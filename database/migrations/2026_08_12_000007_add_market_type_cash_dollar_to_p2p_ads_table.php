<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// [PHASE1] Migration افزودن ستون‌های market_type و is_cash_dollar به جدول p2p_ads
// اگر جدول از قبل وجود داره، فقط ستون‌های جدید اضافه میشن
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('p2p_ads', function (Blueprint $table) {
            // [PHASE1] نوع بازار: iran / china
            if (! Schema::hasColumn('p2p_ads', 'market_type')) {
                $table->string('market_type', 20)->default('iran')->after('auto_response_message');
            }
            // [PHASE1] دلار نقد
            if (! Schema::hasColumn('p2p_ads', 'is_cash_dollar')) {
                $table->boolean('is_cash_dollar')->default(false)->after('market_type');
            }
            // [PHASE1] ایندکس برای فیلتر بازار
            $table->index(['market_type', 'is_cash_dollar']);
        });
    }

    public function down(): void
    {
        Schema::table('p2p_ads', function (Blueprint $table) {
            $table->dropIndex(['market_type', 'is_cash_dollar']);
            $table->dropColumn(['market_type', 'is_cash_dollar']);
        });
    }
};
