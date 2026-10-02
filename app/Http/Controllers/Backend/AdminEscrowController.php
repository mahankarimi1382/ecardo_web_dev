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
 * Class AdminEscrowController
 *
 * کنترلر مدیریت معاملات امانی و اسکرو (Escrow Transactions & Dispute Arbitration)
 * مانیتورینگ مبالغ حبس‌شده در حساب‌های کاستودیال پلتفرم،
 * نظارت بر دوره‌های بازرسی و حمل کالا، و داوری قطعی اختلافات (Arbitration) با لجر دوبل.
 */
class AdminEscrowController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:escrow-manage', only: ['index', 'show']),
            new Middleware('permission:escrow-action', only: ['arbitrate', 'extendInspection', 'forceRelease', 'cancel']),
        ];
    }

    /**
     * لیست معاملات امانی با فیلتر وضعیت، نوع معامله، ارز و جستجو
     */
    public function index(Request $request)
    {
        $filters = [
            'status'    => $request->input('status', 'all'),
            'deal_type' => $request->input('deal_type'),
            'currency'  => $request->input('currency'),
            'search'    => $request->input('search'),
            'per_page'  => $request->integer('per_page', 15),
        ];

        $stats = $this->getEscrowStats();
        $records = $this->queryEscrows($filters);

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'status'  => true,
                'message' => 'لیست معاملات اسکرو با موفقیت دریافت شد.',
                'data'    => [
                    'stats'      => $stats,
                    'filters'    => $filters,
                    'pagination' => [
                        'current_page' => $records['current_page'] ?? 1,
                        'total'        => $records['total'] ?? count($records['data']),
                        'per_page'     => $filters['per_page'],
                        'last_page'    => $records['last_page'] ?? 1,
                    ],
                    'items'      => $records['data'],
                ],
            ]);
        }

        return view('backend.escrows.index', [
            'stats'   => $stats,
            'filters' => $filters,
            'records' => $records,
        ]);
    }

    /**
     * مشاهده پرونده کامل قرارداد امانی، مبالغ حبس‌شده، گفتگوی طرفین و مستندات اختلاف
     */
    public function show(Request $request, $id)
    {
        $escrow = $this->findEscrow($id);

        if (!$escrow) {
            if ($request->expectsJson()) {
                return response()->json(['status' => false, 'message' => 'معامله اسکرو یافت نشد.'], 404);
            }
            if (function_exists('notify')) notify()->error('قرارداد اسکرو یافت نشد.');
            return redirect()->route('admin.escrows.index');
        }

        if ($request->expectsJson()) {
            return response()->json(['status' => true, 'data' => $escrow]);
        }

        return view('backend.escrows.show', ['escrow' => $escrow]);
    }

    /**
     * داوری نهایی و حل اختلاف قرارداد اسکرو (Arbitration Decision) با لجر دوطرفه
     * عملیات: آزادسازی ۱۰۰٪ به فروشنده، عودت ۱۰۰٪ به خریدار، یا تقسیم درصدی منصفانه
     */
    public function arbitrate(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'decision'       => 'required|in:RELEASE_TO_SELLER,REFUND_TO_BUYER,SPLIT_DISBURSEMENT',
            'seller_share'   => 'nullable|required_if:decision,SPLIT_DISBURSEMENT|numeric|min:0|max:100',
            'buyer_share'    => 'nullable|required_if:decision,SPLIT_DISBURSEMENT|numeric|min:0|max:100',
            'legal_verdict'  => 'required|string|max:2000',
        ]);

        if ($validator->fails()) return $this->errorResponse($request, $validator->errors()->first());

        $admin = Auth::user();
        $adminId = $admin ? $admin->id : 1;

        try {
            DB::beginTransaction();

            $escrow = $this->findEscrow($id);
            if (!$escrow) throw new \Exception('معامله اسکرو یافت نشد.');

            $totalAmount = (float) ($escrow['amount'] ?? 0);
            $buyerId = $escrow['buyer_id'] ?? null;
            $sellerId = $escrow['seller_id'] ?? null;
            $currency = $escrow['currency'] ?? 'IRR';

            $buyerPayout = 0.0;
            $sellerPayout = 0.0;

            if ($request->decision === 'RELEASE_TO_SELLER') {
                $sellerPayout = $totalAmount;
            } elseif ($request->decision === 'REFUND_TO_BUYER') {
                $buyerPayout = $totalAmount;
            } else {
                $sShare = (float) $request->seller_share;
                $bShare = (float) $request->buyer_share;
                if (abs(($sShare + $bShare) - 100.0) > 0.01) {
                    throw new \Exception('مجموع سهم خریدار و فروشنده باید دقیقاً ۱۰۰٪ باشد.');
                }
                $sellerPayout = ($totalAmount * $sShare) / 100.0;
                $buyerPayout = ($totalAmount * $bShare) / 100.0;
            }

            // لجر دوطرفه: قفل والت‌های خریدار و فروشنده به ترتیب صعودی (Deadlock Prevention)
            if (Schema::hasTable('wallets') && Schema::hasTable('journal_entries')) {
                $walletIds = array_filter([$buyerId, $sellerId]);
                $wallets = DB::table('wallets')
                    ->whereIn('user_id', $walletIds)
                    ->orderBy('id', 'asc')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('user_id');

                // واریز به فروشنده
                if ($sellerPayout > 0 && isset($wallets[$sellerId])) {
                    $sWallet = $wallets[$sellerId];
                    DB::table('wallets')->where('id', $sWallet->id)->increment('balance', $sellerPayout);

                    DB::table('journal_entries')->insert([
                        'reference_type' => 'ESCROW_ARBITRATION_RELEASE',
                        'reference_id'   => $id,
                        'debit_account'  => 'ESCROW_CUSTODY_POOL',
                        'credit_account' => "WALLET_{$sWallet->id}",
                        'amount'         => $sellerPayout,
                        'currency'       => $currency,
                        'operator_id'    => $adminId,
                        'created_at'     => now(),
                    ]);
                }

                // عودت به خریدار
                if ($buyerPayout > 0 && isset($wallets[$buyerId])) {
                    $bWallet = $wallets[$buyerId];
                    DB::table('wallets')->where('id', $bWallet->id)->increment('balance', $buyerPayout);

                    DB::table('journal_entries')->insert([
                        'reference_type' => 'ESCROW_ARBITRATION_REFUND',
                        'reference_id'   => $id,
                        'debit_account'  => 'ESCROW_CUSTODY_POOL',
                        'credit_account' => "WALLET_{$bWallet->id}",
                        'amount'         => $buyerPayout,
                        'currency'       => $currency,
                        'operator_id'    => $adminId,
                        'created_at'     => now(),
                    ]);
                }
            }

            $arbitrationData = [
                'decision'      => $request->decision,
                'seller_payout' => $sellerPayout,
                'buyer_payout'  => $buyerPayout,
                'verdict'       => $request->legal_verdict,
                'arbitrated_by' => $adminId,
                'arbitrated_at' => now()->toIso8601String(),
            ];

            if (Schema::hasTable('escrow_orders')) {
                DB::table('escrow_orders')->where('id', $id)->update([
                    'status'            => 'RESOLVED',
                    'dispute_status'    => 'CLOSED',
                    'arbitration_data'  => json_encode($arbitrationData, JSON_UNESCAPED_UNICODE),
                    'resolved_at'       => now(),
                    'updated_at'        => now(),
                ]);
            }

            $this->logActivity('escrow_arbitrated', $id, 'EscrowOrder', $arbitrationData);

            DB::commit();

            $msg = 'حکم داوری با موفقیت صادر شد و مبالغ طبق لجر دوطرفه تسویه گردید.';
            if ($request->expectsJson()) {
                return response()->json(['status' => true, 'message' => $msg, 'data' => $arbitrationData]);
            }

            if (function_exists('notify')) notify()->success($msg);
            return back();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("[AdminEscrowController@arbitrate] " . $e->getMessage());
            return $this->errorResponse($request, 'خطا در داوری اسکرو: ' . $e->getMessage());
        }
    }

    /**
     * تمدید مهلت بازرسی کالا برای خریدار
     */
    public function extendInspection(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'days'   => 'required|integer|min:1|max:30',
            'reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) return $this->errorResponse($request, $validator->errors()->first());

        try {
            if (Schema::hasTable('escrow_orders')) {
                $escrow = DB::table('escrow_orders')->where('id', $id)->first();
                $currentDeadline = $escrow && $escrow->inspection_deadline ? \Carbon\Carbon::parse($escrow->inspection_deadline) : now();

                DB::table('escrow_orders')->where('id', $id)->update([
                    'inspection_deadline' => $currentDeadline->addDays($request->days),
                    'updated_at'          => now(),
                ]);
            }

            $this->logActivity('escrow_inspection_extended', $id, 'EscrowOrder', $request->all());

            $msg = "مهلت بازرسی کالا به مدت {$request->days} روز تمدید شد.";
            if ($request->expectsJson()) return response()->json(['status' => true, 'message' => $msg]);

            if (function_exists('notify')) notify()->success($msg);
            return back();
        } catch (\Exception $e) {
            return $this->errorResponse($request, $e->getMessage());
        }
    }

    /**
     * آزادسازی اضطراری وجوه امانی به فروشنده
     */
    public function forceRelease(Request $request, $id)
    {
        $request->merge(['decision' => 'RELEASE_TO_SELLER', 'legal_verdict' => 'آزادسازی اضطراری توسط مدیریت ارشد پلتفرم']);
        return $this->arbitrate($request, $id);
    }

    /**
     * لغو معامله اسکرو و استرداد کامل به خریدار
     */
    public function cancel(Request $request, $id)
    {
        $request->merge(['decision' => 'REFUND_TO_BUYER', 'legal_verdict' => 'ابطال قرارداد امانی توسط ادمین']);
        return $this->arbitrate($request, $id);
    }

    // -------------------------------------------------------------
    // متدهای کمکی داخلی
    // -------------------------------------------------------------

    protected function getEscrowStats(): array
    {
        if (Schema::hasTable('escrow_orders')) {
            return [
                'total_deals'     => DB::table('escrow_orders')->count(),
                'active_custody'  => DB::table('escrow_orders')->whereIn('status', ['FUNDED', 'IN_TRANSIT', 'INSPECTING'])->count(),
                'disputed_deals'  => DB::table('escrow_orders')->where('dispute_status', 'OPEN')->count(),
                'locked_volume'   => DB::table('escrow_orders')->whereIn('status', ['FUNDED', 'IN_TRANSIT', 'INSPECTING', 'DISPUTED'])->sum('amount'),
                'resolved_volume' => DB::table('escrow_orders')->where('status', 'RESOLVED')->sum('amount'),
            ];
        }

        return ['total_deals' => 0, 'active_custody' => 0, 'disputed_deals' => 0, 'locked_volume' => 0, 'resolved_volume' => 0];
    }

    protected function queryEscrows(array $filters): array
    {
        if (Schema::hasTable('escrow_orders')) {
            $query = DB::table('escrow_orders as eo')
                ->leftJoin('users as b', 'eo.buyer_id', '=', 'b.id')
                ->leftJoin('users as s', 'eo.seller_id', '=', 's.id')
                ->select(
                    'eo.*',
                    'b.name as buyer_name',
                    'b.email as buyer_email',
                    's.name as seller_name',
                    's.email as seller_email'
                );

            if (!empty($filters['status']) && $filters['status'] !== 'all') {
                $query->where('eo.status', $filters['status']);
            }
            if (!empty($filters['deal_type'])) {
                $query->where('eo.deal_type', $filters['deal_type']);
            }
            if (!empty($filters['search'])) {
                $q = $filters['search'];
                $query->where(function ($sq) use ($q) {
                    $sq->where('eo.order_no', 'like', "%{$q}%")
                       ->orWhere('b.name', 'like', "%{$q}%")
                       ->orWhere('s.name', 'like', "%{$q}%");
                });
            }

            $paginator = $query->orderByDesc('eo.id')->paginate($filters['per_page']);
            return [
                'current_page' => $paginator->currentPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
                'data'         => $paginator->items(),
            ];
        }

        return ['current_page' => 1, 'total' => 0, 'last_page' => 1, 'data' => []];
    }

    protected function findEscrow($id): ?array
    {
        if (Schema::hasTable('escrow_orders')) {
            $rec = DB::table('escrow_orders as eo')
                ->leftJoin('users as b', 'eo.buyer_id', '=', 'b.id')
                ->leftJoin('users as s', 'eo.seller_id', '=', 's.id')
                ->select('eo.*', 'b.name as buyer_name', 'b.email as buyer_email', 's.name as seller_name', 's.email as seller_email')
                ->where('eo.id', $id)
                ->first();

            return $rec ? (array) $rec : null;
        }
        return null;
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

    protected function errorResponse(Request $request, string $msg, int $code = 400)
    {
        if ($request->expectsJson()) return response()->json(['status' => false, 'message' => $msg], $code);
        if (function_exists('notify')) notify()->error($msg);
        return back()->withInput();
    }
}
