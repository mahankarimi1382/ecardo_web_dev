<?php

namespace Addons\P2PTrading\Enums;

// [PHASE1] Enum نوع آگهی: خرید یا فروش
enum AdsType: string
{
    case Buy = 'buy';    // آگهی خرید: کاربر می‌خواد ارز بخره
    case Sell = 'sell';  // آگهی فروش: کاربر می‌خواد ارز بفروشه
}
