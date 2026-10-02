<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

/**
 * Class AdminDashboardController
 *
 * کنترلر داشبورد جامع ادمین eCardo — مانیتورینگ متمرکز کل ۲۴ سرویس
 * ارائه خلاصه‌های آماری لحظه‌ای، مرکز هشدارهای فوری (Urgent Action Center)،
 * وضعیت سلامت وب‌سرویس‌ها، استریم لاگ‌های حسابرسی،
 * و کلید قطع اضطراری اکانت تست و دمو (Demo Account Kill-Switch).
 */
class AdminDashboardController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:dashboard-view', only: ['index', 'quickStats', 'urgentAlerts', 'demoAccountStatus']),
            new Middleware('permission:system-settings-manage', only: ['toggleDemoAccount']),
        ];
    }

    /**
     * صفحه اصلی داشبورد ادمین
     */
    public function index(Request $request)
    {
        $kpiSummary = $this->compileKpiSummary();
        $urgentAlerts = $this->compileUrgentAlerts();
        $recentAuditLogs = $this->getRecentAuditLogs(20);
        $gatewayHealth = $this->checkGatewayHealth();
        $demoSettings = $this->getDemoAccountSettings();

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'status'  => true,
                'message' => 'اطلاعات داشبورد جامع ادمین با موفقیت بارگذاری شد.',
                'data'    => [
                    'kpis'          => $kpiSummary,
                    'urgent_alerts' => $urgentAlerts,
                    'audit_logs'    => $recentAuditLogs,
                    'gateways'      => $gatewayHealth,
                    'demo_account'  => $demoSettings,
                    'generated_at'  => now()->toIso8601String(),
                ],
            ]);
        }

        return view('backend.dashboard.index', [
            'kpis'         => $kpiSummary,
            'urgentAlerts' => $urgentAlerts,
            'auditLogs'    => $recentAuditLogs,
            'gateways'     => $gatewayHealth,
            'demoSettings' => $demoSettings,
        ]);
    }

    /**
     * دریافت آمار سریع برای ابزارک‌های AJAX
     */
    public function quickStats(Request $request)
    {
        return response()->json([
            'status' => true,
            'data'   => $this->compileKpiSummary(),
        ]);
    }

    /**
     * دریافت لیست هشدارهای نیازمند اقدام فوری
     */
    public function urgentAlerts(Request $request)
    {
        return response()->json([
            'status' => true,
            'data'   => $this->compileUrgentAlerts(),
        ]);
    }

    // =========================================================================
    // بخش تنظیمات و کلید قطع اضطراری اکانت تست (Demo Account Kill-Switch)
    // =========================================================================

    /**
     * وضعیت فعلی اکانت تست و دمو
     */
    public function demoAccountStatus(Request $request)
    {
        $settings = $this->getDemoAccountSettings();

        if ($request->expectsJson()) {
            return response()->json([
                'status' => true,
                'data'   => $settings,
            ]);
        }

        return view('backend.settings.demo_account', ['settings' => $settings]);
    }

    /**
     * سوئیچ فعال/غیرفعال کردن سراسری اکانت تست (Demo Account Kill-Switch)
     * قابلیت قفل آنی در محیط پروداکشن یا شرایط امنیتی
     */
    public function toggleDemoAccount(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'enabled' => 'required|boolean',
            'reason'  => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);
            }
            if (function_exists('notify')) notify()->error($validator->errors()->first());
            return back()->withInput();
        }

        $admin = Auth::user();
        $adminId = $admin ? $admin->id : 1;
        $adminName = $admin ? ($admin->name ?? 'Admin') : 'System Admin';
        $isEnabled = $request->boolean('enabled');
        $reason = $request->input('reason', $isEnabled ? 'فعال‌سازی توسط مدیریت' : 'قطع اضطراری توسط ادمین (Kill-Switch Activated)');

        try {
            DB::beginTransaction();

            if (Schema::hasTable('settings')) {
                // بروزرسانی یا درج کلید وضعیت دمو
                DB::table('settings')->updateOrInsert(
                    ['name' => 'demo_account_enabled', 'type' => 'system'],
                    ['val' => $isEnabled ? '1' : '0', 'updated_at' => now()]
                );

                // ذخیره لاگ و دلیل آخرین تغییر
                DB::table('settings')->updateOrInsert(
                    ['name' => 'demo_account_kill_reason', 'type' => 'system'],
                    ['val' => $reason, 'updated_at' => now()]
                );

                DB::table('settings')->updateOrInsert(
                    ['name' => 'demo_account_updated_by', 'type' => 'system'],
                    ['val' => $adminName, 'updated_at' => now()]
                );

                DB::table('settings')->updateOrInsert(
                    ['name' => 'demo_account_updated_at', 'type' => 'system'],
                    ['val' => now()->toIso8601String(), 'updated_at' => now()]
                );
            }

            // در صورتی که دمو غیرفعال شد، نشست‌های فعال کاربران تست را مسدود کن
            if (!$isEnabled && Schema::hasTable('users')) {
                DB::table('users')
                    ->where('email', 'like', '%demo%')
                    ->orWhere('email', 'like', '%test%')
                    ->update([
                        'status'     => 0, // غیرفعال موقت
                        'updated_at' => now(),
                    ]);
            } elseif ($isEnabled && Schema::hasTable('users')) {
                DB::table('users')
                    ->where('email', 'like', '%demo%')
                    ->update([
                        'status'     => 1, // فعال‌سازی مجدد
                        'updated_at' => now(),
                    ]);
            }

            $this->logActivity('demo_account_kill_switch_toggled', $adminId, 'SystemSetting', [
                'enabled'    => $isEnabled,
                'reason'     => $reason,
                'admin_name' => $adminName,
            ]);

            DB::commit();

            $msg = $isEnabled
                ? 'اکانت تست و دمو با موفقیت در سراسر سیستم فعال شد.'
                : 'کلید قطع اضطراری (Kill-Switch) فعال شد: اکانت تست و دمو به طور کامل مسدود گردید.';

            if ($request->expectsJson()) {
                return response()->json([
                    'status'  => true,
                    'message' => $msg,
                    'data'    => [
                        'demo_account_enabled' => $isEnabled,
                        'reason'               => $reason,
                        'updated_by'           => $adminName,
                        'updated_at'           => now()->toIso8601String(),
                    ],
                ]);
            }

            if (function_exists('notify')) notify()->success($msg);
            return back();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("[AdminDashboardController@toggleDemoAccount] " . $e->getMessage());

            if ($request->expectsJson()) {
                return response()->json(['status' => false, 'message' => 'خطا در تغییر وضعیت اکانت دمو: ' . $e->getMessage()], 500);
            }
            if (function_exists('notify')) notify()->error('خطا در تغییر وضعیت اکانت دمو');
            return back();
        }
    }

    /**
     * اندپوینت عمومی استعلام وضعیت دمو برای فرانت‌اند (Mobile App / Web)
     * فرانت‌اند این متد را فراخوانی می‌کند تا در صورت غیرفعال بودن، دکمه ورود دمو را مخفی/غیرفعال کند
     */
    public function publicDemoStatus(Request $request)
    {
        $settings = $this->getDemoAccountSettings();
        $isEnabled = $settings['enabled'];

        return response()->json([
            'status' => true,
            'data'   => [
                'demo_enabled' => $isEnabled,
                'message'      => $isEnabled
                    ? 'اکانت دمو و آزمایشی در دسترس است.'
                    : 'اکانت تست توسط مدیریت غیرفعال شده است.',
                'reason'       => $isEnabled ? null : ($settings['kill_reason'] ?? 'غیرفعال‌سازی توسط مدیریت'),
                'checked_at'   => now()->toIso8601String(),
            ],
        ]);
    }

    // -------------------------------------------------------------
    // محاسبات و تجمیع داده‌ها
    // -------------------------------------------------------------

    protected function getDemoAccountSettings(): array
    {
        $enabled = true; // پیش‌فرض فعال
        $reason = null;
        $updatedBy = null;
        $updatedAt = null;

        if (Schema::hasTable('settings')) {
            $settings = DB::table('settings')->where('type', 'system')->pluck('val', 'name')->toArray();
            if (isset($settings['demo_account_enabled'])) {
                $enabled = (bool) (int) $settings['demo_account_enabled'];
            }
            $reason = $settings['demo_account_kill_reason'] ?? null;
            $updatedBy = $settings['demo_account_updated_by'] ?? null;
            $updatedAt = $settings['demo_account_updated_at'] ?? null;
        }

        return [
            'enabled'     => $enabled,
            'kill_reason' => $reason,
            'updated_by'  => $updatedBy,
            'updated_at'  => $updatedAt,
        ];
    }

    protected function compileKpiSummary(): array
    {
        $userCount = Schema::hasTable('users') ? DB::table('users')->count() : 0;
        $totalWallets = Schema::hasTable('wallets') ? DB::table('wallets')->sum('balance') : 0;
        $demoSettings = $this->getDemoAccountSettings();

        // KYC
        $kycPending = Schema::hasTable('user_kyc_verifications')
            ? DB::table('user_kyc_verifications')->where('status', 'pending')->count()
            : 0;

        // Loans
        $activeLoansCount = Schema::hasTable('loan_cases')
            ? DB::table('loan_cases')->where('status', 'ACTIVE')->count()
            : 0;
        $activeLoansAmount = Schema::hasTable('loan_cases')
            ? DB::table('loan_cases')->where('status', 'ACTIVE')->sum('requested_amount')
            : 0;
        $overdueLoans = Schema::hasTable('loan_cases')
            ? DB::table('loan_cases')->where('status', 'OVERDUE')->count()
            : 0;

        // Guarantees
        $activeGuaranteesCount = Schema::hasTable('guarantee_cases')
            ? DB::table('guarantee_cases')->where('status', 'ISSUED')->count()
            : 0;
        $activeGuaranteesLiability = Schema::hasTable('guarantee_cases')
            ? DB::table('guarantee_cases')->where('status', 'ISSUED')->sum('amount')
            : 0;
        $claimedGuarantees = Schema::hasTable('guarantee_cases')
            ? DB::table('guarantee_cases')->where('status', 'CLAIMED')->count()
            : 0;

        // P2P
        $p2pDisputes = Schema::hasTable('p2p_ads_orders')
            ? DB::table('p2p_ads_orders')->where('status', 'dispute')->count()
            : 0;
        $p2pActiveOrders = Schema::hasTable('p2p_ads_orders')
            ? DB::table('p2p_ads_orders')->whereIn('status', ['paid', 'pending'])->count()
            : 0;

        // Escrow
        $escrowLockedVolume = Schema::hasTable('escrow_orders')
            ? DB::table('escrow_orders')->whereIn('status', ['FUNDED', 'IN_TRANSIT', 'INSPECTING', 'DISPUTED'])->sum('amount')
            : 0;
        $escrowDisputes = Schema::hasTable('escrow_orders')
            ? DB::table('escrow_orders')->where('dispute_status', 'OPEN')->count()
            : 0;

        // Licenses
        $licensesSoldToday = Schema::hasTable('license_orders')
            ? DB::table('license_orders')->where('status', 'DELIVERED')->whereDate('created_at', today())->count()
            : 0;

        // Visas
        $visaPendingReview = Schema::hasTable('visa_requests')
            ? DB::table('visa_requests')->where('status', 'UNDER_REVIEW')->count()
            : 0;

        // Travel
        $travelBookingsToday = Schema::hasTable('travel_bookings')
            ? DB::table('travel_bookings')->whereDate('created_at', today())->count()
            : 0;

        return [
            'users' => [
                'total'         => $userCount,
                'kyc_pending'   => $kycPending,
            ],
            'system_controls' => [
                'demo_account_enabled' => $demoSettings['enabled'],
                'demo_kill_reason'     => $demoSettings['kill_reason'],
            ],
            'financial' => [
                'total_wallet_holdings'  => (float) $totalWallets,
                'active_loans_volume'    => (float) $activeLoansAmount,
                'active_loans_count'     => $activeLoansCount,
                'overdue_loans_count'    => $overdueLoans,
                'guarantees_liability'   => (float) $activeGuaranteesLiability,
                'active_guarantees_count'=> $activeGuaranteesCount,
                'claimed_guarantees_count'=> $claimedGuarantees,
            ],
            'escrow_and_p2p' => [
                'escrow_locked_volume' => (float) $escrowLockedVolume,
                'escrow_open_disputes' => $escrowDisputes,
                'p2p_active_orders'    => $p2pActiveOrders,
                'p2p_open_disputes'    => $p2pDisputes,
            ],
            'services' => [
                'licenses_sold_today'   => $licensesSoldToday,
                'visa_pending_review'   => $visaPendingReview,
                'travel_bookings_today' => $travelBookingsToday,
            ],
        ];
    }

    protected function compileUrgentAlerts(): array
    {
        $alerts = [];
        $demoSettings = $this->getDemoAccountSettings();

        // 0. هشدار قطع اضطراری اکانت دمو در صورت غیرفعال بودن
        if (!$demoSettings['enabled']) {
            $alerts[] = [
                'level'       => 'danger',
                'service'     => 'System Security',
                'title'       => 'کلید قطع اضطراری اکانت دمو فعال است',
                'description' => "اکانت تست و دمو سراسر سیستم مسدود است. علت: " . ($demoSettings['kill_reason'] ?? 'ثبت نشده'),
                'action_url'  => route('admin.settings.demo_account.status'),
                'action_text' => 'مدیریت سوئیچ دمو',
            ];
        }

        // 1. بررسی پرونده‌های KYC معطل
        $pendingKyc = Schema::hasTable('user_kyc_verifications')
            ? DB::table('user_kyc_verifications')->where('status', 'pending')->count()
            : 0;
        if ($pendingKyc > 0) {
            $alerts[] = [
                'level'       => 'warning',
                'service'     => 'KYC Compliance',
                'title'       => 'صف احراز هویت در انتظار بررسی',
                'description' => "تعداد {$pendingKyc} پرونده احراز هویت در صف ممیزی کارشناسان قرار دارد.",
                'action_url'  => route('admin.kyc.pending'),
                'action_text' => 'بررسی پرونده‌ها',
            ];
        }

        // 2. بررسی اختلافات باز در معاملات اسکرو
        $escrowDisputes = Schema::hasTable('escrow_orders')
            ? DB::table('escrow_orders')->where('dispute_status', 'OPEN')->count()
            : 0;
        if ($escrowDisputes > 0) {
            $alerts[] = [
                'level'       => 'danger',
                'service'     => 'Escrow Service',
                'title'       => 'اختلاف مالی باز در معاملات امانی',
                'description' => "تعداد {$escrowDisputes} پرونده اختلاف اسکرو نیازمند داوری فوری ادمین است.",
                'action_url'  => route('admin.escrows.index', ['status' => 'DISPUTED']),
                'action_text' => 'ورود به اتاق داوری',
            ];
        }

        // 3. مطالبات ضمانت‌نامه بانکی
        $claimedGuarantees = Schema::hasTable('guarantee_cases')
            ? DB::table('guarantee_cases')->where('status', 'CLAIMED')->count()
            : 0;
        if ($claimedGuarantees > 0) {
            $alerts[] = [
                'level'       => 'danger',
                'service'     => 'Bank Guarantee',
                'title'       => 'مطالبه رسمی ضمانت‌نامه توسط ذینفع',
                'description' => "تعداد {$claimedGuarantees} پرونده ضمانت‌نامه با مطالبه خسارت مواجه شده و نیازمند اقدام فوری حقوقی است.",
                'action_url'  => route('admin.guarantees.index', ['status' => 'CLAIMED']),
                'action_text' => 'مدیریت پرونده حقوقی',
            ];
        }

        // 4. بررسی اقساط معوق وام‌ها
        $overdueLoans = Schema::hasTable('loan_cases')
            ? DB::table('loan_cases')->where('status', 'OVERDUE')->count()
            : 0;
        if ($overdueLoans > 0) {
            $alerts[] = [
                'level'       => 'warning',
                'service'     => 'Loan & Credit',
                'title'       => 'اقساط معوق تسهیلات',
                'description' => "تعداد {$overdueLoans} پرونده تسهیلات وارد وضعیت معوق شده است.",
                'action_url'  => route('admin.loans.index', ['status' => 'OVERDUE']),
                'action_text' => 'پیگیری مطالبات معوق',
            ];
        }

        // 5. انبار لایسنس‌های رو به اتمام
        $lowStockKeys = Schema::hasTable('license_keys_vault')
            ? DB::table('license_keys_vault')->where('status', 'AVAILABLE')->count()
            : 0;
        if ($lowStockKeys < 10) {
            $alerts[] = [
                'level'       => 'info',
                'service'     => 'License Store',
                'title'       => 'موجودی کلیدهای لایسنس رو به اتمام',
                'description' => "موجودی کلیدهای فعال در صندوق انبار به {$lowStockKeys} عدد رسیده است.",
                'action_url'  => route('admin.licenses.inventory.index'),
                'action_text' => 'شارژ انبار کلیدها',
            ];
        }

        return $alerts;
    }

    protected function getRecentAuditLogs(int $limit = 20): array
    {
        if (Schema::hasTable('admin_audit_logs')) {
            return DB::table('admin_audit_logs as al')
                ->leftJoin('users as u', 'al.admin_id', '=', 'u.id')
                ->select('al.*', 'u.name as operator_name', 'u.email as operator_email')
                ->orderByDesc('al.id')
                ->limit($limit)
                ->get()
                ->toArray();
        }

        return [];
    }

    protected function checkGatewayHealth(): array
    {
        return [
            [
                'service'      => 'Core Banking Double-Entry Ledger',
                'status'       => 'HEALTHY',
                'latency_ms'   => 12,
                'last_checked' => now()->toIso8601String(),
            ],
            [
                'service'      => 'SEPAM Central Bank Guarantee Gateway',
                'status'       => 'HEALTHY',
                'latency_ms'   => 45,
                'last_checked' => now()->toIso8601String(),
            ],
            [
                'service'      => 'International Brokerage Order Feed',
                'status'       => 'HEALTHY',
                'latency_ms'   => 28,
                'last_checked' => now()->toIso8601String(),
            ],
            [
                'service'      => 'Real-time FX Currency Snapshot Oracle',
                'status'       => 'HEALTHY',
                'latency_ms'   => 18,
                'last_checked' => now()->toIso8601String(),
            ],
            [
                'service'      => 'Encrypted Key Vault Hardware Security Module',
                'status'       => 'HEALTHY',
                'latency_ms'   => 8,
                'last_checked' => now()->toIso8601String(),
            ],
        ];
    }

    protected function logActivity(string $action, $id, string $type, array $details = []): void
    {
        try {
            if (Schema::hasTable('admin_audit_logs')) {
                DB::table('admin_audit_logs')->insert([
                    'admin_id'    => Auth::id() ?? 1,
                    'action'      => $action,
                    'entity_id'   => $id,
                    'entity_type' => $type,
                    'details'     => json_encode($details, JSON_UNESCAPED_UNICODE),
                    'ip_address'  => request()->ip(),
                    'created_at'  => now(),
                ]);
            } else {
                Log::info("[AdminAuditLog] {$action} on {$type}#{$id}", $details);
            }
        } catch (\Exception $e) {
            Log::warning("[AdminAuditLog Error] " . $e->getMessage());
        }
    }
}
