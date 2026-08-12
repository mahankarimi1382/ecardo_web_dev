<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

// [PHASE1] کنترلر تنظیمات ماژول P2P
// تنظیمات از جدول settings با type='p2p' خوانده/نوشته میشن
// باگ رفع شده:
//   1. تایپو "آپسirt" اصلاح شد
//   2. براکت اشتباه `)` به `]` اصلاح شد (نسخه قبلی خط 48)
// پرمیشن: p2p-ads-manage
// لیست فیلدها:
//   - ads_approval_required: آیا آگهی نیاز به تایید ادمین داره؟
//   - order_fee_percent: درصد کارمزد سفارش
//   - seller_release_grace_minutes: زمان مهلت رهاسازی فروشنده (دقیقه)
//   - verified_trader_min_completed_orders: حداقل سفارشات تکمیل‌شده برای تاجر تاییدشده
//   - verified_trader_min_completion_rate: حداقل نرخ تکمیل (درصد)
//   - min_transactions_success_rate: حداقل نرخ موفقیت تراکنش‌ها
//   - min_completion_rate: حداقل نرخ تکمیل
//   - min_orders_required: حداقل سفارشات مورد نیاز
//   - ads_poster_fee: کارمزد ثبت آگهی
//   - p2p_trade_enabled: فعال/غیرفعال بودن معامله P2P
//   - market_iran_enabled: فعال بودن بازار ایران
//   - market_china_enabled: فعال بودن بازار چین
//   - cash_dollar_enabled: فعال بودن دلار نقد
class P2pSettingsController extends Controller implements HasMiddleware
{
    public static function middleware()
    {
        return [
            new Middleware('permission:p2p-ads-manage'),
        ];
    }

    /**
     * [PHASE1] نمایش صفحه تنظیمات P2P
     * تمام تنظیمات با type='p2p' از جدول settings خوانده میشن
     */
    public function index()
    {
        $settings = DB::table('settings')->where('type', 'p2p')->pluck('val', 'name')->toArray();

        return view('backend.p2p.settings.index', ['settings' => $settings]);
    }

    /**
     * [PHASE1-FIX] ذخیره تنظیمات P2P
     * باگ رفع شده: براکت `)` به `]` و تایپو اصلاح شد
     * از UPSERT (updateOrInsert) استفاده می‌کنه
     */
    public function update(Request $request)
    {
        // [PHASE1-FIX] لیست فیلدهای مجاز - آرایه صحیح
        $allowedFields = [
            'ads_approval_required',
            'order_fee_percent',
            'seller_release_grace_minutes',
            'verified_trader_min_completed_orders',
            'verified_trader_min_completion_rate',
            'min_transactions_success_rate',
            'min_completion_rate',
            'min_orders_required',
            'ads_poster_fee',
            'p2p_trade_enabled',
            // [PHASE1] فیلدهای جدید Phase 1
            'market_iran_enabled',
            'market_china_enabled',
            'cash_dollar_enabled',
        ];

        foreach ($allowedFields as $field) {
            if ($request->has($field)) {
                $value = $request->input($field);
                // [PHASE1] برای فیلدهای بولین
                if (in_array($field, [
                    'ads_approval_required',
                    'p2p_trade_enabled',
                    'market_iran_enabled',
                    'market_china_enabled',
                    'cash_dollar_enabled',
                ])) {
                    $value = $value ? '1' : '0';
                }

                // [PHASE1-FIX] UPSERT با آرایه صحیح (باگ براکت رفع شد)
                DB::table('settings')->updateOrInsert(
                    ['name' => $field, 'type' => 'p2p'],
                    ['val' => $value, 'updated_at' => now()]
                );
            }
        }

        // [PHASE1] پاک کردن کش تنظیمات
        \Artisan::call('cache:clear');

        notify()->success(__('P2P settings updated successfully!'));
        return back();
    }
}
