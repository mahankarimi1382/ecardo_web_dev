<?php

namespace Addons\P2PTrading\Enums;

// [PHASE1] Enum نوع فرستنده پیام: کاربر یا ادمین
enum OrderMessageActorType: string
{
    case User = 'user';    // پیام از کاربر (خریدار/فروشنده)
    case Admin = 'admin';  // پیام از ادمین پشتیبانی
}
