<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// [PHASE1] Migration جدول حساب‌های پرداخت کاربران
// هر کاربر برای هر متد پرداخت می‌تونه اطلاعات حساب خودش رو ثبت کنه
// مثال: کاربر X شماره کارت ملت رو برای متد "کارت به کارت" ثبت می‌کنه
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('p2p_user_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('payment_method_id')->constrained('p2p_payment_methods')->cascadeOnDelete();
            $table->json('fields')->nullable();                // اطلاعات واقعی حساب کاربر
            $table->timestamps();

            // [PHASE1] هر کاربر فقط یک حساب برای هر متد پرداخت
            $table->unique(['user_id', 'payment_method_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('p2p_user_payment_methods');
    }
};
