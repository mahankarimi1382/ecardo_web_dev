<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// [PHASE1] Migration جدول سفارشات P2P
// هر سفارش از یک آگهی ایجاد می‌شه
// جریان: pending_payment -> paid -> completed
//   یا: pending_payment -> cancelled/expired
//   یا: paid -> disputed -> completed/cancelled (توسط ادمین)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('p2p_ads_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ads_id')->constrained('p2p_ads')->cascadeOnDelete();
            $table->foreignId('ad_owner_id')->constrained('users');
            $table->foreignId('order_creator_id')->constrained('users');
            $table->foreignId('seller_user_id')->constrained('users');
            $table->foreignId('buyer_user_id')->constrained('users');
            $table->foreignId('asset_currency_id')->constrained('currencies');
            $table->foreignId('fiat_currency_id')->constrained('currencies');
            $table->foreignId('payment_method_id')->constrained('p2p_payment_methods');

            $table->string('status', 20)->default('pending_payment');
            $table->decimal('asset_amount', 20, 8);           // مقدار دارایی
            $table->decimal('fiat_amount', 20, 8);             // مقدار فیات
            $table->decimal('price', 20, 8);                   // قیمت واحد
            $table->decimal('fee_percent', 8, 4)->default(0); // درصد کارمزد
            $table->decimal('fee_asset_amount', 20, 8)->default(0); // مبلغ کارمزد
            $table->decimal('seller_locked_asset', 20, 8);    // مبلغ قفل‌شده فروشنده

            // [PHASE1] تایم‌لاین سفارش
            $table->timestamp('payment_deadline_at')->nullable();  // مهلت پرداخت
            $table->timestamp('marked_paid_at')->nullable();       // زمان تایید پرداخت
            $table->timestamp('completed_at')->nullable();         // زمان تکمیل
            $table->timestamp('cancelled_at')->nullable();         // زمان لغو
            $table->timestamp('expired_at')->nullable();           // زمان انقضا
            $table->timestamp('disputed_at')->nullable();          // زمان باز شدن اختلاف
            $table->timestamp('resolved_at')->nullable();          // زمان حل اختلاف

            // [PHASE1] حل اختلاف
            $table->foreignId('resolved_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('dispute_reason', 2000)->nullable();
            $table->string('resolution_note', 2000)->nullable();

            $table->timestamps();

            // [PHASE1] ایندکس‌ها
            $table->index(['status']);
            $table->index(['buyer_user_id', 'status']);
            $table->index(['seller_user_id', 'status']);
            $table->index('payment_deadline_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('p2p_ads_orders');
    }
};
