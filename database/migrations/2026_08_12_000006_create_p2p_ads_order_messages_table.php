<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// [PHASE1] Migration جدول پیام‌های سفارش P2P
// پیام‌ها از طرف کاربر (خریدار/فروشنده) یا ادمین
// شامل فایل پیوست هم می‌تونن باشن
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('p2p_ads_order_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('p2p_ads_orders')->cascadeOnDelete();
            $table->string('sender_type', 10);  // user / admin
            $table->unsignedBigInteger('sender_id');  // user_id یا admin_id
            $table->text('message')->nullable();

            // [PHASE1] فایل پیوست
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->string('attachment_mime')->nullable();
            $table->unsignedBigInteger('attachment_size')->nullable();

            $table->timestamps();

            // [PHASE1] ایندکس
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('p2p_ads_order_messages');
    }
};
