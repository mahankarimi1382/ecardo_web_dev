<?php

namespace Addons\P2PTrading\Http\Controllers\Api;

use Addons\P2PTrading\Enums\OrderStatus;
use Addons\P2PTrading\Http\Resources\OrderResource;
use Addons\P2PTrading\Models\AdsOrder;
use Addons\P2PTrading\Services\OrderService;
use App\Http\Controllers\Controller;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

// [PHASE1] کنترلر API سفارشات P2P برای کاربر
// شامل: ایجاد سفارش، علامت‌گذاری پرداخت، رهاسازی، لغو، اختلاف
// تمام عملیات مالی داخل تراکنش DB با lockForUpdate انجام میشن

class OrderController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        protected OrderService $orderService
    ) {}

    /**
     * [PHASE1] لیست سفارشات کاربر (خریدار یا فروشنده بودن)
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'order_number' => 'nullable|string',
            'status' => 'nullable|in:' . implode(',', array_column(OrderStatus::cases(), 'value')),
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422, $validator->errors());
        }

        $orders = $this->orderService->listUserOrders($request->user(), [
            'status' => $request->status,
            'order_number' => $request->order_number,
            'per_page' => $request->integer('per_page', 15),
        ]);

        return $this->success([
            'orders' => OrderResource::collection($orders),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ], __('Order list'));
    }

    /**
     * [PHASE1] جزئیات یک سفارش
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $order = $this->orderService->getUserOrder($request->user(), $id);

        if (! $order) {
            return $this->error(__('Order not found'), 404);
        }

        return $this->success(new OrderResource($order), __('Order details'));
    }

    /**
     * [PHASE1] ایجاد سفارش جدید از یک آگهی
     * مبلغ می‌تونه asset_amount یا fiat_amount باشه (بسته به نوع آگهی)
     * فرمت ورودی حداکثر 1,000.50 پشتیبانی می‌شه
     */
    public function store(Request $request): JsonResponse
    {
        // [PHASE1] نرمال‌سازی ورودی مبلغ
        $assetAmount = $request->input('asset_amount');
        $fiatAmount = $request->input('fiat_amount');

        $assetAmount = $assetAmount !== null ? trim(str_replace(',', '', (string) $assetAmount)) : null;
        $fiatAmount = $fiatAmount !== null ? trim(str_replace(',', '', (string) $fiatAmount)) : null;

        $request->merge([
            'asset_amount' => $assetAmount === '' ? null : $assetAmount,
            'fiat_amount' => $fiatAmount === '' ? null : $fiatAmount,
        ]);

        $validator = Validator::make($request->all(), [
            'ad_id' => 'required|exists:p2p_ads,id',
            'asset_amount' => 'nullable|numeric|min:0.00000001',
            'fiat_amount' => 'nullable|numeric|min:0.00000001',
            'payment_method_id' => 'required|exists:p2p_payment_methods,id',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422, $validator->errors());
        }

        try {
            $order = $this->orderService->createOrder($request->user(), $request->all());

            return $this->success(new OrderResource($order), __('Order created successfully'));
        } catch (\Exception $exception) {
            return $this->error($exception->getMessage(), 422);
        }
    }

    /**
     * [PHASE1] خریدار تایید می‌کنه که پول رو فرستاده
     * فقط خریدار می‌تونه این کار رو بکنه
     * فقط سفارشات pending_payment قابل تایید هستن
     */
    public function markPaid(Request $request, int $id): JsonResponse
    {
        /** @var AdsOrder $order */
        $order = AdsOrder::find($id);
        if (! $order) {
            return $this->error(__('Order not found'), 404);
        }

        try {
            $this->orderService->ensureUserParticipant($order, $request->user());
            $order = $this->orderService->markPaid($order, $request->user());
            return $this->success(new OrderResource($order), __('Order marked as paid'));
        } catch (\Exception $exception) {
            return $this->error($exception->getMessage(), 422);
        }
    }

    /**
     * [PHASE1] فروشنده دارایی رو رهاسازی می‌کنه
     * فقط فروشنده و فقط سفارشات paid قابل رهاسازی هستن
     */
    public function release(Request $request, int $id): JsonResponse
    {
        /** @var AdsOrder $order */
        $order = AdsOrder::find($id);
        if (! $order) {
            return $this->error(__('Order not found'), 404);
        }

        try {
            $this->orderService->ensureUserParticipant($order, $request->user());
            $order = $this->orderService->release($order, $request->user());
            return $this->success(new OrderResource($order), __('Order released successfully'));
        } catch (\Exception $exception) {
            return $this->error($exception->getMessage(), 422);
        }
    }

    /**
     * [PHASE1] لغو سفارش - فقط در وضعیت pending_payment
     * دارایی قفل‌شده به فروشنده برمی‌گرده
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        /** @var AdsOrder $order */
        $order = AdsOrder::find($id);
        if (! $order) {
            return $this->error(__('Order not found'), 404);
        }

        try {
            $this->orderService->ensureUserParticipant($order, $request->user());
            $order = $this->orderService->cancel($order, $request->user());
            return $this->success(new OrderResource($order), __('Order cancelled successfully'));
        } catch (\Exception $exception) {
            return $this->error($exception->getMessage(), 422);
        }
    }

    /**
     * [PHASE1] باز کردن اختلاف - فقط در وضعیت paid
     * ادمین باید تصمیم بگیره: رهاسازی به خریدار یا برگشت به فروشنده
     */
    public function dispute(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422, $validator->errors());
        }

        /** @var AdsOrder $order */
        $order = AdsOrder::find($id);
        if (! $order) {
            return $this->error(__('Order not found'), 404);
        }

        try {
            $this->orderService->ensureUserParticipant($order, $request->user());
            $order = $this->orderService->dispute($order, $request->user(), $request->reason);
            return $this->success(new OrderResource($order), __('Dispute opened successfully'));
        } catch (\Exception $exception) {
            return $this->error($exception->getMessage(), 422);
        }
    }

    /**
     * [PHASE1] تغییر متد پرداخت سفارش - فقط در وضعیت pending_payment
     */
    public function updatePaymentMethod(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'payment_method_id' => 'required|exists:p2p_payment_methods,id',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422, $validator->errors());
        }

        /** @var AdsOrder $order */
        $order = AdsOrder::find($id);
        if (! $order) {
            return $this->error(__('Order not found'), 404);
        }

        try {
            $order = $this->orderService->updatePaymentMethod($order, $request->user(), (int) $request->payment_method_id);
            return $this->success(new OrderResource($order), __('Payment method updated successfully'));
        } catch (\Exception $exception) {
            return $this->error($exception->getMessage(), 422);
        }
    }
}
