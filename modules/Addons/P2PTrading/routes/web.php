<?php

// [PHASE1] روت‌های API ماژول P2P
// تمام روت‌ها نیاز به احراز هویت sanctum و نقش user دارن
// همچنین Guard پلاگین چک می‌کنه ماژول فعال باشه

use Addons\P2PTrading\Http\Controllers\Api\AdsController;
use Addons\P2PTrading\Http\Controllers\Api\OrderController;
use Addons\P2PTrading\Http\Controllers\Api\OrderMessageController;
use Addons\P2PTrading\Http\Controllers\Api\UserPaymentMethodController;
use Illuminate\Support\Facades\Route;
use Remotelywork\Installer\Http\Middleware\PluginGuard;


Route::middleware([PluginGuard::p2pTrading(), 'auth:sanctum', 'api_role:user'])->prefix('api/user/p2p')->group(function () {

    // [PHASE1] متدهای پرداخت کاربر
    Route::get('payment-methods', [UserPaymentMethodController::class, 'paymentMethods']);
    Route::apiResource('payment-accounts', UserPaymentMethodController::class);

    // [PHASE1] مارکتپلیس
    Route::get('marketplace', [AdsController::class, 'marketplace']);
    Route::get('marketplace/config', [AdsController::class, 'marketplaceConfig']);
    Route::get('marketplace/{id}', [AdsController::class, 'marketplaceAdShow']);

    // [PHASE1] آگهی‌ها
    Route::get('ads/highest-order-price', [AdsController::class, 'highestOrderPrice']);
    Route::get('ads/eligibility', [AdsController::class, 'eligibility']);
    Route::apiResource('ads', AdsController::class);
    Route::patch('ads/{id}/status-update', [AdsController::class, 'statusUpdate']);
    Route::get('ads/{id}/payment-methods', [AdsController::class, 'paymentMethods']);

    // [PHASE1] درخواست تاجر تاییدشده
    Route::get('verified-trader-application/status', [AdsController::class, 'verifiedTraderApplicationStatus']);
    Route::post('verified-trader-application/apply', [AdsController::class, 'verifiedTraderApply']);

    // [PHASE1] سفارشات
    Route::get('orders', [OrderController::class, 'index']);
    Route::get('orders/{id}', [OrderController::class, 'show']);
    Route::post('orders', [OrderController::class, 'store']);
    Route::post('orders/{id}/mark-paid', [OrderController::class, 'markPaid']);
    Route::patch('orders/{id}/payment-method', [OrderController::class, 'updatePaymentMethod']);
    Route::post('orders/{id}/release', [OrderController::class, 'release']);
    Route::post('orders/{id}/cancel', [OrderController::class, 'cancel']);
    Route::post('orders/{id}/dispute', [OrderController::class, 'dispute']);

    // [PHASE1] پیام‌های سفارش
    Route::get('orders/{id}/messages', [OrderMessageController::class, 'index']);
    Route::post('orders/{id}/messages', [OrderMessageController::class, 'store']);
});
