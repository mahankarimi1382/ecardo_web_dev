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
 * Class AdminLoanController
 *
 * کنترلر مدیریت پرونده‌های تسهیلات و اعتبارات (Loans & Credit)
 * پشتیبانی از اعتبارسنجی متقاضی و ضامنین، صدور پیشنهاد (Offer)،
 * تایید وثایق نقدی/رمزارزی/طلا، واریز وجه با لجر دوطرفه (Disbursement)،
 * پایش و مدیریت اقساط و تسویه زودهنگام.
 */
class AdminLoanController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:loan-manage', only: ['index', 'show', 'installments']),
            new Middleware('permission:loan-action', only: ['assessAndOffer', 'verifyCollateral', 'disburse', 'markInstallmentPaid', 'reject', 'cancel']),
        ];
    }

    /**
     * لیست پرونده‌های تسهیلات با فیلتر وضعیت، طرح، تاریخ و جستجو
     */
    public function index(Request $request)
    {
        $filters = [
            'status'     => $request->input('status', 'all'),
            'product_id' => $request->input('product_id'),
            'search'     => $request->input('search'),
            'date_from'  => $request->input('date_from'),
            'date_to'    => $request->input('date_to'),
            'per_page'   => $request->integer('per_page', 15),
        ];

        $stats = $this->getLoanStats();
        $records = $this->queryLoanCases($filters);

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'status'  => true,
                'message' => 'لیست پرونده‌های وام با موفقیت دریافت شد.',
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

        return view('backend.loans.index', [
            'stats'   => $stats,
            'filters' => $filters,
            'records' => $records,
        ]);
    }

    /**
     * مشاهده پرونده کامل تسهیلات، وضعیت وثایق، جدول اقساط و رویدادها
     */
    public function show(Request $request, $id)
    {
        $loanCase = $this->findLoanCase($id);

        if (!$loanCase) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status'  => false,
                    'message' => 'پرونده تسهیلات مورد نظر یافت نشد.',
                ], 404);
            }
            if (function_exists('notify')) {
                notify()->error('پرونده وام یافت نشد.');
            }
            return redirect()->route('admin.loans.index');
        }

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'status'  => true,
                'message' => 'جزئیات پرونده وام بارگذاری شد.',
                'data'    => $loanCase,
            ]);
        }

        return view('backend.loans.show', [
            'loanCase' => $loanCase,
        ]);
    }

    /**
     * ارزیابی اعتباری و صدور آفر رسمی وام برای کاربر
     */
    public function assessAndOffer(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'offered_amount'     => 'required|numeric|min:1000000',
            'rate_pct'           => 'required|numeric|min:0|max:100',
            'tenure_months'      => 'required|integer|min:1|max:120',
            'validity_days'      => 'nullable|integer|min:1|max:30',
            'admin_note'         => 'nullable|string|max:1000',
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

        try {
            DB::beginTransaction();

            $loanCase = $this->findLoanCase($id);
            if (!$loanCase) throw new \Exception('پرونده تسهیلات یافت نشد.');

            $offeredAmount = (float) $request->offered_amount;
            $ratePct = (float) $request->rate_pct;
            $tenure = (int) $request->tenure_months;
            $validityDays = (int) $request->input('validity_days', 7);

            // محاسبه اقساط فرمول Amortized
            $monthlyEmi = $this->calculateEmi($offeredAmount, $ratePct, $tenure);
            $schedulePreview = $this->generateSchedulePreview($offeredAmount, $ratePct, $tenure, $monthlyEmi);

            $offerPayload = [
                'offered_amount'    => $offeredAmount,
                'rate_pct'          => $ratePct,
                'tenure_months'     => $tenure,
                'monthly_emi'       => $monthlyEmi,
                'expires_at'        => now()->addDays($validityDays)->toIso8601String(),
                'schedule_preview'  => $schedulePreview,
                'assessed_by'       => $adminId,
                'assessed_at'       => now()->toIso8601String(),
                'note'              => $request->admin_note,
            ];

            if (Schema::hasTable('loan_cases')) {
                DB::table('loan_cases')->where('id', $id)->update([
                    'status'            => 'OFFERED',
                    'offer_data'        => json_encode($offerPayload, JSON_UNESCAPED_UNICODE),
                    'offer_expires_at'  => now()->addDays($validityDays),
                    'admin_note'        => $request->admin_note,
                    'updated_at'        => now(),
                ]);
            }

            $this->logActivity('loan_offer_issued', $id, 'LoanCase', $offerPayload);

            DB::commit();

            $msg = "پیشنهاد تسهیلات به مبلغ " . number_format($offeredAmount) . " با موفقیت صادر و به کارتابل کاربر ارسال شد.";
            if ($request->expectsJson()) {
                return response()->json([
                    'status'  => true,
                    'message' => $msg,
                    'data'    => ['id' => $id, 'status' => 'OFFERED', 'offer' => $offerPayload],
                ]);
            }

            if (function_exists('notify')) notify()->success($msg);
            return back();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("[AdminLoanController@assessAndOffer] " . $e->getMessage());
            return $this->errorResponse($request, 'خطا در صدور آفر: ' . $e->getMessage());
        }
    }

    /**
     * تایید وثایق تودیعی (سپرده نقدی، رمزارز یا طلا)
     */
    public function verifyCollateral(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'collateral_id' => 'required',
            'action'        => 'required|in:accept,reject',
            'note'          => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($request, $validator->errors()->first());
        }

        try {
            DB::beginTransaction();

            $isAccepted = $request->action === 'accept';
            $nextStatus = $isAccepted ? 'AWAITING_SIGNING' : 'COMPLEMENT_REQUIRED';

            if (Schema::hasTable('loan_cases')) {
                DB::table('loan_cases')->where('id', $id)->update([
                    'status'            => $nextStatus,
                    'collateral_status' => $isAccepted ? 'VERIFIED_LOCKED' : 'REJECTED',
                    'admin_note'        => $request->note,
                    'updated_at'        => now(),
                ]);
            }

            $this->logActivity('loan_collateral_verified', $id, 'LoanCase', [
                'action'        => $request->action,
                'collateral_id' => $request->collateral_id,
                'note'          => $request->note,
            ]);

            DB::commit();

            $msg = $isAccepted
                ? 'وثیقه پرونده با موفقیت تایید و پرونده به مرحله امضای قرارداد منتقل شد.'
                : 'وثیقه رد شد و درخواست اصلاح به کاربر ارسال گردید.';

            if ($request->expectsJson()) {
                return response()->json(['status' => true, 'message' => $msg, 'data' => ['status' => $nextStatus]]);
            }

            if (function_exists('notify')) notify()->success($msg);
            return back();

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse($request, $e->getMessage());
        }
    }

    /**
     * پرداخت و آزادسازی مبلغ وام به کیف پول کاربر با لجر دوطرفه (Disbursement)
     */
    public function disburse(Request $request, $id)
    {
        $admin = Auth::user();
        $adminId = $admin ? $admin->id : 1;

        try {
            DB::beginTransaction();

            $loanCase = $this->findLoanCase($id);
            if (!$loanCase) throw new \Exception('پرونده وام یافت نشد.');

            $amount = (float) ($loanCase['offered_amount'] ?? $loanCase['requested_amount'] ?? 0);
            $userId = $loanCase['user_id'] ?? null;

            if ($amount <= 0 || !$userId) {
                throw new \Exception('مبلغ وام یا کاربر نامعتبر است.');
            }

            // لجر دوطرفه: قفل والت با شناسه صعودی (Deadlock Prevention)
            if (Schema::hasTable('wallets') && Schema::hasTable('journal_entries')) {
                $wallet = DB::table('wallets')
                    ->where('user_id', $userId)
                    ->orderBy('id', 'asc')
                    ->lockForUpdate()
                    ->first();

                if ($wallet) {
                    // بستانکار کردن کیف پول کاربر و ثبت سند دوبل
                    DB::table('wallets')->where('id', $wallet->id)->increment('balance', $amount);

                    DB::table('journal_entries')->insert([
                        'reference_type' => 'LOAN_DISBURSEMENT',
                        'reference_id'   => $id,
                        'debit_account'  => 'SYSTEM_LOAN_POOL',
                        'credit_account' => "WALLET_{$wallet->id}",
                        'amount'         => $amount,
                        'currency'       => $loanCase['currency'] ?? 'IRR',
                        'operator_id'    => $adminId,
                        'created_at'     => now(),
                    ]);
                }
            }

            // ایجاد اقساط قطعی در جدول اقساط
            $this->createDefinitiveInstallments($id, $loanCase, $amount);

            if (Schema::hasTable('loan_cases')) {
                DB::table('loan_cases')->where('id', $id)->update([
                    'status'       => 'ACTIVE',
                    'disbursed_at' => now(),
                    'disbursed_by' => $adminId,
                    'updated_at'   => now(),
                ]);
            }

            $this->logActivity('loan_disbursed', $id, 'LoanCase', [
                'amount'   => $amount,
                'user_id'  => $userId,
                'admin_id' => $adminId,
            ]);

            DB::commit();

            $msg = "مبلغ " . number_format($amount) . " با موفقیت به والت کاربر واریز شد و اقساط فعال گردید.";
            if ($request->expectsJson()) {
                return response()->json(['status' => true, 'message' => $msg, 'data' => ['id' => $id, 'status' => 'ACTIVE']]);
            }

            if (function_exists('notify')) notify()->success($msg);
            return back();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("[AdminLoanController@disburse] " . $e->getMessage());
            return $this->errorResponse($request, 'خطا در واریز تسهیلات: ' . $e->getMessage());
        }
    }

    /**
     * مشاهده جدول اقساط پرونده
     */
    public function installments(Request $request, $id)
    {
        $installments = $this->queryInstallments($id);

        if ($request->expectsJson()) {
            return response()->json([
                'status'  => true,
                'message' => 'اقساط پرونده با موفقیت دریافت شد.',
                'data'    => $installments,
            ]);
        }

        return view('backend.loans.installments', [
            'loanCaseId'   => $id,
            'installments' => $installments,
        ]);
    }

    /**
     * ثبت دستی پرداخت یک قسط توسط ادمین
     */
    public function markInstallmentPaid(Request $request, $id, $instId)
    {
        try {
            DB::beginTransaction();

            if (Schema::hasTable('loan_installments')) {
                DB::table('loan_installments')->where('id', $instId)->where('case_id', $id)->update([
                    'status'     => 'PAID',
                    'paid_at'    => now(),
                    'paid_via'   => 'ADMIN_MANUAL',
                    'updated_at' => now(),
                ]);

                // اگر تمام اقساط پرداخت شدند، پرونده تسویه کامل می‌شود
                $remaining = DB::table('loan_installments')->where('case_id', $id)->where('status', '!=', 'PAID')->count();
                if ($remaining === 0) {
                    DB::table('loan_cases')->where('id', $id)->update([
                        'status'       => 'COMPLETED',
                        'completed_at' => now(),
                        'updated_at'   => now(),
                    ]);
                }
            }

            $this->logActivity('installment_manual_payment', $instId, 'LoanInstallment', ['case_id' => $id]);

            DB::commit();

            if ($request->expectsJson()) {
                return response()->json(['status' => true, 'message' => 'قسط با موفقیت به عنوان تسویه‌شده ثبت گردید.']);
            }

            if (function_exists('notify')) notify()->success('قسط با موفقیت ثبت شد.');
            return back();

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse($request, $e->getMessage());
        }
    }

    /**
     * رد پرونده تسهیلات
     */
    public function reject(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) return $this->errorResponse($request, $validator->errors()->first());

        try {
            if (Schema::hasTable('loan_cases')) {
                DB::table('loan_cases')->where('id', $id)->update([
                    'status'     => 'REJECTED',
                    'admin_note' => $request->reason,
                    'updated_at' => now(),
                ]);
            }

            $this->logActivity('loan_rejected', $id, 'LoanCase', ['reason' => $request->reason]);

            if ($request->expectsJson()) {
                return response()->json(['status' => true, 'message' => 'پرونده وام رد شد.']);
            }

            if (function_exists('notify')) notify()->success('پرونده وام رد شد.');
            return back();
        } catch (\Exception $e) {
            return $this->errorResponse($request, $e->getMessage());
        }
    }

    /**
     * لغو پرونده تسهیلات
     */
    public function cancel(Request $request, $id)
    {
        try {
            if (Schema::hasTable('loan_cases')) {
                DB::table('loan_cases')->where('id', $id)->update([
                    'status'     => 'CANCELLED',
                    'updated_at' => now(),
                ]);
            }

            $this->logActivity('loan_cancelled', $id, 'LoanCase');

            if ($request->expectsJson()) {
                return response()->json(['status' => true, 'message' => 'پرونده تسهیلات با موفقیت لغو شد.']);
            }

            if (function_exists('notify')) notify()->success('پرونده تسهیلات لغو شد.');
            return back();
        } catch (\Exception $e) {
            return $this->errorResponse($request, $e->getMessage());
        }
    }

    // -------------------------------------------------------------
    // محاسبات و هلپرها
    // -------------------------------------------------------------

    protected function calculateEmi(float $principal, float $annualRatePct, int $tenureMonths): float
    {
        if ($principal <= 0 || $tenureMonths <= 0) return 0.0;
        if ($annualRatePct <= 0) return $principal / $tenureMonths;

        $monthlyRate = ($annualRatePct / 100.0) / 12.0;
        $factor = pow(1.0 + $monthlyRate, $tenureMonths);
        if ($factor <= 1.0) return $principal / $tenureMonths;

        return ($principal * $monthlyRate * $factor) / ($factor - 1.0);
    }

    protected function generateSchedulePreview(float $principal, float $annualRatePct, int $tenure, float $emi): array
    {
        $schedule = [];
        $balance = $principal;
        $monthlyRate = ($annualRatePct / 100.0) / 12.0;

        for ($seq = 1; $seq <= $tenure; $seq++) {
            $interest = $balance * $monthlyRate;
            $principalPart = $emi - $interest;
            $balance -= $principalPart;
            if ($balance < 0) $balance = 0;

            $schedule[] = [
                'seq'            => $seq,
                'due_date'       => now()->addMonths($seq)->format('Y-m-d'),
                'amount'         => round($emi),
                'principal_part' => round($principalPart),
                'interest_part'  => round($interest),
                'rem_balance'    => round($balance),
            ];
        }

        return $schedule;
    }

    protected function createDefinitiveInstallments(int $caseId, array $loanCase, float $principal): void
    {
        if (!Schema::hasTable('loan_installments')) return;

        $rate = (float) ($loanCase['offer']['rate_pct'] ?? $loanCase['rate_pct'] ?? 18.0);
        $tenure = (int) ($loanCase['offer']['tenure_months'] ?? $loanCase['tenure_months'] ?? 12);
        $emi = $this->calculateEmi($principal, $rate, $tenure);
        $schedule = $this->generateSchedulePreview($principal, $rate, $tenure, $emi);

        foreach ($schedule as $row) {
            DB::table('loan_installments')->insert([
                'case_id'        => $caseId,
                'installment_no' => $row['seq'],
                'due_date'       => $row['due_date'],
                'amount'         => $row['amount'],
                'principal_part' => $row['principal_part'],
                'interest_part'  => $row['interest_part'],
                'status'         => 'PENDING',
                'created_at'     => now(),
            ]);
        }
    }

    protected function getLoanStats(): array
    {
        if (Schema::hasTable('loan_cases')) {
            return [
                'total_cases'       => DB::table('loan_cases')->count(),
                'active_loans'      => DB::table('loan_cases')->where('status', 'ACTIVE')->count(),
                'pending_assess'    => DB::table('loan_cases')->whereIn('status', ['APPLIED', 'UNDER_ASSESSMENT'])->count(),
                'awaiting_action'   => DB::table('loan_cases')->whereIn('status', ['OFFERED', 'AWAITING_COLLATERAL', 'AWAITING_SIGNING'])->count(),
                'overdue_cases'     => DB::table('loan_cases')->where('status', 'OVERDUE')->count(),
                'total_disbursed'   => DB::table('loan_cases')->whereIn('status', ['ACTIVE', 'COMPLETED'])->sum('requested_amount'),
            ];
        }

        return [
            'total_cases'     => 0,
            'active_loans'    => 0,
            'pending_assess'  => 0,
            'awaiting_action' => 0,
            'overdue_cases'   => 0,
            'total_disbursed' => 0,
        ];
    }

    protected function queryLoanCases(array $filters): array
    {
        if (Schema::hasTable('loan_cases')) {
            $query = DB::table('loan_cases as lc')
                ->leftJoin('users as u', 'lc.user_id', '=', 'u.id')
                ->select('lc.*', 'u.name as user_name', 'u.email as user_email', 'u.phone as user_phone');

            if (!empty($filters['status']) && $filters['status'] !== 'all') {
                $query->where('lc.status', $filters['status']);
            }
            if (!empty($filters['search'])) {
                $s = $filters['search'];
                $query->where(function ($q) use ($s) {
                    $q->where('lc.case_no', 'like', "%{$s}%")
                      ->orWhere('u.name', 'like', "%{$s}%")
                      ->orWhere('u.phone', 'like', "%{$s}%");
                });
            }

            $paginator = $query->orderByDesc('lc.id')->paginate($filters['per_page']);
            return [
                'current_page' => $paginator->currentPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
                'data'         => $paginator->items(),
            ];
        }

        return ['current_page' => 1, 'total' => 0, 'last_page' => 1, 'data' => []];
    }

    protected function findLoanCase($id): ?array
    {
        if (Schema::hasTable('loan_cases')) {
            $rec = DB::table('loan_cases as lc')
                ->leftJoin('users as u', 'lc.user_id', '=', 'u.id')
                ->select('lc.*', 'u.name as user_name', 'u.email as user_email', 'u.phone as user_phone')
                ->where('lc.id', $id)
                ->first();

            if ($rec) {
                $data = (array) $rec;
                if (!empty($data['offer_data']) && is_string($data['offer_data'])) {
                    $data['offer_data'] = json_decode($data['offer_data'], true);
                }
                $data['installments'] = $this->queryInstallments($id);
                return $data;
            }
        }

        return null;
    }

    protected function queryInstallments($caseId): array
    {
        if (Schema::hasTable('loan_installments')) {
            return DB::table('loan_installments')->where('case_id', $caseId)->orderBy('installment_no')->get()->toArray();
        }
        return [];
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
                Log::info("[AdminAuditLog] action={$action} entity={$type}#{$id}", $details);
            }
        } catch (\Exception $e) {
            Log::warning("[AdminAuditLog Error] " . $e->getMessage());
        }
    }

    protected function errorResponse(Request $request, string $msg, int $code = 400)
    {
        if ($request->expectsJson()) {
            return response()->json(['status' => false, 'message' => $msg], $code);
        }
        if (function_exists('notify')) notify()->error($msg);
        return back()->withInput();
    }
}
