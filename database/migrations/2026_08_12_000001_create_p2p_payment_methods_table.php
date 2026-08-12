<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// [PHASE1] Migration جدول متدهای پرداخت P2P
// متدهایی مثل: کارت به کارت، انتقال بانکی، عیان‌الرحمن
// هر متد به یک کیورنسی فیات متصل است
// fields: JSON شامل تعریف فیلدهای داینامیک
//   مثال: [{"name":"card_number","type":"text","validation":"required"}]
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('p2p_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('currency_id')->constrained('currencies')->cascadeOnDelete();
            $table->string('name');                              // نام متد: کارت به کارت، انتقال بانکی
            $table->json('fields')->nullable();                // فیلدهای داینامیک
            $table->boolean('is_active')->default(true);       // [PHASE1] فعال/غیرفعال
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('p2p_payment_methods');
    }
};
