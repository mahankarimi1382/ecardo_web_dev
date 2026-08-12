<?php

namespace Addons\P2PTrading\Enums;

// [PHASE1] Enum وضعیت‌های سفارش P2P
enum OrderStatus: string
{
    case PendingPayment = 'pending_payment';  // منتظر پرداخت خریدار
    case Paid = 'paid';                       // خریدار پرداخت رو تایید کرد
    case Disputed = 'disputed';                // اختلاف باز شده
    case Completed = 'completed';              // تکمیل و تسویه شده
    case Cancelled = 'cancelled';            // لغو شده
    case Expired = 'expired';                  // منقضی شده (زمان پرداخت تمام شد)
}
