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
 * Class AdminVisaController
 *
 * کنترلر مدیریت پرونده‌های ویزای الکترونیک (e-Visa) و خدمات کنسولی
 * پایش صف متقاضیان، ممیزی مدارک هویتی و مسافرتی، نوبت‌دهی و رهگیری سفارت،
 * صدور روادید دیجیتال و ابلاغ دلایل عدم پذیرش.
 */
class AdminVisaController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:visa-manage', only: ['index', 'show']),
            new Middleware('permission:visa-action', only: ['verifyDocs', 'updateEmbassy', 'issueVisa', 'reject', 'cancel']),
        ];
    }

    /**
     * لیست پرونده‌های ویزا با فیلتر وضعیت، کشور مقصد، نوع ویزا و جستجو
     */
    public function index(Request $request)
    {
        $filters = [
            'status'       => $request->input('status', 'all'),
            'country_code' => $request->input('country_code'),
            'visa_type'    => $request->input('visa_type'),
            'search'       => $request->input('search'),
            'per_page'     => $request->integer('per_page', 15),
        ];

        $stats = $this->getVisaStats();
        $records = $this->queryVisaRequests($filters);

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'status'  => true,
                'message' => 'لیست پرونده‌های ویزا دریافت شد.',
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

        return view('backend.visas.index', [
            'stats'   => $stats,
            'filters' => $filters,
            'records' => $records,
        ]);
    }

    /**
     * مشاهده پرونده کامل ویزا شامل مدارک، مشخصات پاسپورت و یادداشت‌های کنسولی
     */
    public function show(Request $request, $id)
    {
        $visaCase = $this->findVisaRequest($id);

        if (!$visaCase) {
            if ($request->expectsJson()) {
                return response()->json(['status' => false, 'message' => 'پرونده ویزا یافت نشد.'], 404);
            }
            if (function_exists('notify')) notify()->error('پرونده ویزا یافت نشد.');
            return redirect()->route('admin.visas.index');
        }

        if ($request->expectsJson()) {
            return response()->json(['status' => true, 'data' => $visaCase]);
        }

        return view('backend.visas.show', ['visaCase' => $visaCase]);
    }

    /**
     * ممیزی مدارک هویتی و مسافرتی متقاضی (تایید یا درخواست بارگذاری مجدد)
     */
    public function verifyDocs(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'doc_key' => 'required|string',
            'action'  => 'required|in:verified,rejected',
            'note'    => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) return $this->errorResponse($request, $validator->errors()->first());

        try {
            DB::beginTransaction();

            $isVerified = $request->action === 'verified';
            $nextStatus = $isVerified ? 'DOCS_VERIFIED' : 'COMPLEMENT_REQUIRED';

            if (Schema::hasTable('visa_requests')) {
                DB::table('visa_requests')->where('id', $id)->update([
                    'status'            => $nextStatus,
                    'doc_review_status' => $isVerified ? 'VERIFIED' : 'REJECTED',
                    'admin_note'        => $request->note,
                    'updated_at'        => now(),
                ]);
            }

            $this->logActivity('visa_docs_reviewed', $id, 'VisaRequest', $request->all());

            DB::commit();

            $msg = $isVerified ? 'مدارک با موفقیت تایید گردید.' : 'مدرک رد شد و اخطار به مسافر ارسال شد.';
            if ($request->expectsJson()) return response()->json(['status' => true, 'message' => $msg]);

            if (function_exists('notify')) notify()->success($msg);
            return back();

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse($request, $e->getMessage());
        }
    }

    /**
     * بروزرسانی وضعیت سفارت و ثبت کد پیگیری کنسولگری
     */
    public function updateEmbassy(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'embassy_tracking_code' => 'required|string|max:100',
            'appointment_date'      => 'nullable|date',
            'notes'                 => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) return $this->errorResponse($request, $validator->errors()->first());

        try {
            if (Schema::hasTable('visa_requests')) {
                DB::table('visa_requests')->where('id', $id)->update([
                    'status'                => 'SUBMITTED_TO_EMBASSY',
                    'embassy_tracking_code' => $request->embassy_tracking_code,
                    'appointment_date'      => $request->appointment_date,
                    'embassy_note'          => $request->notes,
                    'updated_at'            => now(),
                ]);
            }

            $this->logActivity('visa_embassy_updated', $id, 'VisaRequest', $request->all());

            if ($request->expectsJson()) {
                return response()->json(['status' => true, 'message' => 'وضعیت سفارت بروزرسانی شد.']);
            }

            if (function_exists('notify')) notify()->success('وضعیت سفارت بروزرسانی شد.');
            return back();
        } catch (\Exception $e) {
            return $this->errorResponse($request, $e->getMessage());
        }
    }

    /**
     * صدور نهایی روادید الکترونیک (e-Visa) و تحویل فایل دیجیتال به متقاضی
     */
    public function issueVisa(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'visa_number'     => 'required|string|max:64',
            'valid_from'      => 'required|date',
            'valid_until'     => 'required|date|after:valid_from',
            'entries_allowed' => 'required|in:SINGLE,DOUBLE,MULTIPLE',
            'evisa_file_url'  => 'nullable|string',
        ]);

        if ($validator->fails()) return $this->errorResponse($request, $validator->errors()->first());

        try {
            DB::beginTransaction();

            $issueData = [
                'visa_number'     => $request->visa_number,
                'valid_from'      => $request->valid_from,
                'valid_until'     => $request->valid_until,
                'entries_allowed' => $request->entries_allowed,
                'file_url'        => $request->evisa_file_url,
                'issued_by'       => Auth::id() ?? 1,
                'issued_at'       => now()->toIso8601String(),
            ];

            if (Schema::hasTable('visa_requests')) {
                DB::table('visa_requests')->where('id', $id)->update([
                    'status'     => 'VISA_ISSUED',
                    'visa_number'=> $request->visa_number,
                    'evisa_data' => json_encode($issueData, JSON_UNESCAPED_UNICODE),
                    'issued_at'  => now(),
                    'updated_at' => now(),
                ]);
            }

            $this->logActivity('visa_issued', $id, 'VisaRequest', $issueData);

            DB::commit();

            $msg = 'روادید الکترونیک با موفقیت صادر و فایل آن در کارتابل مسافر فعال گردید.';
            if ($request->expectsJson()) {
                return response()->json(['status' => true, 'message' => $msg, 'data' => ['status' => 'VISA_ISSUED', 'visa' => $issueData]]);
            }

            if (function_exists('notify')) notify()->success($msg);
            return back();

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse($request, $e->getMessage());
        }
    }

    /**
     * رد درخواست ویزا با درج علت کنسولی
     */
    public function reject(Request $request, $id)
    {
        $validator = Validator::make($request->all(), ['reason' => 'required|string|max:1000']);
        if ($validator->fails()) return $this->errorResponse($request, $validator->errors()->first());

        try {
            if (Schema::hasTable('visa_requests')) {
                DB::table('visa_requests')->where('id', $id)->update([
                    'status'     => 'REJECTED',
                    'admin_note' => $request->reason,
                    'updated_at' => now(),
                ]);
            }

            $this->logActivity('visa_rejected', $id, 'VisaRequest', ['reason' => $request->reason]);

            if ($request->expectsJson()) return response()->json(['status' => true, 'message' => 'درخواست ویزا رد شد.']);
            if (function_exists('notify')) notify()->success('درخواست ویزا رد شد.');
            return back();
        } catch (\Exception $e) {
            return $this->errorResponse($request, $e->getMessage());
        }
    }

    /**
     * لغو درخواست ویزا
     */
    public function cancel(Request $request, $id)
    {
        try {
            if (Schema::hasTable('visa_requests')) {
                DB::table('visa_requests')->where('id', $id)->update([
                    'status'     => 'CANCELLED',
                    'updated_at' => now(),
                ]);
            }

            $this->logActivity('visa_cancelled', $id, 'VisaRequest');

            if ($request->expectsJson()) return response()->json(['status' => true, 'message' => 'درخواست لغو شد.']);
            if (function_exists('notify')) notify()->success('درخواست لغو شد.');
            return back();
        } catch (\Exception $e) {
            return $this->errorResponse($request, $e->getMessage());
        }
    }

    // -------------------------------------------------------------
    // متدهای کمکی داخلی
    // -------------------------------------------------------------

    protected function getVisaStats(): array
    {
        if (Schema::hasTable('visa_requests')) {
            return [
                'total_requests' => DB::table('visa_requests')->count(),
                'pending_review' => DB::table('visa_requests')->where('status', 'UNDER_REVIEW')->count(),
                'in_embassy'     => DB::table('visa_requests')->where('status', 'SUBMITTED_TO_EMBASSY')->count(),
                'issued_count'   => DB::table('visa_requests')->where('status', 'VISA_ISSUED')->count(),
                'rejected_count' => DB::table('visa_requests')->where('status', 'REJECTED')->count(),
            ];
        }

        return ['total_requests' => 0, 'pending_review' => 0, 'in_embassy' => 0, 'issued_count' => 0, 'rejected_count' => 0];
    }

    protected function queryVisaRequests(array $filters): array
    {
        if (Schema::hasTable('visa_requests')) {
            $query = DB::table('visa_requests as vr')
                ->leftJoin('users as u', 'vr.user_id', '=', 'u.id')
                ->select('vr.*', 'u.name as applicant_name', 'u.email as applicant_email');

            if (!empty($filters['status']) && $filters['status'] !== 'all') {
                $query->where('vr.status', $filters['status']);
            }
            if (!empty($filters['country_code'])) {
                $query->where('vr.country_code', $filters['country_code']);
            }
            if (!empty($filters['search'])) {
                $s = $filters['search'];
                $query->where(function ($q) use ($s) {
                    $q->where('vr.case_no', 'like', "%{$s}%")
                      ->orWhere('vr.passport_number', 'like', "%{$s}%")
                      ->orWhere('u.name', 'like', "%{$s}%");
                });
            }

            $paginator = $query->orderByDesc('vr.id')->paginate($filters['per_page']);
            return [
                'current_page' => $paginator->currentPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
                'data'         => $paginator->items(),
            ];
        }

        return ['current_page' => 1, 'total' => 0, 'last_page' => 1, 'data' => []];
    }

    protected function findVisaRequest($id): ?array
    {
        if (Schema::hasTable('visa_requests')) {
            $rec = DB::table('visa_requests as vr')
                ->leftJoin('users as u', 'vr.user_id', '=', 'u.id')
                ->select('vr.*', 'u.name as applicant_name', 'u.email as applicant_email', 'u.phone as applicant_phone')
                ->where('vr.id', $id)
                ->first();

            if ($rec) {
                $data = (array) $rec;
                if (!empty($data['applicant_info']) && is_string($data['applicant_info'])) {
                    $data['applicant_info'] = json_decode($data['applicant_info'], true);
                }
                if (!empty($data['evisa_data']) && is_string($data['evisa_data'])) {
                    $data['evisa_data'] = json_decode($data['evisa_data'], true);
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
