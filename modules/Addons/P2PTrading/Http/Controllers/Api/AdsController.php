<?php

namespace Addons\P2PTrading\Http\Controllers\Api;

use Addons\P2PTrading\Enums\AdsStatus;
use Addons\P2PTrading\Enums\AdsType;
use Addons\P2PTrading\Http\Resources\AdsResource;
use Addons\P2PTrading\Http\Resources\MarketplaceAdsResource;
use Addons\P2PTrading\Http\Resources\UserPaymentMethodResource;
use Addons\P2PTrading\Models\Ads;
use Addons\P2PTrading\Models\PaymentMethod;
use Addons\P2PTrading\Models\UserPaymentMethod;
use Addons\P2PTrading\Services\AdsService;
use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Traits\ApiResponseTrait;
use App\Traits\ImageUpload;
use App\Traits\NotifyTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

// [PHASE1] کنترلر API آگهی‌های P2P برای کاربر
// شامل: CRUD آگهی، مارکتپلیس، فیلتر بازار ایران/چین
// این کنترلر فقط از طریق API ( sanctum ) قابل دسترسیه
class AdsController extends Controller
{
    use ApiResponseTrait, ImageUpload, NotifyTrait;

    public function __construct(
        protected AdsService $adsService
    ) {}

    /**
     * [PHASE1] لیست آگهی‌های کاربر لاگین‌شده
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
            'status' => 'nullable|in:' . implode(',', array_column(AdsStatus::cases(), 'value')),
            'type' => 'nullable|in:' . implode(',', array_column(AdsType::cases(), 'value')),
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422, $validator->errors());
        }

        $user = $request->user();

        $filters = array_filter([
            'status' => $request->status,
            'currency' => $request->currency,
            'type' => $request->type,
            'per_page' => $request->integer('per_page', 15),
            'page' => $request->integer('page', 1),
        ]);

        $ads = $this->adsService->listAds($user, $filters);

        return $this->success([
            'ads' => AdsResource::collection($ads),
            'pagination' => [
                'current_page' => $ads->currentPage(),
                'last_page' => $ads->lastPage(),
                'per_page' => $ads->perPage(),
                'total' => $ads->total(),
            ],
        ], __('Ads list'));
    }

    /**
     * [PHASE1] ایجاد آگهی جدید
     * فیلدهای اجباری: asset_currency_id, fiat_currency_id, type, price, 
     *   min_amount, max_amount, total_amount, payment_duration, description, payment_method_ids
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'asset_currency_id' => 'required|exists:currencies,id',
            'fiat_currency_id' => 'required|exists:currencies,id',
            'type' => 'required|in:' . implode(',', array_column(AdsType::cases(), 'value')),
            'min_amount' => 'required|numeric|min:0.00000001',
            'max_amount' => 'required|numeric|min:0.00000001|gte:min_amount',
            'total_amount' => 'required|numeric|min:0.00000001',
            'price' => 'required|numeric|min:0.00000001',
            'payment_duration' => 'required|integer|min:1',
            'description' => 'required|string|max:5000',
            'auto_response_message' => 'nullable|string|max:500',
            'payment_method_ids' => 'required|array|min:1',
            'payment_method_ids.*' => 'required|exists:p2p_user_payment_methods,id',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422, $validator->errors());
        }

        try {
            $ad = $this->adsService->createAd($user, $request->all());

            return $this->success(
                new AdsResource($ad),
                __('Ad created successfully!')
            );
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * [PHASE1] بررسی واجد شرایط بودن کاربر برای ایجاد آگهی
     */
    public function eligibility(Request $request): JsonResponse
    {
        return $this->success(
            $this->adsService->getAdsCreationEligibility($request->user()),
            __('Ads eligibility')
        );
    }

    /**
     * [PHASE1] نمایش جزئیات آگهی کاربر
     */
    public function show($id): JsonResponse
    {
        $user = request()->user();

        $ad = $this->adsService->getAd($id, $user);

        if (! $ad) {
            return $this->error(__('Ad not found'), 404);
        }

        return $this->success(
            new MarketplaceAdsResource($ad),
            __('Ad details')
        );
    }

    /**
     * [PHASE1] بروزرسانی آگهی
     */
    public function update(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'price' => 'nullable|numeric|min:0.00000001',
            'min_amount' => 'nullable|numeric|min:0.00000001',
            'max_amount' => 'nullable|numeric|min:0.00000001',
            'total_amount' => 'nullable|numeric|min:0.00000001',
            'payment_duration' => 'nullable|integer|min:1',
            'description' => 'nullable|string|max:5000',
            'auto_response_message' => 'nullable|string|max:500',
            'status' => 'nullable|in:' . implode(',', [AdsStatus::Active->value, AdsStatus::Inactive->value]),
            'payment_method_ids' => 'nullable|array|min:1',
            'payment_method_ids.*' => 'required|exists:p2p_user_payment_methods,id',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422, $validator->errors());
        }

        $ad = Ads::where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (! $ad) {
            return $this->error(__('Ad not found'), 404);
        }

        try {
            $ad = $this->adsService->updateAd($ad, $user, $request->all());

            return $this->success(
                new AdsResource($ad),
                __('Ad updated successfully!')
            );
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * [PHASE1] تغییر وضعیت آگهی (فعال/غیرفعال)
     */
    public function statusUpdate(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:' . implode(',', [AdsStatus::Active->value, AdsStatus::Inactive->value]),
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422, $validator->errors());
        }

        $ad = Ads::where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (! $ad) {
            return $this->error(__('Ad not found'), 404);
        }

        try {
            $ad = $this->adsService->updateAdStatus($ad, $user, $request->string('status')->toString());

            return $this->success(
                new AdsResource($ad),
                __('Ad status updated successfully!')
            );
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * [PHASE1] لیست متدهای پرداخت مجاز برای یک آگهی
     */
    public function paymentMethods($id): JsonResponse
    {
        $user = request()->user();

        $ad = Ads::find($id);

        if (! $ad) {
            return $this->error(__('Ad not found'), 404);
        }

        $paymentMethodIds = $ad->paymentMethods()->pluck('payment_method_id');

        $paymentMethods = UserPaymentMethod::with('paymentMethod.currency')
            ->where('user_id', $user->id)
            ->whereIn('payment_method_id', $paymentMethodIds)->get();

        return $this->success([
            'payment_methods' => UserPaymentMethodResource::collection($paymentMethods),
        ], __('Ad payment methods'));
    }

    /**
     * [PHASE1] حذف آگهی
     */
    public function destroy($id): JsonResponse
    {
        $user = request()->user();

        $ad = Ads::where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (! $ad) {
            return $this->error(__('Ad not found'), 404);
        }

        try {
            $this->adsService->deleteAd($ad);

            return $this->success([], __('Ad deleted successfully!'));
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * [PHASE1] مارکتپلیس - لیست آگهی‌های فعال
     * شامل فیلتر: نوع، کیورنسی‌ها، بازار (ایران/چین)، دلار نقد، متد پرداخت
     */
    public function marketplace(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => 'nullable|in:' . implode(',', array_column(AdsType::cases(), 'value')),
            'asset_currency_id' => 'nullable|exists:currencies,id',
            'fiat_currency_id' => 'nullable|exists:currencies,id',
            'payment_method_id' => 'nullable|array',
            'payment_method_id.*' => 'exists:p2p_payment_methods,id',
            'min_amount' => 'nullable|numeric|min:0',
            'max_amount' => 'nullable|numeric|min:0',
            'per_page' => 'nullable|integer|min:1',
            'page' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422, $validator->errors());
        }

        // [PHASE1] فیلترهای مارکتپلیس شامل بازار و دلار نقد
        $filters = array_filter([
            'type' => $request->type,
            'asset_currency_id' => $request->asset_currency_id,
            'fiat_currency_id' => $request->fiat_currency_id,
            'market_type' => $request->market_type,           // [PHASE1] فیلتر بازار (iran/china)
            'is_cash_dollar' => $request->is_cash_dollar,    // [PHASE1] فیلتر دلار نقد
            'payment_method_id' => $request->payment_method_id,
            'min_amount' => $request->min_amount,
            'per_page' => $request->integer('per_page', 15),
        ]);

        $ads = $this->adsService->listMarketplaceAds($filters);

        return $this->success([
            'ads' => MarketplaceAdsResource::collection($ads),
            'pagination' => [
                'current_page' => $ads->currentPage(),
                'last_page' => $ads->lastPage(),
                'per_page' => $ads->perPage(),
                'total' => $ads->total(),
            ],
        ], __('Marketplace ads'));
    }

    /**
     * [PHASE1] نمایش جزئیات آگهی در مارکتپلیس
     */
    public function marketplaceAdShow($id): JsonResponse
    {
        $ad = Ads::with(['assetCurrency', 'fiatCurrency', 'paymentMethods.paymentMethod.currency'])
            ->where('id', $id)
            ->first();

        if (! $ad) {
            return $this->error(__('Ad not found'), 404);
        }

        return $this->success(
            new MarketplaceAdsResource($ad),
            __('Ad details')
        );
    }

    /**
     * [PHASE1] بالاترین قیمت سفارش (برای محاسبه نرخ بازار)
     */
    public function highestOrderPrice(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => 'nullable|in:' . implode(',', array_column(AdsType::cases(), 'value')),
            'asset_currency_id' => 'nullable|exists:currencies,id',
            'fiat_currency_id' => 'nullable|exists:currencies,id',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422, $validator->errors());
        }

        $filters = array_filter([
            'type' => $request->type,
            'asset_currency_id' => $request->asset_currency_id,
            'fiat_currency_id' => $request->fiat_currency_id,
        ]);

        $highestPrice = $this->adsService->getHighestMarketplacePrice($filters);

        return $this->success([
            'highest_price' => $highestPrice,
            'filters' => $filters,
            'price_source' => 'active_ads',
        ], __('Highest order price'));
    }

    /**
     * [PHASE1] کانفیگ مارکتپلیس - لیست کیورنسی‌ها و متدهای پرداخت فعال
     */
    public function marketplaceConfig(): JsonResponse
    {
        $assetCurrencyIds = Ads::where('status', AdsStatus::Active)->distinct()->pluck('asset_currency_id');
        $fiatCurrencyIds = Ads::where('status', AdsStatus::Active)->distinct()->pluck('fiat_currency_id');

        $assetCurrencies = Currency::whereIn('id', $assetCurrencyIds)
            ->get(['id', 'name', 'code', 'icon', 'symbol'])
            ->map(fn($c) => ['id' => $c->id, 'name' => $c->name,  'icon' => $c->icon ? asset($c->icon) : null, 'code' => $c->code, 'symbol' => $c->symbol]);

        $fiatCurrencies = Currency::whereIn('id', $fiatCurrencyIds)
            ->get(['id', 'name', 'code', 'icon', 'symbol'])
            ->map(fn($c) => ['id' => $c->id, 'name' => $c->name, 'icon' => $c->icon ? asset($c->icon) : null, 'code' => $c->code, 'symbol' => $c->symbol]);

        $paymentMethods = PaymentMethod::with('currency')
            ->get(['id', 'name', 'currency_id'])
            ->map(fn($pm) => [
                'id' => $pm->id,
                'name' => $pm->name,
                'currency' => $pm->currency ? ['id' => $pm->currency->id, 'code' => $pm->currency->code] : null,
            ]);

        return $this->success([
            'asset_currencies' => $assetCurrencies,
            'fiat_currencies' => $fiatCurrencies,
            'payment_methods' => $paymentMethods,
        ], __('Marketplace config'));
    }

    /**
     * [PHASE1] وضعیت درخواست تاجر تاییدشده
     */
    public function verifiedTraderApplicationStatus(Request $request): JsonResponse
    {
        return $this->success(
            $this->adsService->getVerifiedTraderEligibility($request->user()),
            __('Verified Trader eligibility')
        );
    }

    /**
     * [PHASE1] ثبت درخواست تاجر تاییدشده
     */
    public function verifiedTraderApply(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'fields' => 'required|array',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422, $validator->errors());
        }

        try {
            $data = $request->all();

            if ($request->hasFile('fields')) {
                foreach ($request->file('fields') as $key => $file) {
                    $data['fields'][$key] = self::imageUploadTrait($file);
                }
            }

            $application = $this->adsService->applyForVerifiedTrader($user, $data);

            $shortcodes = [
                '[[full_name]]' => $user->full_name,
                '[[kyc_status_link]]' => route('admin.verification.all'),
            ];

            $this->sendNotify(setting('site_email', 'global'), 'p2p_trader_applied', 'Admin', $shortcodes, $user->phone, $user->id, route('admin.verification.all'));

            return $this->success(
                $application,
                __('Verified Trader application submitted successfully!')
            );
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 422);
        }
    }
}
