<?php

namespace Addons\P2PTrading\Models;

use Addons\P2PTrading\Enums\OrderMessageActorType;
use App\Models\Admin;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// [PHASE1] مدل پیام‌های سفارش P2P
// پیام‌ها می‌تونن از طرف کاربر (خریدار/فروشنده) یا ادمین باشند
// شامل فایل پیوست هم می‌تونن باشن
class AdsOrderMessage extends Model
{
    protected $table = 'p2p_ads_order_messages';

    protected $guarded = [];

    // [PHASE1] رابطه با سفارش
    public function order(): BelongsTo
    {
        return $this->belongsTo(AdsOrder::class, 'order_id');
    }

    // [PHASE1] رابطه با کاربر (وقتی فرستنده کاربر است)
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    // [PHASE1] رابطه با ادمین (وقتی فرستنده ادمین است)
    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'sender_id');
    }

    // [PHASE1] متد پلی‌مورفیک: بر اساس sender_type مدل درست رو برمی‌گردونه
    public function sender(): User|Admin|null
    {
        return $this->sender_type === OrderMessageActorType::Admin ? $this->admin : $this->user;
    }

    protected function casts(): array
    {
        return [
            'sender_type' => OrderMessageActorType::class,
        ];
    }
}
