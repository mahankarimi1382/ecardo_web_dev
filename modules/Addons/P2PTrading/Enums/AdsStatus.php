<?php

namespace Addons\P2PTrading\Enums;

// [PHASE1] Enum وضعیت‌های آگهی P2P
enum AdsStatus: string
{
    case Pending = 'pending';      // منتظر تایید ادمین
    case Active = 'active';        // فعال و قابل معامله
    case Inactive = 'inactive';    // غیرفعال توسط کاربر
    case Completed = 'completed';  // تکمیل شده (تمام لیقویتی فروخته شد)
    case Cancelled = 'cancelled';  // رد شده یا لغو شده
}
