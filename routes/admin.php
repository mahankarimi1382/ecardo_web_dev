<?php

use App\Http\Controllers\Backend\AdminDashboardController;
use App\Http\Controllers\Backend\AdminEscrowController;
use App\Http\Controllers\Backend\AdminGuaranteeController;
use App\Http\Controllers\Backend\AdminKycController;
use App\Http\Controllers\Backend\AdminLicenseController;
use App\Http\Controllers\Backend\AdminLoanController;
use App\Http\Controllers\Backend\AdminTourController;
use App\Http\Controllers\Backend\AdminVisaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| ECardo Comprehensive Admin Routes (روت‌های جامع پنل ادمین)
|--------------------------------------------------------------------------
|
| این فایل کلیه روت‌های مدیریتی، ممیزی و نظارتی پلتفرم eCardo را برای
| تمام ۲۴ سرویس تحت پوشش متمرکز می‌کند:
| احراز هویت KYC، تسهیلات و وام، ضمانت‌نامه و LC، فروشگاه لایسنس،
| خدمات ویزا و کنسولی، معاملات امانی اسکرو، تور و سفر، و داشبورد جامع.
|
*/

Route::group(['prefix' => 'admin', 'as' => 'admin.'], function () {

    // =========================================================================
    // 1. داشبورد جامع ادمین و مانیتورینگ کل سرویس‌ها (Dashboard)
    // =========================================================================
    Route::group(['prefix' => 'dashboard', 'as' => 'dashboard.'], function () {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('index');
        Route::get('quick-stats', [AdminDashboardController::class, 'quickStats'])->name('quick_stats');
        Route::get('urgent-alerts', [AdminDashboardController::class, 'urgentAlerts'])->name('urgent_alerts');
    });

    // =========================================================================
    // 2. احراز هویت و ممیزی مدارک کاربران (KYC Compliance)
    // =========================================================================
    Route::group(['prefix' => 'kyc', 'as' => 'kyc.'], function () {
        Route::get('/', [AdminKycController::class, 'index'])->name('index');
        Route::get('pending', [AdminKycController::class, 'pending'])->name('pending');
        Route::get('{id}', [AdminKycController::class, 'show'])->name('show');
        Route::get('{id}/document/{docType}/preview', [AdminKycController::class, 'previewDocument'])->name('document.preview');
        Route::post('{id}/approve', [AdminKycController::class, 'approve'])->name('approve');
        Route::post('{id}/reject', [AdminKycController::class, 'reject'])->name('reject');
        Route::post('{id}/hold', [AdminKycController::class, 'putOnHold'])->name('hold');
        Route::post('{id}/notes', [AdminKycController::class, 'storeNote'])->name('notes.store');
    });

    // =========================================================================
    // 3. مدیریت تسهیلات و اعتبارات (Loans & Credit)
    // =========================================================================
    Route::group(['prefix' => 'loans', 'as' => 'loans.'], function () {
        Route::get('/', [AdminLoanController::class, 'index'])->name('index');
        Route::get('{id}', [AdminLoanController::class, 'show'])->name('show');
        Route::post('{id}/assess', [AdminLoanController::class, 'assessAndOffer'])->name('assess');
        Route::post('{id}/collateral/verify', [AdminLoanController::class, 'verifyCollateral'])->name('collateral.verify');
        Route::post('{id}/disburse', [AdminLoanController::class, 'disburse'])->name('disburse');
        Route::get('{id}/installments', [AdminLoanController::class, 'installments'])->name('installments.index');
        Route::post('{id}/installments/{instId}/mark-paid', [AdminLoanController::class, 'markInstallmentPaid'])->name('installments.mark_paid');
        Route::post('{id}/reject', [AdminLoanController::class, 'reject'])->name('reject');
        Route::post('{id}/cancel', [AdminLoanController::class, 'cancel'])->name('cancel');
    });

    // =========================================================================
    // 4. مدیریت ضمانت‌نامه‌ها و اعتبارات اسنادی (Bank Guarantees & LC)
    // =========================================================================
    Route::group(['prefix' => 'guarantees', 'as' => 'guarantees.'], function () {
        Route::get('/', [AdminGuaranteeController::class, 'index'])->name('index');
        Route::get('{id}', [AdminGuaranteeController::class, 'show'])->name('show');
        Route::post('{id}/approve-review', [AdminGuaranteeController::class, 'approveReview'])->name('approve_review');
        Route::post('{id}/confirm-margin', [AdminGuaranteeController::class, 'confirmMargin'])->name('confirm_margin');
        Route::post('{id}/issue-sepam', [AdminGuaranteeController::class, 'issueSepam'])->name('issue_sepam');
        Route::post('{id}/handle-claim', [AdminGuaranteeController::class, 'handleClaim'])->name('handle_claim');
        Route::post('{id}/release', [AdminGuaranteeController::class, 'release'])->name('release');
        Route::post('{id}/reject', [AdminGuaranteeController::class, 'reject'])->name('reject');
        Route::post('{id}/cancel', [AdminGuaranteeController::class, 'cancel'])->name('cancel');
    });

    // =========================================================================
    // 5. فروشگاه لایسنس، انبار کلیدها و حل اختلاف (License Store & Vault)
    // =========================================================================
    Route::group(['prefix' => 'licenses', 'as' => 'licenses.'], function () {
        Route::get('/', [AdminLicenseController::class, 'index'])->name('index');
        Route::get('orders', [AdminLicenseController::class, 'orders'])->name('orders');
        Route::get('orders/{id}', [AdminLicenseController::class, 'showOrder'])->name('orders.show');
        Route::post('orders/{id}/resolve-dispute', [AdminLicenseController::class, 'resolveDispute'])->name('orders.resolve_dispute');
        Route::get('inventory', [AdminLicenseController::class, 'inventory'])->name('inventory.index');
        Route::post('inventory/import', [AdminLicenseController::class, 'importKeys'])->name('inventory.import');
        Route::post('keys/{keyId}/revoke', [AdminLicenseController::class, 'revokeKey'])->name('keys.revoke');
    });

    // =========================================================================
    // 6. خدمات ویزای الکترونیک و کنسولی (Visa & Consular Desk)
    // =========================================================================
    Route::group(['prefix' => 'visas', 'as' => 'visas.'], function () {
        Route::get('/', [AdminVisaController::class, 'index'])->name('index');
        Route::get('{id}', [AdminVisaController::class, 'show'])->name('show');
        Route::post('{id}/verify-docs', [AdminVisaController::class, 'verifyDocs'])->name('verify_docs');
        Route::post('{id}/update-embassy', [AdminVisaController::class, 'updateEmbassy'])->name('update_embassy');
        Route::post('{id}/issue', [AdminVisaController::class, 'issueVisa'])->name('issue');
        Route::post('{id}/reject', [AdminVisaController::class, 'reject'])->name('reject');
        Route::post('{id}/cancel', [AdminVisaController::class, 'cancel'])->name('cancel');
    });

    // =========================================================================
    // 7. معاملات امانی اسکرو و داوری اختلافات (Escrow & Arbitration)
    // =========================================================================
    Route::group(['prefix' => 'escrows', 'as' => 'escrows.'], function () {
        Route::get('/', [AdminEscrowController::class, 'index'])->name('index');
        Route::get('{id}', [AdminEscrowController::class, 'show'])->name('show');
        Route::post('{id}/arbitrate', [AdminEscrowController::class, 'arbitrate'])->name('arbitrate');
        Route::post('{id}/extend-inspection', [AdminEscrowController::class, 'extendInspection'])->name('extend_inspection');
        Route::post('{id}/force-release', [AdminEscrowController::class, 'forceRelease'])->name('force_release');
        Route::post('{id}/cancel', [AdminEscrowController::class, 'cancel'])->name('cancel');
    });

    // =========================================================================
    // 8. خدمات تور، سفر و رزرواسیون (Tours & Travel Bookings)
    // =========================================================================
    Route::group(['prefix' => 'tours', 'as' => 'tours.'], function () {
        Route::get('/', [AdminTourController::class, 'index'])->name('index');
        Route::post('/', [AdminTourController::class, 'store'])->name('store');
        Route::get('{id}', [AdminTourController::class, 'show'])->name('show');
        Route::post('{id}/update', [AdminTourController::class, 'update'])->name('update');
        Route::post('{id}/delete', [AdminTourController::class, 'delete'])->name('delete');
    });

    Route::group(['prefix' => 'travel-bookings', 'as' => 'travel_bookings.'], function () {
        Route::get('/', [AdminTourController::class, 'bookings'])->name('index');
        Route::get('{id}', [AdminTourController::class, 'showBooking'])->name('show');
        Route::post('{id}/confirm', [AdminTourController::class, 'confirmBooking'])->name('confirm');
        Route::post('{id}/cancel-refund', [AdminTourController::class, 'cancelAndRefundBooking'])->name('cancel_refund');
    });

    // =========================================================================
    // 9. روت‌های معاملات همتابه‌همتا (P2P Trading)
    // =========================================================================
    if (file_exists(__DIR__ . '/admin_p2p.php')) {
        require __DIR__ . '/admin_p2p.php';
    }

});
