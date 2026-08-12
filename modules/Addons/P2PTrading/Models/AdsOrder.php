<?php

namespace Addons\P2PTrading\Models;

use Addons\P2PTrading\Enums\OrderStatus;
use App\Models\Admin;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// [PHASE1] مدل سفارش P2P
// هر سفارش از یک آگهی ایجاد می‌شود و شامل اطلاعات خریدار/فروشنده و مبلغ است
class AdsOrder extends Model
{
    protected $table = 'p2p_ads_orders';

    protected $guarded = [];

    // [PHASE1] رابطه با آگهی مرتبط
    public function ad(): BelongsTo
    {
        return $this->belongsTo(Ads::class, 'ads_id');
    }

    // [PHASE1] رابطه با متد پرداخت انتخاب‌شده
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    // [PHASE1] رابطه با فروشنده
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_user_id');
    }

    // [PHASE1] رابطه با خریدار
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_user_id');
    }

    // [PHASE1] رابطه با ایجادکننده سفارش
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'order_creator_id');
    }

    // [PHASE1] رابطه با کیورنسی دارایی
    public function assetCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'asset_currency_id');
    }

    // [PHASE1] رابطه با کیورنسی فیات
    public function fiatCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'fiat_currency_id');
    }

    // [PHASE1] رابطه با ادمین حل‌کننده اختلاف
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'resolved_by_admin_id');
    }

    // [PHASE1] رابطه با پیام‌های سفارش
    public function messages(): HasMany
    {
        return $this->hasMany(AdsOrderMessage::class, 'order_id');
    }

    // [PHASE1] تبدیل خودکار نوع‌ها
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'asset_amount' => 'decimal:8',
            'fiat_amount' => 'decimal:8',
            'price' => 'decimal:8',
            'fee_percent' => 'decimal:4',
            'fee_asset_amount' => 'decimal:8',
            'seller_locked_asset' => 'decimal:8',
            'payment_deadline_at' => 'datetime',
            'marked_paid_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'expired_at' => 'datetime',
            'disputed_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }
}
