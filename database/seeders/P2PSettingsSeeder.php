<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

// [PHASE1] Seeder تنظیمات اولیه P2P
// این فیلدها تو جدول settings با type='p2p' ذخیره میشن
class P2PSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // [PHASE1] آیا آگهی نیاز به تایید ادمین داره؟
            ['name' => 'ads_approval_required', 'type' => 'p2p', 'val' => '1'],
            // [PHASE1] درصد کارمزد سفارش
            ['name' => 'order_fee_percent', 'type' => 'p2p', 'val' => '0.5'],
            // [PHASE1] مهلت رهاسازی فروشنده بعد از پرداخت (دقیقه)
            ['name' => 'seller_release_grace_minutes', 'type' => 'p2p', 'val' => '30'],
            // [PHASE1] حداقل سفارشات تکمیل‌شده برای تاجر تاییدشده
            ['name' => 'verified_trader_min_completed_orders', 'type' => 'p2p', 'val' => '10'],
            // [PHASE1] حداقل نرخ تکمیل (درصد) برای تاجر تاییدشده
            ['name' => 'verified_trader_min_completion_rate', 'type' => 'p2p', 'val' => '90'],
            // [PHASE1] حداقل نرخ موفقیت تراکنش
            ['name' => 'min_transactions_success_rate', 'type' => 'p2p', 'val' => '80'],
            // [PHASE1] حداقل نرخ تکمیل P2P
            ['name' => 'min_completion_rate', 'type' => 'p2p', 'val' => '85'],
            // [PHASE1] حداقل سفارشات مورد نیاز
            ['name' => 'min_orders_required', 'type' => 'p2p', 'val' => '3'],
            // [PHASE1] کارمزد ثبت آگهی
            ['name' => 'ads_poster_fee', 'type' => 'p2p', 'val' => '0'],
            // [PHASE1] فعال بودن معامله P2P
            ['name' => 'p2p_trade_enabled', 'type' => 'p2p', 'val' => '1'],
            // [PHASE1] فیلدهای جدید بازار ایران
            ['name' => 'market_iran_enabled', 'type' => 'p2p', 'val' => '1'],
            // [PHASE1] بازار چین (Phase 2)
            ['name' => 'market_china_enabled', 'type' => 'p2p', 'val' => '0'],
            // [PHASE1] فعال بودن دلار نقد
            ['name' => 'cash_dollar_enabled', 'type' => 'p2p', 'val' => '1'],
        ];

        foreach ($settings as $setting) {
            DB::table('settings')->updateOrInsert(
                ['name' => $setting['name'], 'type' => $setting['type']],
                ['val' => $setting['val'], 'updated_at' => now()]
            );
        }
    }
}
