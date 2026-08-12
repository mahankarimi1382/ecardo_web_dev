<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// [PHASE1] Migration جدول آگهی‌های P2P
// هر آگهی یک پیشنهاد خرید/فروش ارز توسط کاربر است
// market_type: نوع بازار (iran/china) - [PHASE1] فیلد جدید
// is_cash_dollar: آیا دلار نقد است - [PHASE1] فیلد جدید
// price: قیمت واحد (فیات به ازای هر واحد دارایی)
// total_amount: کل مقدار دارایی موجود در آگهی
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('p2p_ads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 10);                         // buy / sell
            $table->string('status', 20)->default('pending');    // pending/active/inactive/completed/cancelled
            $table->foreignId('asset_currency_id')->constrained('currencies')->cascadeOnDelete();
            $table->foreignId('fiat_currency_id')->constrained('currencies')->cascadeOnDelete();
            $table->decimal('price', 20, 8);                   // قیمت واحد
            $table->decimal('min_amount', 20, 8);             // حداقل مبلغ سفارش (فیات)
            $table->decimal('max_amount', 20, 8);             // حداکثر مبلغ سفارش (فیات)
            $table->decimal('total_amount', 20, 8);          // کل لیقویتی دارایی
            $table->integer('payment_duration');               // مهلت پرداخت (دقیقه)
            $table->text('description')->nullable();          // توضیحات آگهی
            $table->string('auto_response_message', 500)->nullable(); // پیام خودکار

            // [PHASE1] فیلدهای جدید بازار ایران
            $table->string('market_type', 20)->default('iran');  // iran / china
            $table->boolean('is_cash_dollar')->default(false);    // دلار نقد

            $table->timestamps();
            $table->softDeletes();

            // [PHASE1] ایندکس‌ها برای پرفورمنس
            $table->index(['status', 'type']);
            $table->index(['market_type', 'is_cash_dollar']);
            $table->index('asset_currency_id');
            $table->index('fiat_currency_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('p2p_ads');
    }
};
