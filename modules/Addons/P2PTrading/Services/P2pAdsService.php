<?php

namespace Addons\P2PTrading\Services;

use Addons\P2PTrading\Enums\AdsStatus;
use Addons\P2PTrading\Enums\AdsType;
use Addons\P2PTrading\Models\Ads;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

// [PHASE1] سرویس مدیریت آگهی‌ها از دید ادمین
// فقط برای پنل ادمین استفاده می‌شه
// شامل: لیست آگهی‌ها، تایید/رد آگهی‌ها
// باگ رفع شده:
//   1. پرانتز بسته نشده در when() برای is_cash_dollar (خط 27 نسخه قبلی)
//   2. فیلترهای fiat_currency و asset_currency تکراری حذف شدند
//   3. تایپو در getNotificationShortcodes: [essage]] به [[message]]
class P2pAdsService
{
    /**
     * [PHASE1] لیست تمام آگهی‌ها با فیلترها
     * فیلترها: وضعیت، نوع، بازار (ایران/چین)، دلار نقد، کیورنسی‌ها، جستجو
     */
    public function listAds(array $filters = []): LengthAwarePaginator
    {
        $query = Ads::with(['user', 'assetCurrency', 'fiatCurrency', 'paymentMethods.paymentMethod'])
            // [PHASE1-FIX] فیلتر کیورنسی فیات (فقط یک بار)
            ->when(!empty($filters['fiat_currency']), fn($q) => $q->where('fiat_currency_id', $filters['fiat_currency']))
            // [PHASE1-FIX] فیلتر کیورنسی دارایی (فقط یک بار)
            ->when(!empty($filters['asset_currency']), fn($q) => $q->where('asset_currency_id', $filters['asset_currency']))
            // [PHASE1] فیلتر وضعیت آگهی
            ->when(isset($filters['status']), fn($q) => $q->where('status', AdsStatus::from($filters['status'])))
            // [PHASE1] فیلتر نوع آگهی (خرید/فروش)
            ->when(isset($filters['type']), fn($q) => $q->where('type', AdsType::from($filters['type'])))
            // [PHASE1] فیلتر بازار (ایران/چین)
            ->when(isset($filters['market_type']), fn($q) => $q->where('market_type', $filters['market_type']))
            // [PHASE1-FIX] فیلتر دلار نقد - باگ پرانتز رفع شد
            ->when(isset($filters['is_cash_dollar']), fn($q) => $q->where('is_cash_dollar', (int) $filters['is_cash_dollar']))
            // [PHASE1] جستجو بر اساس نام کاربری یا ایمیل کاربر
            ->when(isset($filters['search']), function ($q) use ($filters) {
                return $q->whereHas('user', function ($query) use ($filters) {
                    $query->where('username', 'like', "%{$filters['search']}%")
                        ->orWhere('email', 'like', "%{$filters['search']}%");
                });
            })
            ->latest();

        return $query->paginate($filters['per_page'] ?? 15);
    }

    /**
     * [PHASE1] لیست آگهی‌های در انتظار تایید
     * ادمین فقط آگهی‌های pending رو اینجا می‌بینه
     */
    public function listPendingAds(array $filters = []): LengthAwarePaginator
    {
        $query = Ads::with(['user', 'assetCurrency', 'fiatCurrency', 'paymentMethods.paymentMethod'])
            ->where('status', AdsStatus::Pending)
            // [PHASE1] فیلتر بازار (ایران/چین)
            ->when(isset($filters['market_type']), fn($q) => $q->where('market_type', $filters['market_type']))
            // [PHASE1] فیلتر دلار نقد
            ->when(isset($filters['is_cash_dollar']), fn($q) => $q->where('is_cash_dollar', (int) $filters['is_cash_dollar']))
            // [PHASE1] جستجو
            ->when(isset($filters['search']), function ($q) use ($filters) {
                return $q->whereHas('user', function ($query) use ($filters) {
                    $query->where('username', 'like', "%{$filters['search']}%")
                        ->orWhere('email', 'like', "%{$filters['search']}%");
                });
            })
            ->latest();

        return $query->paginate($filters['per_page'] ?? 15);
    }

    /**
     * [PHASE1] دریافت جزئیات آگهی برای مودال تایید/رد
     * شامل اطلاعات کاربر، کیورنسی‌ها و متدهای پرداخت
     */
    public function getAdForApproval(int $id): Ads
    {
        return Ads::with(['user', 'assetCurrency', 'fiatCurrency', 'paymentMethods.paymentMethod.currency'])
            ->findOrFail($id);
    }

    /**
     * [PHASE1] تایید آگهی توسط ادمین
     * وضعیت آگهی رو از pending به active تغییر می‌ده
     * و ایمیلی حاوی شورتکدها برای کاربر می‌فرسته
     */
    public function approveAd(Ads $ad, ?string $message = null): array
    {
        $ad->update([
            'status' => AdsStatus::Active,
        ]);

        return [
            'ad' => $ad->load(['user', 'assetCurrency', 'fiatCurrency']),
            'shortcodes' => $this->getNotificationShortcodes($ad, $message),
            'template' => 'p2p_ad_status_update',
        ];
    }

    /**
     * [PHASE1] رد آگهی توسط ادمین
     * وضعیت آگهی رو به cancelled تغییر می‌ده
     */
    public function rejectAd(Ads $ad, ?string $message = null): array
    {
        $ad->update([
            'status' => AdsStatus::Cancelled,
        ]);

        return [
            'ad' => $ad->load(['user', 'assetCurrency', 'fiatCurrency']),
            'shortcodes' => $this->getNotificationShortcodes($ad, $message),
            'template' => 'p2p_ad_status_update',
        ];
    }

    /**
     * [PHASE1-FIX] ساخت شورتکدهای نوتیفیکیشن
     * باگ رفع شده: [essage]] به [[message]] اصلاح شد
     */
    protected function getNotificationShortcodes(Ads $ad, ?string $message = null): array
    {
        return [
            '[[full_name]]' => $ad->user->full_name,
            '[[ad_id]]' => $ad->id,
            '[[type]]' => ucfirst($ad->type->value),
            '[[asset_currency]]' => $ad->assetCurrency->code,
            '[[fiat_currency]]' => $ad->fiatCurrency->code,
            '[[price]]' => formatAmount($ad->price, $ad->fiatCurrency->code),
            '[[status]]' => $ad->status->value,
            // [PHASE1-FIX] تایپو رفع شد: [essage]] به [[message]]
            '[[message]]' => $message ?? '',
            '[[site_title]]' => setting('site_title', 'global'),
        ];
    }
}
