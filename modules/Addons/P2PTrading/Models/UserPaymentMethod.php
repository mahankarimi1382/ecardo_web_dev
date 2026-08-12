<?php

namespace Addons\P2PTrading\Models;

use Addons\P2PTrading\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// [PHASE1] مدل متدهای پرداخت کاربر
// هر کاربر می‌تونه برای هر متد پرداخت، اطلاعات حساب خودش رو ثبت کنه
// مثال: کاربر شماره کارت بانک ملت خودش رو برای متد "کارت به کارت" ثبت می‌کنه
class UserPaymentMethod extends Model
{
    protected $table = 'p2p_user_payment_methods';

    protected $guarded = [];

    // [PHASE1] رابطه با متد پرداخت پایه
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    // [PHASE1] رابطه با کاربر
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // [PHASE1] فیلدهای داینامیک (JSON) - شامل اطلاعات واقعی حساب کاربر
    protected function casts(): array
    {
        return [
            'fields' => 'json',
        ];
    }
}
