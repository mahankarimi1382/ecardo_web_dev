<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// [PHASE1] Migration جدول پیوند آگهی با متدهای پرداخت
// هر آگهی می‌تونه چند متد پرداخت داشته باشه
// fields: JSON شامل اطلاعات تکمیلی (مثل شماره کارت خاص برای این آگهی)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('p2p_ads_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ads_id')->constrained('p2p_ads')->cascadeOnDelete();
            $table->foreignId('payment_method_id')->constrained('p2p_payment_methods')->cascadeOnDelete();
            $table->json('fields')->nullable();                // اطلاعات اضافه
            $table->timestamps();

            // [PHASE1] جلوگیری از تکرار
            $table->unique(['ads_id', 'payment_method_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('p2p_ads_payment_methods');
    }
};
