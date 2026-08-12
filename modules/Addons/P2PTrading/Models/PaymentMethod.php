<?php

namespace Addons\P2PTrading\Models;

use App\Models\Currency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// [PHASE1] مدل متدهای پرداخت P2P
// متدهایی مثل انتقال بانکی، کارت به کارت، عیان‌الرحمن و...
// هر متد به یک کیورنسی فیات متصل است
// فیلدهای داینامیک: fields JSON شامل تعریف فیلدهایی که کاربر باید پر کنه
// مثال: [{"name":"card_number","type":"text","validation":"required"}]
class PaymentMethod extends Model
{
    protected $table = 'p2p_payment_methods';

    protected $guarded = [];

    // [PHASE1] رابطه با کیورنسی فیات
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    // [PHASE1] فیلدهای داینامیک متد پرداخت (JSON)
    protected function casts(): array
    {
        return [
            'fields' => 'json',
            'is_active' => 'boolean',
        ];
    }
}
