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
 * Class AdminTourController
 *
 * کنترلر مدیریت بسته‌های تور، ظرفیت سفرها و رزرواسیون مسافران (Tours & Travel Bookings)
 * پشتیبانی از مدیریت برنامه سفر و پکیج‌ها، لیست مسافران، صدور ووچر و استرداد/ابطال بلیط با لجر والت.
 */
class AdminTourController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:tour-manage', only: ['index', 'show', 'bookings', 'showBooking']),
            new Middleware('permission:tour-action', only: ['store', 'update', 'delete', 'confirmBooking', 'cancelAndRefundBooking']),
        ];
    }

    /**
     * لیست تورها و بسته‌های سفر با فیلتر مقصد، وضعیت، تاریخ و ظرفیت
     */
    public function index(Request $request)
    {
        $filters = [
            'status'      => $request->input('status', 'all'),
            'destination' => $request->input('destination'),
            'search'      => $request->input('search'),
            'per_page'    => $request->integer('per_page', 15),
        ];

        $stats = $this->getTourStats();
        $records = $this->queryTours($filters);

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'status'  => true,
                'message' => 'لیست تورها دریافت شد.',
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

        return view('backend.tours.index', [
            'stats'   => $stats,
            'filters' => $filters,
            'records' => $records,
        ]);
    }

    /**
     * ایجاد تور جدید
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title'          => 'required|string|max:200',
            'destination'    => 'required|string|max:100',
            'origin'         => 'required|string|max:100',
            'start_date'     => 'required|date',
            'end_date'       => 'required|date|after:start_date',
            'capacity'       => 'required|integer|min:1',
            'price_adult'    => 'required|numeric|min:0',
            'currency'       => 'required|string|size:3',
            'itinerary'      => 'nullable|string',
            'hotel_name'     => 'nullable|string|max:150',
            'transport_type' => 'nullable|string|in:FLIGHT,TRAIN,BUS,CRUISE',
        ]);

        if ($validator->fails()) return $this->errorResponse($request, $validator->errors()->first());

        try {
            DB::beginTransaction();

            $id = 1;
            if (Schema::hasTable('travel_tours')) {
                $id = DB::table('travel_tours')->insertGetId([
                    'title'          => $request->title,
                    'destination'    => $request->destination,
                    'origin'         => $request->origin,
                    'start_date'     => $request->start_date,
                    'end_date'       => $request->end_date,
                    'capacity'       => $request->capacity,
                    'booked_seats'   => 0,
                    'price_adult'    => $request->price_adult,
                    'currency'       => $request->currency,
                    'itinerary'      => $request->itinerary,
                    'hotel_name'     => $request->hotel_name,
                    'transport_type' => $request->transport_type,
                    'status'         => 'ACTIVE',
                    'created_by'     => Auth::id() ?? 1,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
            }

            $this->logActivity('tour_created', $id, 'TravelTour', $request->all());

            DB::commit();

            $msg = 'بسته تور جدید با موفقیت ایجاد و منتشر شد.';
            if ($request->expectsJson()) {
                return response()->json(['status' => true, 'message' => $msg, 'data' => ['id' => $id]]);
            }

            if (function_exists('notify')) notify()->success($msg);
            return redirect()->route('admin.tours.index');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse($request, $e->getMessage());
        }
    }

    /**
     * مشاهده جزئیات بسته تور و فهرست مسافران رزرو شده
     */
    public function show(Request $request, $id)
    {
        $tour = $this->findTour($id);

        if (!$tour) {
            if ($request->expectsJson()) return response()->json(['status' => false, 'message' => 'تور یافت نشد.'], 404);
            if (function_exists('notify')) notify()->error('تور یافت نشد.');
            return redirect()->route('admin.tours.index');
        }

        $passengers = $this->queryTourPassengers($id);

        if ($request->expectsJson()) {
            return response()->json([
                'status' => true,
                'data'   => ['tour' => $tour, 'passengers' => $passengers],
            ]);
        }

        return view('backend.tours.show', ['tour' => $tour, 'passengers' => $passengers]);
    }

    /**
     * ویرایش اطلاعات تور
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'title'       => 'required|string|max:200',
            'capacity'    => 'required|integer|min:1',
            'price_adult' => 'required|numeric|min:0',
            'status'      => 'required|in:ACTIVE,COMPLETED,CANCELLED',
        ]);

        if ($validator->fails()) return $this->errorResponse($request, $validator->errors()->first());

        try {
            if (Schema::hasTable('travel_tours')) {
                DB::table('travel_tours')->where('id', $id)->update([
                    'title'       => $request->title,
                    'capacity'    => $request->capacity,
                    'price_adult' => $request->price_adult,
                    'status'      => $request->status,
                    'updated_at'  => now(),
                ]);
            }

            $this->logActivity('tour_updated', $id, 'TravelTour', $request->all());

            if ($request->expectsJson()) return response()->json(['status' => true, 'message' => 'تور بروزرسانی شد.']);
            if (function_exists('notify')) notify()->success('تور بروزرسانی شد.');
            return back();
        } catch (\Exception $e) {
            return $this->errorResponse($request, $e->getMessage());
        }
    }

    /**
     * حذف یا بایگانی تور
     */
    public function delete(Request $request, $id)
    {
        try {
            if (Schema::hasTable('travel_tours')) {
                DB::table('travel_tours')->where('id', $id)->update(['status' => 'CANCELLED', 'updated_at' => now()]);
            }

            $this->logActivity('tour_deleted', $id, 'TravelTour');

            if ($request->expectsJson()) return response()->json(['status' => true, 'message' => 'تور حذف شد.']);
            if (function_exists('notify')) notify()->success('تور حذف شد.');
            return redirect()->route('admin.tours.index');
        } catch (\Exception $e) {
            return $this->errorResponse($request, $e->getMessage());
        }
    }

    /**
     * کارتابل کلیه رزرواسیون‌های سفر و مسافران
     */
    public function bookings(Request $request)
    {
        $filters = [
            'status'       => $request->input('status', 'all'),
            'service_type' => $request->input('service_type', 'all'),
            'search'       => $request->input('search'),
            'per_page'     => $request->integer('per_page', 15),
        ];

        $bookings = $this->queryBookings($filters);

        if ($request->expectsJson()) {
            return response()->json(['status' => true, 'data' => $bookings]);
        }

        return view('backend.tours.bookings', ['bookings' => $bookings, 'filters' => $filters]);
    }

    /**
     * مشاهده یک رزرو خاص
     */
    public function showBooking(Request $request, $id)
    {
        $booking = $this->findBooking($id);
        if (!$booking) {
            if ($request->expectsJson()) return response()->json(['status' => false, 'message' => 'رزرو یافت نشد.'], 404);
            if (function_exists('notify')) notify()->error('رزرو یافت نشد.');
            return redirect()->route('admin.travel_bookings.index');
        }

        if ($request->expectsJson()) return response()->json(['status' => true, 'data' => $booking]);
        return view('backend.tours.booking_show', ['booking' => $booking]);
    }

    /**
     * تایید قطعی رزرو و صدور ووچر سفر
     */
    public function confirmBooking(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'ticket_code'  => 'nullable|string|max:64',
            'voucher_url'  => 'nullable|string',
        ]);

        if ($validator->fails()) return $this->errorResponse($request, $validator->errors()->first());

        try {
            if (Schema::hasTable('travel_bookings')) {
                DB::table('travel_bookings')->where('id', $id)->update([
                    'status'       => 'CONFIRMED',
                    'ticket_code'  => $request->ticket_code ?? 'TKT-' . strtoupper(bin2hex(random_bytes(4))),
                    'voucher_url'  => $request->voucher_url,
                    'confirmed_at' => now(),
                    'updated_at'   => now(),
                ]);
            }

            $this->logActivity('travel_booking_confirmed', $id, 'TravelBooking', $request->all());

            $msg = 'رزرو تایید شد و بلیط/ووچر سفر برای مسافر صادر گردید.';
            if ($request->expectsJson()) return response()->json(['status' => true, 'message' => $msg]);

            if (function_exists('notify')) notify()->success($msg);
            return back();
        } catch (\Exception $e) {
            return $this->errorResponse($request, $e->getMessage());
        }
    }

    /**
     * لغو رزرو و استرداد وجه طبق قوانین کنسلی به کیف پول مسافر
     */
    public function cancelAndRefundBooking(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'penalty_pct' => 'required|numeric|min:0|max:100',
            'reason'      => 'required|string|max:500',
        ]);

        if ($validator->fails()) return $this->errorResponse($request, $validator->errors()->first());

        $admin = Auth::user();
        $adminId = $admin ? $admin->id : 1;

        try {
            DB::beginTransaction();

            $booking = $this->findBooking($id);
            if (!$booking) throw new \Exception('رزرو یافت نشد.');

            $totalAmount = (float) ($booking['total_amount'] ?? 0);
            $penaltyPct = (float) $request->penalty_pct;
            $refundAmount = $totalAmount * ((100.0 - $penaltyPct) / 100.0);
            $userId = $booking['user_id'] ?? null;

            // لجر دوطرفه بازگشت وجه به کیف پول
            if ($refundAmount > 0 && $userId && Schema::hasTable('wallets') && Schema::hasTable('journal_entries')) {
                $wallet = DB::table('wallets')->where('user_id', $userId)->orderBy('id', 'asc')->lockForUpdate()->first();
                if ($wallet) {
                    DB::table('wallets')->where('id', $wallet->id)->increment('balance', $refundAmount);

                    DB::table('journal_entries')->insert([
                        'reference_type' => 'TRAVEL_BOOKING_REFUND',
                        'reference_id'   => $id,
                        'debit_account'  => 'TRAVEL_PROVIDER_ESCROW',
                        'credit_account' => "WALLET_{$wallet->id}",
                        'amount'         => $refundAmount,
                        'currency'       => $booking['currency'] ?? 'IRR',
                        'operator_id'    => $adminId,
                        'created_at'     => now(),
                    ]);
                }
            }

            if (Schema::hasTable('travel_bookings')) {
                DB::table('travel_bookings')->where('id', $id)->update([
                    'status'        => 'CANCELLED_REFUNDED',
                    'refund_amount' => $refundAmount,
                    'penalty_amount'=> $totalAmount - $refundAmount,
                    'admin_note'    => $request->reason,
                    'cancelled_at'  => now(),
                    'updated_at'    => now(),
                ]);
            }

            $this->logActivity('travel_booking_cancelled_refunded', $id, 'TravelBooking', [
                'total'        => $totalAmount,
                'refund'       => $refundAmount,
                'penalty_pct'  => $penaltyPct,
                'reason'       => $request->reason,
            ]);

            DB::commit();

            $msg = "رزرو لغو و مبلغ " . number_format($refundAmount) . " پس از کسر {$penaltyPct}٪ جریمه کنسلی به کیف پول مسترد شد.";
            if ($request->expectsJson()) return response()->json(['status' => true, 'message' => $msg, 'refund_amount' => $refundAmount]);

            if (function_exists('notify')) notify()->success($msg);
            return back();

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse($request, $e->getMessage());
        }
    }

    // -------------------------------------------------------------
    // متدهای کمکی داخلی
    // -------------------------------------------------------------

    protected function getTourStats(): array
    {
        if (Schema::hasTable('travel_tours')) {
            return [
                'active_tours'    => DB::table('travel_tours')->where('status', 'ACTIVE')->count(),
                'total_bookings'  => Schema::hasTable('travel_bookings') ? DB::table('travel_bookings')->count() : 0,
                'pending_tickets' => Schema::hasTable('travel_bookings') ? DB::table('travel_bookings')->where('status', 'PENDING')->count() : 0,
            ];
        }
        return ['active_tours' => 0, 'total_bookings' => 0, 'pending_tickets' => 0];
    }

    protected function queryTours(array $filters): array
    {
        if (Schema::hasTable('travel_tours')) {
            $query = DB::table('travel_tours');
            if (!empty($filters['status']) && $filters['status'] !== 'all') $query->where('status', $filters['status']);
            if (!empty($filters['destination'])) $query->where('destination', $filters['destination']);
            if (!empty($filters['search'])) {
                $s = $filters['search'];
                $query->where(function ($q) use ($s) {
                    $q->where('title', 'like', "%{$s}%")->orWhere('destination', 'like', "%{$s}%");
                });
            }
            $paginator = $query->orderByDesc('id')->paginate($filters['per_page']);
            return [
                'current_page' => $paginator->currentPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
                'data'         => $paginator->items(),
            ];
        }
        return ['current_page' => 1, 'total' => 0, 'last_page' => 1, 'data' => []];
    }

    protected function findTour($id): ?array
    {
        if (Schema::hasTable('travel_tours')) {
            $rec = DB::table('travel_tours')->where('id', $id)->first();
            return $rec ? (array) $rec : null;
        }
        return null;
    }

    protected function queryTourPassengers($tourId): array
    {
        if (Schema::hasTable('travel_bookings')) {
            return DB::table('travel_bookings as tb')
                ->leftJoin('users as u', 'tb.user_id', '=', 'u.id')
                ->select('tb.*', 'u.name as user_name', 'u.phone as user_phone')
                ->where('tb.tour_id', $tourId)
                ->get()
                ->toArray();
        }
        return [];
    }

    protected function queryBookings(array $filters): array
    {
        if (Schema::hasTable('travel_bookings')) {
            $query = DB::table('travel_bookings as tb')
                ->leftJoin('users as u', 'tb.user_id', '=', 'u.id')
                ->select('tb.*', 'u.name as user_name', 'u.email as user_email');

            if (!empty($filters['status']) && $filters['status'] !== 'all') $query->where('tb.status', $filters['status']);
            if (!empty($filters['service_type']) && $filters['service_type'] !== 'all') $query->where('tb.service_type', $filters['service_type']);
            if (!empty($filters['search'])) {
                $s = $filters['search'];
                $query->where(function ($q) use ($s) {
                    $q->where('tb.booking_no', 'like', "%{$s}%")->orWhere('u.name', 'like', "%{$s}%");
                });
            }
            $paginator = $query->orderByDesc('tb.id')->paginate($filters['per_page']);
            return [
                'current_page' => $paginator->currentPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
                'data'         => $paginator->items(),
            ];
        }
        return ['current_page' => 1, 'total' => 0, 'last_page' => 1, 'data' => []];
    }

    protected function findBooking($id): ?array
    {
        if (Schema::hasTable('travel_bookings')) {
            $rec = DB::table('travel_bookings as tb')
                ->leftJoin('users as u', 'tb.user_id', '=', 'u.id')
                ->select('tb.*', 'u.name as user_name', 'u.email as user_email', 'u.phone as user_phone')
                ->where('tb.id', $id)
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
