<?php

namespace Addons\P2PTrading\Enums;

// [PHASE1] Enum نوع بازار P2P: ایران یا چین
// بازار ایران: دلار نقد + ارزهای خارجی جفت با ریال
// بازار چین: نیاز به بررسی بیشتر (Phase 2)
enum MarketType: string
{
    case Iran = 'iran';    // بازار ایران - دلار نقد و ارز خارجی/ریال
    case China = 'china';  // بازار چین - Phase 2
}
