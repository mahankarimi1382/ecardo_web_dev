# P2P Module Phase 1 - Patch Log

**Date**: 2026-08-12  
**Version**: v1.1  
**Author**: eCardo Dev Team

---

## باگ‌های رفع‌شده

### 1. 🔴 Syntax Error - P2pAdsService.php خط 27
**فایل**: `modules/Addons/P2PTrading/Services/P2pAdsService.php`
**مشکل**: پرانتز `)` بسته نشده در `->when()` برای فیلتر `is_cash_dollar`
**قبل**:
```php
->when(isset($filters['is_cash_dollar']), fn($q) => $q->where('is_cash_dollar', (int)$filters['is_cash_dollar'])
```
**بعد**:
```php
->when(isset($filters['is_cash_dollar']), fn($q) => $q->where('is_cash_dollar', (int) $filters['is_cash_dollar']))
```

### 2. 🔴 Syntax Error - P2pSettingsController.php خط 48
**فایل**: `app/Http/Controllers/Backend/P2pSettingsController.php`
**مشکل**: براکت اشتباه `)` به جای `]` + تایپو `آپسirt`
**قبل**:
```php
// [PHASE1-PATCH) آپسirt با UPSERT
$allowedFields = [
    ...
);
```
**بعد**:
```php
// [PHASE1-FIX] UPSERT با آرایه صحیح
$allowedFields = [
    ...
];
```

### 3. 🔴 Route Closure Bug - admin.php
**فایل**: `routes/admin.php`
**مشکل**: روت‌های Settings بیرون از گروه `Route::group()` قرار داشتند
**قبل**:
```php
Route::group(['prefix' => 'p2p', 'as' => 'p2p.'], function () {
    // ... ads, orders ...
});

// [PHASE1-PATCH] P2P Settings  <-- بیرون از گروه!
Route::get('settings', ...);
Route::post('settings', ...);
```
**بعد**: تمام روت‌ها داخل گروه `Route::group()` با پرافیکس `p2p.`

### 4. 🟡 Duplicate Filters - P2pAdsService.php
**مشکل**: فیلترهای `fiat_currency` و `asset_currency` دو بار اعمال میشدند (خطوط 20-21 و 29-30)
**رفع**: فیلترها یک‌بار اعمال شدند

### 5. 🟡 Typo in getNotificationShortcodes
**فایل**: `P2pAdsService.php`
**مشکل**: `[essage]]` به جای `[[message]]`
**رفع**: اصلاح شد

### 6. 🟡 Missing MarketType Enum
**مشکل**: فیلد `market_type` استفاده میشد ولی Enum مربوطه وجود نداشت
**رفع**: ایجاد `Enums/MarketType.php`

---

## امکانات جدید Phase 1

### 1. فیلد `market_type` در جدول آگهی‌ها
- مقادیر: `iran` / `china`
- فیلتر در ادمین و API مارکتپلیس

### 2. فیلد `is_cash_dollar` در جدول آگهی‌ها
- بولین: دلار نقد یا اسکناسی
- فیلتر در ادمین و API مارکتپلیس

### 3. تنظیمات جدید
- `market_iran_enabled`: فعال بودن بازار ایران
- `market_china_enabled`: فعال بودن بازار چین
- `cash_dollar_enabled`: فعال بودن دلار نقد

### 4. Migration‌های P2P
- 7 فایل migration برای تمام جداول P2P
- 1 seeder برای تنظیمات اولیه

---

## ساختار فایل‌ها

```
modules/Addons/P2PTrading/
├── plugin.json
├── P2PTradingServiceProvider.php
├── Enums/
│   ├── AdsStatus.php
│   ├── AdsType.php
│   ├── MarketType.php          [PHASE1 جدید]
│   ├── OrderStatus.php
│   └── OrderMessageActorType.php
├── Models/
│   ├── Ads.php
│   ├── AdsOrder.php
│   ├── AdsOrderMessage.php
│   ├── AdsPaymentMethod.php
│   ├── PaymentMethod.php
│   └── UserPaymentMethod.php
├── Services/
│   ├── P2pAdsService.php       [PHASE1 رفع باگ]
│   ├── AdsService.php
│   └── OrderService.php
├── Http/
│   └── Controllers/Api/
│       ├── AdsController.php
│       ├── OrderController.php
│       ├── OrderMessageController.php
│       └── UserPaymentMethodController.php
└── routes/
    └── web.php

app/Http/Controllers/Backend/
├── P2pAdsController.php
├── P2pOrderController.php
├── P2pOrderMessageController.php
├── P2pPaymentMethodController.php
└── P2pSettingsController.php  [PHASE1 رفع باگ]

routes/
└── admin_p2p.php              [PHASE1 رفع باگ روت]

database/
├── migrations/
│   ├── 2026_08_12_000001_create_p2p_payment_methods_table.php
│   ├── 2026_08_12_000002_create_p2p_user_payment_methods_table.php
│   ├── 2026_08_12_000003_create_p2p_ads_table.php
│   ├── 2026_08_12_000004_create_p2p_ads_payment_methods_table.php
│   ├── 2026_08_12_000005_create_p2p_ads_orders_table.php
│   ├── 2026_08_12_000006_create_p2p_ads_order_messages_table.php
│   └── 2026_08_12_000007_add_market_type_cash_dollar_to_p2p_ads_table.php
└── seeders/
    └── P2PSettingsSeeder.php
```
