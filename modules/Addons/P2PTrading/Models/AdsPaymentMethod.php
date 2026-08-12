<?php

namespace Addons\P2PTrading\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Addons\P2PTrading\Models\PaymentMethod;

// [PHASE1] مدل پیوند آگهی با متد پرداخت
// هر آگهی می‌تونه چند متد پرداخت داشته باشه
// این جدول رابطه many-to-many بین آگهی‌ها و متدهای پرداخت رو مدیریت می‌کنه
// فیلد fields شامل اطلاعات اضافه متد پرداخت هست (مثل شماره حساب)
class AdsPaymentMethod extends Model
{
    protected $table = 'p2p_ads_payment_methods';

    protected $guarded = [];

    // [PHASE1] رابطه با متد پرداخت
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    // [PHASE1] رابطه با آگهی
    public function ads(): BelongsTo
    {
        return $this->belongsTo(Ads::class, 'ads_id');
    }

    // [PHASE1] فیلدهای داینامیک متد پرداخت (JSON)
    protected function casts(): array
    {
        return [
            'fields' => 'json',
        ];
    }
}
