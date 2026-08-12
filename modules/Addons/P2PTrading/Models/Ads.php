<?php

namespace Addons\P2PTrading\Models;

use Addons\P2PTrading\Enums\AdsStatus;
use Addons\P2PTrading\Enums\AdsType;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// [PHASE1] مدل آگهی P2P
// هر آگهی نشان‌دهنده یک پیشنهاد خرید/فروش ارز توسط کاربر است
class Ads extends Model
{
    protected $table = 'p2p_ads';

    protected $guarded = [];

    // [PHASE1] رابطه با کاربر ایجادکننده آگهی
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // [PHASE1] رابطه با کیورنسی دارایی (ارز فروخته/خریداری شده)
    public function assetCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'asset_currency_id');
    }

    // [PHASE1] رابطه با کیورنسی فیات (ریال، یوان و...)
    public function fiatCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'fiat_currency_id');
    }

    // [PHASE1] رابطه با متدهای پرداخت مرتبط با این آگهی
    public function paymentMethods(): HasMany
    {
        return $this->hasMany(AdsPaymentMethod::class, 'ads_id');
    }

    // [PHASE1] رابطه با سفارشات این آگهی
    public function orders(): HasMany
    {
        return $this->hasMany(AdsOrder::class, 'ads_id');
    }

    // [PHASE1] تبدیل خودکار نوع‌ها
    protected function casts(): array
    {
        return [
            'type' => AdsType::class,
            'status' => AdsStatus::class,
            'min_amount' => 'decimal:8',
            'max_amount' => 'decimal:8',
            'total_amount' => 'decimal:8',
            'price' => 'decimal:8',
            'is_cash_dollar' => 'boolean',
            // [PHASE1] تبدیل market_type به Enum
            'market_type' => 'string', // Will use MarketType enum in queries
        ];
    }
}
