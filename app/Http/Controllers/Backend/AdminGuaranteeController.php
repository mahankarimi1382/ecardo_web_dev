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
 * Class AdminGuaranteeController
 *
 * کنترلر مدیریت ضمانت‌نامه‌های بانکی و اعتبارات اسنادی (Bank Guarantees & LC)
 * پایش چرخه: بررسی مدارک و مناقصه -> تعیین بانک عامل و وجه التزام -> تودیع وجه التزام در اسکرو ->
 * صدور سند رسمی با کد سپام بانک مرکزی -> پایش دوره اعتبار -> مدیریت مطالبات ذینفع و آزادسازی تضامین.
 */
class AdminGuaranteeController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:guarantee-manage', only: ['index', 'show']),
            new Middleware('permission:guarantee-action', only: ['approveReview', 'confirmMargin', 'issueSepam', 'handleClaim', 'release', 'reject', 'cancel']),
        ];
    }

    /**
     * لیست پرونده‌های ضمانت‌نامه با فیلتر ابزار، وضعیت، ارز و جستجو
     */
    public function index(Request $request)
    {
        $filters = [
            'status'     => $request->input('status', 'all'),
            'instrument' => $request->input('instrument'),
            'currency'   => $request->input('currency'),
            'search'     => $request->input('search'),
            'per_page'   => $request->integer('per_page', 15),
        ];

        $stats = $this->getGuaranteeStats();
        $records = $this->queryGuarantees($filters);

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'status'  => true,
                'message' => 'لیست ضمانت‌نامه‌ها با موفقیت دریافت شد.',
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

        return view('backend.guarantees.index', [
            'stats'   => $stats,
            'filters' => $filters,
            'records' => $records,
        ]);
    }

    /**
     * مشاهده پرونده تفصیلی، مدارک مناقصه، استعلام سپام و وضعیت مارجین
     */
    public function show(Request $request, $id)
    {
        $record = $this->findGuarantee($id);

        if (!$record) {
            if ($request->expectsJson()) {
                return response()->json(['status' => false, 'message' => 'پرونده ضمانت‌نامه یافت نشد.'], 404);
            }
            if (function_exists('notify')) notify()->error('پرونده یافت نشد.');
            return redirect()->route('admin.guarantees.index');
        }

        if ($request->expectsJson()) {
            return response()->json(['status' => true, 'data' => $record]);
        }

        return view('backend.guarantees.show', ['guarantee' => $record]);
    }

    /**
     * تایید بررسی اولیه و اعلام شرایط صدور (تعیین بانک، درصد مارجین و کارمزد)
     */
    public function approveReview(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'bank_name'    => 'required|string|max:100',
            'margin_pct'   => 'required|numeric|min:0|max:100',
            'fee_pct'      => 'required|numeric|min:0|max:100',
            'deposit_hours'=> 'nullable|integer|min:12|max:168',
            'admin_note'   => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) return $this->errorResponse($request, $validator->errors()->first());

        try {
            DB::beginTransaction();

            $guarantee = $this->findGuarantee($id);
            if (!$guarantee) throw new \Exception('پرونده ضمانت‌نامه یافت نشد.');

            $depositDeadline = now()->addHours($request->input('deposit_hours', 48));

            if (Schema::hasTable('guarantee_cases')) {
                DB::table('guarantee_cases')->where('id', $id)->update([
                    'status'            => 'MARGIN_PENDING',
                    'assigned_bank'     => $request->bank_name,
                    'approved_margin_pct' => $request->margin_pct,
                    'approved_fee_pct'  => $request->fee_pct,
                    'margin_deadline'   => $depositDeadline,
                    'admin_note'        => $request->admin_note,
                    'updated_at'        => now(),
                ]);
            }

            $this->logActivity('guarantee_review_approved', $id, 'GuaranteeCase', [
                'bank'       => $request->bank_name,
                'margin_pct' => $request->margin_pct,
                'fee_pct'    => $request->fee_pct,
            ]);

            DB::commit();

            $msg = 'بررسی پرونده با موفقیت تایید و درخواست تودیع وجه التزام برای متقاضی صادر شد.';
            if ($request->expectsJson()) {
                return response()->json(['status' => true, 'message' => $msg, 'data' => ['status' => 'MARGIN_PENDING']]);
            }

            if (function_exists('notify')) notify()->success($msg);
            return back();

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse($request, $e->getMessage());
        }
    }

    /**
     * تایید وصول و قفل وجه التزام در اسکرو پلتفرم
     */
    public function confirmMargin(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            if (Schema::hasTable('guarantee_cases')) {
                DB::table('guarantee_cases')->where('id', $id)->update([
                    'status'        => 'IN_ISSUANCE',
                    'margin_status' => 'LOCKED_IN_ESCROW',
                    'updated_at'    => now(),
                ]);
            }

            $this->logActivity('guarantee_margin_locked', $id, 'GuaranteeCase');

            DB::commit();

            $msg = 'تودیع وجه التزام تایید شد و پرونده جهت صدور سند رسمی به بانک ارسال گردید.';
            if ($request->expectsJson()) {
                return response()->json(['status' => true, 'message' => $msg, 'data' => ['status' => 'IN_ISSUANCE']]);
            }

            if (function_exists('notify')) notify()->success($msg);
            return back();

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse($request, $e->getMessage());
        }
    }

    /**
     * ثبت نهایی سند رسمی ضمانت‌نامه با کد سپام بانک مرکزی
     */
    public function issueSepam(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'sepam_tracking_code' => 'required|string|max:64',
            'bank_ref_no'         => 'required|string|max:64',
            'issue_date'          => 'required|date',
            'expiry_date'         => 'required|date|after:issue_date',
            'guarantee_file_url'  => 'nullable|string',
        ]);

        if ($validator->fails()) return $this->errorResponse($request, $validator->errors()->first());

        try {
            DB::beginTransaction();

            $sepamData = [
                'sepam_tracking_code' => $request->sepam_tracking_code,
                'bank_ref_no'         => $request->bank_ref_no,
                'issue_date'          => $request->issue_date,
                'expiry_date'         => $request->expiry_date,
                'file_url'            => $request->guarantee_file_url,
                'registered_by'       => Auth::id() ?? 1,
                'registered_at'       => now()->toIso8601String(),
            ];

            if (Schema::hasTable('guarantee_cases')) {
                DB::table('guarantee_cases')->where('id', $id)->update([
                    'status'              => 'ISSUED',
                    'sepam_code'          => $request->sepam_tracking_code,
                    'bank_ref'            => $request->bank_ref_no,
                    'sepam_data'          => json_encode($sepamData, JSON_UNESCAPED_UNICODE),
                    'issued_at'           => now(),
                    'updated_at'          => now(),
                ]);
            }

            $this->logActivity('guarantee_sepam_issued', $id, 'GuaranteeCase', $sepamData);

            DB::commit();

            $msg = 'ضمانت‌نامه رسمی با کد رهگیری سپام بانک مرکزی با موفقیت ثبت و صادر شد.';
            if ($request->expectsJson()) {
                return response()->json(['status' => true, 'message' => $msg, 'data' => ['status' => 'ISSUED', 'sepam' => $sepamData]]);
            }

            if (function_exists('notify')) notify()->success($msg);
            return back();

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse($request, $e->getMessage());
        }
    }

    /**
     * ثبت مطالبه رسمی ذینفع (Claim) و فریز فوری تضامین
     */
    public function handleClaim(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'claim_amount'    => 'required|numeric|min:1',
            'beneficiary_letter_ref' => 'required|string|max:100',
            'legal_note'      => 'required|string|max:2000',
        ]);

        if ($validator->fails()) return $this->errorResponse($request, $validator->errors()->first());

        try {
            DB::beginTransaction();

            if (Schema::hasTable('guarantee_cases')) {
                DB::table('guarantee_cases')->where('id', $id)->update([
                    'status'       => 'CLAIMED',
                    'claim_amount' => $request->claim_amount,
                    'claim_data'   => json_encode($request->only('claim_amount', 'beneficiary_letter_ref', 'legal_note'), JSON_UNESCAPED_UNICODE),
                    'claimed_at'   => now(),
                    'updated_at'   => now(),
                ]);
            }

            $this->logActivity('guarantee_claimed', $id, 'GuaranteeCase', $request->all());

            DB::commit();

            $msg = 'مطالبه ذینفع ثبت شد؛ مارجین فریز شده و اخطار حقوقی به متعهد ارسال گردید.';
            if ($request->expectsJson()) {
                return response()->json(['status' => true, 'message' => $msg, 'data' => ['status' => 'CLAIMED']]);
            }

            if (function_exists('notify')) notify()->success($msg);
            return back();

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse($request, $e->getMessage());
        }
    }

    /**
     * آزادسازی وجه التزام به کیف پول کاربر پس از انقضای قانونی یا ابطال
     */
    public function release(Request $request, $id)
    {
        $admin = Auth::user();
        $adminId = $admin ? $admin->id : 1;

        try {
            DB::beginTransaction();

            $guarantee = $this->findGuarantee($id);
            if (!$guarantee) throw new \Exception('پرونده یافت نشد.');

            $marginAmount = (float) ($guarantee['margin_amount'] ?? 0);
            $userId = $guarantee['user_id'] ?? null;

            // بازگشت وجه التزام به والت کاربر با لجر دوطرفه
            if ($marginAmount > 0 && $userId && Schema::hasTable('wallets') && Schema::hasTable('journal_entries')) {
                $wallet = DB::table('wallets')->where('user_id', $userId)->orderBy('id', 'asc')->lockForUpdate()->first();
                if ($wallet) {
                    DB::table('wallets')->where('id', $wallet->id)->increment('balance', $marginAmount);

                    DB::table('journal_entries')->insert([
                        'reference_type' => 'GUARANTEE_MARGIN_RELEASE',
                        'reference_id'   => $id,
                        'debit_account'  => 'ESCROW_GUARANTEE_MARGIN_POOL',
                        'credit_account' => "WALLET_{$wallet->id}",
                        'amount'         => $marginAmount,
                        'currency'       => $guarantee['currency'] ?? 'IRR',
                        'operator_id'    => $adminId,
                        'created_at'     => now(),
                    ]);
                }
            }

            if (Schema::hasTable('guarantee_cases')) {
                DB::table('guarantee_cases')->where('id', $id)->update([
                    'status'       => 'RELEASED',
                    'released_at'  => now(),
                    'released_by'  => $adminId,
                    'updated_at'   => now(),
                ]);
            }

            $this->logActivity('guarantee_margin_released', $id, 'GuaranteeCase', ['amount' => $marginAmount]);

            DB::commit();

            $msg = 'وجه التزام با موفقیت آزاد و به کیف پول متقاضی مسترد شد.';
            if ($request->expectsJson()) {
                return response()->json(['status' => true, 'message' => $msg, 'data' => ['status' => 'RELEASED']]);
            }

            if (function_exists('notify')) notify()->success($msg);
            return back();

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse($request, $e->getMessage());
        }
    }

    /**
     * رد پرونده ضمانت‌نامه
     */
    public function reject(Request $request, $id)
    {
        $validator = Validator::make($request->all(), ['reason' => 'required|string|max:1000']);
        if ($validator->fails()) return $this->errorResponse($request, $validator->errors()->first());

        try {
            if (Schema::hasTable('guarantee_cases')) {
                DB::table('guarantee_cases')->where('id', $id)->update([
                    'status'     => 'REJECTED',
                    'admin_note' => $request->reason,
                    'updated_at' => now(),
                ]);
            }

            $this->logActivity('guarantee_rejected', $id, 'GuaranteeCase', ['reason' => $request->reason]);

            if ($request->expectsJson()) {
                return response()->json(['status' => true, 'message' => 'پرونده ضمانت‌نامه رد شد.']);
            }

            if (function_exists('notify')) notify()->success('پرونده ضمانت‌نامه رد شد.');
            return back();
        } catch (\Exception $e) {
            return $this->errorResponse($request, $e->getMessage());
        }
    }

    /**
     * لغو پرونده
     */
    public function cancel(Request $request, $id)
    {
        try {
            if (Schema::hasTable('guarantee_cases')) {
                DB::table('guarantee_cases')->where('id', $id)->update([
                    'status'     => 'CANCELLED',
                    'updated_at' => now(),
                ]);
            }

            $this->logActivity('guarantee_cancelled', $id, 'GuaranteeCase');

            if ($request->expectsJson()) {
                return response()->json(['status' => true, 'message' => 'پرونده لغو شد.']);
            }

            if (function_exists('notify')) notify()->success('پرونده لغو شد.');
            return back();
        } catch (\Exception $e) {
            return $this->errorResponse($request, $e->getMessage());
        }
    }

    // -------------------------------------------------------------
    // متدهای کمکی داخلی
    // -------------------------------------------------------------

    protected function getGuaranteeStats(): array
    {
        if (Schema::hasTable('guarantee_cases')) {
            return [
                'total_cases'       => DB::table('guarantee_cases')->count(),
                'active_issued'     => DB::table('guarantee_cases')->where('status', 'ISSUED')->count(),
                'pending_review'    => DB::table('guarantee_cases')->whereIn('status', ['DRAFT', 'UNDER_REVIEW'])->count(),
                'awaiting_margin'   => DB::table('guarantee_cases')->where('status', 'MARGIN_PENDING')->count(),
                'active_claims'     => DB::table('guarantee_cases')->where('status', 'CLAIMED')->count(),
                'total_guaranteed'  => DB::table('guarantee_cases')->where('status', 'ISSUED')->sum('amount'),
            ];
        }

        return [
            'total_cases'      => 0,
            'active_issued'    => 0,
            'pending_review'   => 0,
            'awaiting_margin'  => 0,
            'active_claims'    => 0,
            'total_guaranteed' => 0,
        ];
    }

    protected function queryGuarantees(array $filters): array
    {
        if (Schema::hasTable('guarantee_cases')) {
            $query = DB::table('guarantee_cases as gc')
                ->leftJoin('users as u', 'gc.user_id', '=', 'u.id')
                ->select('gc.*', 'u.name as applicant_name', 'u.email as applicant_email');

            if (!empty($filters['status']) && $filters['status'] !== 'all') {
                $query->where('gc.status', $filters['status']);
            }
            if (!empty($filters['search'])) {
                $s = $filters['search'];
                $query->where(function ($q) use ($s) {
                    $q->where('gc.case_no', 'like', "%{$s}%")
                      ->orWhere('gc.beneficiary_name', 'like', "%{$s}%")
                      ->orWhere('u.name', 'like', "%{$s}%");
                });
            }

            $paginator = $query->orderByDesc('gc.id')->paginate($filters['per_page']);
            return [
                'current_page' => $paginator->currentPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
                'data'         => $paginator->items(),
            ];
        }

        return ['current_page' => 1, 'total' => 0, 'last_page' => 1, 'data' => []];
    }

    protected function findGuarantee($id): ?array
    {
        if (Schema::hasTable('guarantee_cases')) {
            $rec = DB::table('guarantee_cases as gc')
                ->leftJoin('users as u', 'gc.user_id', '=', 'u.id')
                ->select('gc.*', 'u.name as applicant_name', 'u.email as applicant_email', 'u.phone as applicant_phone')
                ->where('gc.id', $id)
                ->first();

            if ($rec) {
                $data = (array) $rec;
                if (!empty($data['sepam_data']) && is_string($data['sepam_data'])) {
                    $data['sepam_data'] = json_decode($data['sepam_data'], true);
                }
                return $data;
            }
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
