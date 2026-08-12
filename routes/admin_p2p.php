<?php

// [PHASE1] روت‌های ادمین ماژول P2P
// باگ رفع شده: روت‌های Settings داخل گروه Route قرار گرفتند
// نسخه قبلی: روت‌های Settings بیرون از گروه بودند (بعد از }); )

use App\Http\Controllers\Backend\P2pAdsController;
use App\Http\Controllers\Backend\P2pOrderController;
use App\Http\Controllers\Backend\P2pOrderMessageController;
use App\Http\Controllers\Backend\P2pPaymentMethodController;
use App\Http\Controllers\Backend\P2pSettingsController;

// [PHASE1-FIX] تمام روت‌های P2P داخل یک گروه هستند
Route::group(['prefix' => 'p2p', 'as' => 'p2p.'], function () {

    // -------------------------------------------------------
    // بخش 1: متدهای پرداخت P2P
    // پرمیشن: p2p-payment-method-manage
    // -------------------------------------------------------
    Route::get('payment-method', [P2pPaymentMethodController::class, 'index'])->name('payment-method.index');
    Route::get('payment-method/create', [P2pPaymentMethodController::class, 'create'])->name('payment-method.create');
    Route::post('payment-method', [P2pPaymentMethodController::class, 'store'])->name('payment-method.store');
    Route::get('payment-method/{id}/edit', [P2pPaymentMethodController::class, 'edit'])->name('payment-method.edit');
    Route::post('payment-method/{id}/update', [P2pPaymentMethodController::class, 'update'])->name('payment-method.update');
    Route::post('payment-method/{id}/delete', [P2pPaymentMethodController::class, 'delete'])->name('payment-method.delete');

    // -------------------------------------------------------
    // بخش 2: آگهی‌های P2P
    // پرمیشن: p2p-ads-manage (مشاهده), p2p-ads-approve (تایید/رد)
    // -------------------------------------------------------
    Route::get('ads', [P2pAdsController::class, 'index'])->name('ads.index');
    Route::get('ads/pending', [P2pAdsController::class, 'pending'])->name('ads.pending');
    Route::get('ads/{id}/action', [P2pAdsController::class, 'action'])->name('ads.action');
    Route::post('ads/action', [P2pAdsController::class, 'actionNow'])->name('ads.action.now');

    // -------------------------------------------------------
    // بخش 3: سفارشات P2P
    // پرمیشن: p2p-orders-manage (مشاهده), p2p-orders-resolve (حل اختلاف)
    // -------------------------------------------------------
    Route::get('orders', [P2pOrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{id}', [P2pOrderController::class, 'show'])->name('orders.show');
    Route::post('orders/{id}/resolve', [P2pOrderController::class, 'resolve'])->name('orders.resolve');

    // [PHASE1] پیام‌های سفارش (چت ادمین با کاربران)
    // پرمیشن: p2p-orders-chat-manage
    Route::get('orders/{id}/messages', [P2pOrderMessageController::class, 'index'])->name('orders.messages.index');
    Route::post('orders/{id}/messages', [P2pOrderMessageController::class, 'store'])->name('orders.messages.store');

    // -------------------------------------------------------
    // بخش 4: تنظیمات P2P
    // [PHASE1-FIX] این روت‌ها قبلاً بیرون از گروه بودند!
    // پرمیشن: p2p-ads-manage
    // -------------------------------------------------------
    Route::get('settings', [P2pSettingsController::class, 'index'])->name('settings.index');
    Route::post('settings', [P2pSettingsController::class, 'update'])->name('settings.update');

}); // [PHASE1-FIX] بسته شدن گروه Route - تمام روت‌ها داخل گروه هستند
