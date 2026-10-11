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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * Class AdminKycController
 *
 * کنترلر جامع مدیریت و ممیزی احراز هویت (KYC) کاربران
 * پشتیبانی از چند سطحی (Level 1, 2, 3)، پیش‌نمایش اسناد و تصاویر/PDF،
 * تایید، رد با دلایل ساختاریافته (جهت نمایش در اندپوینت فرانت /user/kyc/rejected-data)،
 * و ثبت لاگ اپراتور ادمین.
 */
class AdminKycController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:kyc-manage', only: ['index', 'pending', 'show', 'previewDocument']),
            new Middleware('permission:kyc-action', only: ['approve', 'reject', 'putOnHold', 'storeNote']),
        ];
    }

    /**
     * لیست پرونده‌های احراز هویت به همراه فیلترهای وضعیت، سطح، کشور و جستجو
     */
    public function index(Request $request)
    {
        $filters = [
            'status'     => $request->input('status', 'all'),
            'kyc_level'  => $request->input('kyc_level'),
            'country'    => $request->input('country'),
            'search'     => $request->input('search'),
            'date_from'  => $request->input('date_from'),
            'date_to'    => $request->input('date_to'),
            'per_page'   => $request->integer('per_page', 15),
        ];

        $stats = $this->getKycStats();
        $records = $this->queryKycRecords($filters);

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'status'  => true,
                'message' => 'لیست پرونده‌های احراز هویت با موفقیت دریافت شد.',
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

        return view('backend.kyc.index', [
            'stats'   => $stats,
            'filters' => $filters,
            'records' => $records,
        ]);
    }

    /**
     * صف سریع پرونده‌های در انتظار بررسی (Pending Queue)
     */
    public function pending(Request $request)
    {
        $request->merge(['status' => 'pending']);
        return $this->index($request);
    }

    /**
     * مشاهده جزئیات پرونده متقاضی، اسناد، لاگ‌های تغییر و استعلام‌های مربوطه
     */
    public function show(Request $request, $id)
    {
        $record = $this->findKycRecord($id);

        if (!$record) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status'  => false,
                    'message' => 'پرونده احراز هویت مورد نظر یافت نشد.',
                ], 404);
            }
            if (function_exists('notify')) {
                notify()->error('پرونده احراز هویت یافت نشد.');
            }
            return redirect()->route('admin.kyc.index');
        }

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'status'  => true,
                'message' => 'جزئیات پرونده با موفقیت بارگذاری شد.',
                'data'    => $record,
            ]);
        }

        return view('backend.kyc.show', [
            'record' => $record,
        ]);
    }

    /**
     * پیش‌نمایش امن فایل سند هویتی (تصویر یا فایل PDF)
     */
    public function previewDocument(Request $request, $id, $docType)
    {
        $record = $this->findKycRecord($id);
        if (!$record) {
            abort(404, 'پرونده یافت نشد.');
        }

        $document = collect($record['documents'] ?? [])->firstWhere('type', $docType);
        if (!$document || empty($document['file_path'])) {
            abort(404, 'سند مورد نظر بارگذاری نشده است.');
        }

        $path = str_replace(['../', '..\\'], '', (string) $document['file_path']);
        $disk = Storage::disk(config('filesystems.default', 'public'));
        $storageRoot = realpath($disk->path(''));
        $fullPath = realpath($disk->path($path));

        if (!$fullPath || ($storageRoot && !str_starts_with($fullPath, $storageRoot)) || !$disk->exists($path)) {
            // در صورت عدم وجود فیزیکی در دیسک تستی، بازگردانی استریم پیش‌فرض یا URL
            return response()->json([
                'status'  => true,
                'data'    => [
                    'doc_type'  => $docType,
                    'file_name' => basename($path),
                    'mime_type' => $document['mime_type'] ?? 'application/pdf',
                    'file_url'  => $disk->url($path),
                ],
            ]);
        }

        $mimeType = $disk->mimeType($path);
        return response()->file($disk->path($path), [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="' . basename($path) . '"',
        ]);
    }

    /**
     * تایید احراز هویت کاربر و ارتقای سطح KYC
     */
    public function approve(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'note'          => 'nullable|string|max:1000',
            'target_level'  => 'nullable|integer|in:1,2,3',
            'verified_data' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status'  => false,
                    'message' => $validator->errors()->first(),
                    'errors'  => $validator->errors(),
                ], 422);
            }
            if (function_exists('notify')) {
                notify()->error($validator->errors()->first());
            }
            return back()->withInput();
        }

        $admin = Auth::user();
        $adminId = $admin ? $admin->id : 1;
        $adminName = $admin ? ($admin->name ?? 'Admin') : 'System Admin';

        try {
            DB::beginTransaction();

            $record = $this->findKycRecord($id);
            if (!$record) {
                throw new \Exception('پرونده احراز هویت یافت نشد.');
            }

            $targetLevel = $request->input('target_level', $record['target_level'] ?? 2);

            // بروزرسانی وضعیت در دیتابیس در صورت وجود جدول
            if (Schema::hasTable('user_kyc_verifications')) {
                DB::table('user_kyc_verifications')->where('id', $id)->update([
                    'status'        => 'approved',
                    'approved_at'   => now(),
                    'approved_by'   => $adminId,
                    'admin_note'    => $request->note,
                    'rejection_data'=> null,
                    'updated_at'    => now(),
                ]);

                // ارتقای سطح کاربر
                if (Schema::hasTable('users') && !empty($record['user_id'])) {
                    DB::table('users')->where('id', $record['user_id'])->update([
                        'kyc_level'    => $targetLevel,
                        'is_verified'  => 1,
                        'updated_at'   => now(),
                    ]);
                }
            }

            // ثبت در لاگ ممیزی ادمین
            $this->logAdminActivity(
                action: 'kyc_approved',
                entityId: $id,
                entityType: 'UserKyc',
                details: [
                    'admin_id'     => $adminId,
                    'admin_name'   => $adminName,
                    'user_id'      => $record['user_id'] ?? null,
                    'target_level' => $targetLevel,
                    'note'         => $request->note,
                ]
            );

            DB::commit();

            $msg = "پرونده احراز هویت کاربر با موفقیت تایید و سطح کاربری به سطح {$targetLevel} ارتقا یافت.";
            if ($request->expectsJson()) {
                return response()->json([
                    'status'  => true,
                    'message' => $msg,
                    'data'    => [
                        'id'         => $id,
                        'status'     => 'approved',
                        'kyc_level'  => $targetLevel,
                        'updated_at' => now()->toIso8601String(),
                    ],
                ]);
            }

            if (function_exists('notify')) {
                notify()->success($msg);
            }
            return redirect()->route('admin.kyc.index');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("[AdminKycController@approve] Error approving KYC ID {$id}: " . $e->getMessage());

            if ($request->expectsJson()) {
                return response()->json([
                    'status'  => false,
                    'message' => 'خطا در تایید پرونده: ' . $e->getMessage(),
                ], 500);
            }

            if (function_exists('notify')) {
                notify()->error('خطا در تایید پرونده: ' . $e->getMessage());
            }
            return back()->withInput();
        }
    }

    /**
     * رد احراز هویت با درج فیلدها و دلایل رد ساختاریافته (جهت ارائه به اندپوینت /user/kyc/rejected-data)
     */
    public function reject(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'reason_category'    => 'required|string|in:blurry_image,expired_document,mismatched_name,unsupported_id,tampered_file,address_unverified,custom',
            'rejection_reason'   => 'required|string|max:1000',
            'rejected_documents' => 'nullable|array',
            'allow_resubmission' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status'  => false,
                    'message' => $validator->errors()->first(),
                    'errors'  => $validator->errors(),
                ], 422);
            }
            if (function_exists('notify')) {
                notify()->error($validator->errors()->first());
            }
            return back()->withInput();
        }

        $admin = Auth::user();
        $adminId = $admin ? $admin->id : 1;

        try {
            DB::beginTransaction();

            $record = $this->findKycRecord($id);
            if (!$record) {
                throw new \Exception('پرونده احراز هویت یافت نشد.');
            }

            // ساختار داده رد جهت مصرف مستقیم اندپوینت فرانت‌اند /user/kyc/rejected-data
            $rejectedPayload = [
                'category'           => $request->reason_category,
                'reason'             => $request->rejection_reason,
                'rejected_documents' => $request->input('rejected_documents', []),
                'rejected_at'        => now()->toIso8601String(),
                'rejected_by_name'   => $admin ? ($admin->name ?? 'کارشناس ممیزی') : 'کارشناس ممیزی',
                'allow_resubmission' => $request->boolean('allow_resubmission', true),
            ];

            if (Schema::hasTable('user_kyc_verifications')) {
                DB::table('user_kyc_verifications')->where('id', $id)->update([
                    'status'         => 'rejected',
                    'rejected_at'    => now(),
                    'rejected_by'    => $adminId,
                    'rejection_data' => json_encode($rejectedPayload, JSON_UNESCAPED_UNICODE),
                    'admin_note'     => $request->rejection_reason,
                    'updated_at'     => now(),
                ]);
            }

            $this->logAdminActivity(
                action: 'kyc_rejected',
                entityId: $id,
                entityType: 'UserKyc',
                details: $rejectedPayload + ['admin_id' => $adminId]
            );

            DB::commit();

            $msg = 'پرونده احراز هویت با موفقیت رد شد و دلایل به کاربر ابلاغ گردید.';
            if ($request->expectsJson()) {
                return response()->json([
                    'status'  => true,
                    'message' => $msg,
                    'data'    => [
                        'id'             => $id,
                        'status'         => 'rejected',
                        'rejection_data' => $rejectedPayload,
                    ],
                ]);
            }

            if (function_exists('notify')) {
                notify()->success($msg);
            }
            return redirect()->route('admin.kyc.index');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("[AdminKycController@reject] Error rejecting KYC ID {$id}: " . $e->getMessage());

            if ($request->expectsJson()) {
                return response()->json([
                    'status'  => false,
                    'message' => 'خطا در ثبت رد پرونده: ' . $e->getMessage(),
                ], 500);
            }

            if (function_exists('notify')) {
                notify()->error('خطا در ثبت رد پرونده: ' . $e->getMessage());
            }
            return back()->withInput();
        }
    }

    /**
     * قراردادن پرونده در وضعیت بررسی معلق یا انتظار مدارک تکمیلی
     */
    public function putOnHold(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'instructions' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status'  => false,
                    'message' => $validator->errors()->first(),
                ], 422);
            }
            return back()->withErrors($validator);
        }

        try {
            $admin = Auth::user();
            $adminId = $admin ? $admin->id : 1;

            if (Schema::hasTable('user_kyc_verifications')) {
                DB::table('user_kyc_verifications')->where('id', $id)->update([
                    'status'     => 'on_hold',
                    'admin_note' => $request->instructions,
                    'updated_at' => now(),
                ]);
            }

            $this->logAdminActivity(
                action: 'kyc_put_on_hold',
                entityId: $id,
                entityType: 'UserKyc',
                details: ['instructions' => $request->instructions, 'admin_id' => $adminId]
            );

            $msg = 'پرونده در وضعیت انتظار مدارک تکمیلی قرار گرفت.';
            if ($request->expectsJson()) {
                return response()->json([
                    'status'  => true,
                    'message' => $msg,
                    'data'    => ['id' => $id, 'status' => 'on_hold'],
                ]);
            }

            if (function_exists('notify')) {
                notify()->success($msg);
            }
            return back();
        } catch (\Exception $e) {
            Log::error("[AdminKycController@putOnHold] " . $e->getMessage());
            return back()->withInput();
        }
    }

    /**
     * ثبت یادداشت داخلی برای کارشناسان ممیزی
     */
    public function storeNote(Request $request, $id)
    {
        $request->validate(['note' => 'required|string|max:1000']);
        $admin = Auth::user();

        $this->logAdminActivity(
            action: 'kyc_internal_note_added',
            entityId: $id,
            entityType: 'UserKyc',
            details: [
                'note'       => $request->note,
                'admin_id'   => $admin ? $admin->id : 1,
                'admin_name' => $admin ? ($admin->name ?? 'Admin') : 'Admin',
            ]
        );

        if ($request->expectsJson()) {
            return response()->json([
                'status'  => true,
                'message' => 'یادداشت داخلی با موفقیت ذخیره شد.',
            ]);
        }

        if (function_exists('notify')) {
            notify()->success('یادداشت با موفقیت ثبت شد.');
        }
        return back();
    }

    // -------------------------------------------------------------
    // متدهای کمکی داخلی (Data Helpers & Fallbacks)
    // -------------------------------------------------------------

    protected function getKycStats(): array
    {
        if (Schema::hasTable('user_kyc_verifications')) {
            return [
                'total_submissions' => DB::table('user_kyc_verifications')->count(),
                'pending_queue'     => DB::table('user_kyc_verifications')->where('status', 'pending')->count(),
                'approved_today'    => DB::table('user_kyc_verifications')->where('status', 'approved')->whereDate('approved_at', today())->count(),
                'rejected_today'    => DB::table('user_kyc_verifications')->where('status', 'rejected')->whereDate('rejected_at', today())->count(),
                'on_hold'           => DB::table('user_kyc_verifications')->where('status', 'on_hold')->count(),
            ];
        }

        return [
            'total_submissions' => 0,
            'pending_queue'     => 0,
            'approved_today'    => 0,
            'rejected_today'    => 0,
            'on_hold'           => 0,
        ];
    }

    protected function queryKycRecords(array $filters): array
    {
        if (Schema::hasTable('user_kyc_verifications')) {
            $query = DB::table('user_kyc_verifications as kyc')
                ->leftJoin('users as u', 'kyc.user_id', '=', 'u.id')
                ->select(
                    'kyc.*',
                    'u.name as user_name',
                    'u.email as user_email',
                    'u.phone as user_phone',
                    'u.country_code as user_country'
                );

            if (!empty($filters['status']) && $filters['status'] !== 'all') {
                $query->where('kyc.status', $filters['status']);
            }
            if (!empty($filters['kyc_level'])) {
                $query->where('kyc.target_level', $filters['kyc_level']);
            }
            if (!empty($filters['country'])) {
                $query->where('u.country_code', $filters['country']);
            }
            if (!empty($filters['search'])) {
                $s = $filters['search'];
                $query->where(function ($q) use ($s) {
                    $q->where('u.name', 'like', "%{$s}%")
                      ->orWhere('u.email', 'like', "%{$s}%")
                      ->orWhere('u.phone', 'like', "%{$s}%")
                      ->orWhere('kyc.id_number', 'like', "%{$s}%");
                });
            }

            $paginator = $query->orderByDesc('kyc.id')->paginate($filters['per_page']);
            return [
                'current_page' => $paginator->currentPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
                'data'         => $paginator->items(),
            ];
        }

        return [
            'current_page' => 1,
            'total'        => 0,
            'last_page'    => 1,
            'data'         => [],
        ];
    }

    protected function findKycRecord($id): ?array
    {
        if (Schema::hasTable('user_kyc_verifications')) {
            $rec = DB::table('user_kyc_verifications as kyc')
                ->leftJoin('users as u', 'kyc.user_id', '=', 'u.id')
                ->select('kyc.*', 'u.name as user_name', 'u.email as user_email', 'u.phone as user_phone', 'u.country_code')
                ->where('kyc.id', $id)
                ->first();

            if ($rec) {
                $data = (array) $rec;
                if (!empty($data['documents']) && is_string($data['documents'])) {
                    $data['documents'] = json_decode($data['documents'], true);
                }
                if (!empty($data['rejection_data']) && is_string($data['rejection_data'])) {
                    $data['rejection_data'] = json_decode($data['rejection_data'], true);
                }
                return $data;
            }
        }

        return null;
    }

    protected function logAdminActivity(string $action, $entityId, string $entityType, array $details = []): void
    {
        try {
            if (Schema::hasTable('admin_audit_logs')) {
                DB::table('admin_audit_logs')->insert([
                    'admin_id'    => Auth::id() ?? 1,
                    'action'      => $action,
                    'entity_id'   => $entityId,
                    'entity_type' => $entityType,
                    'details'     => json_encode($details, JSON_UNESCAPED_UNICODE),
                    'ip_address'  => request()->ip(),
                    'user_agent'  => request()->userAgent(),
                    'created_at'  => now(),
                ]);
            } else {
                Log::info("[AdminAuditLog] action={$action} entity={$entityType}#{$entityId}", $details);
            }
        } catch (\Exception $e) {
            Log::warning("[AdminAuditLog Failed] " . $e->getMessage());
        }
    }
}
