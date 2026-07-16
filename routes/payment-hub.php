<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use PaymentHub\Http\Controllers\PaymentCallbackController;

Route::prefix('payment-hub')->name('payment-hub.')->group(function (): void {
    Route::get('success', [PaymentCallbackController::class, 'success'])->name('success');
    Route::get('cancel', [PaymentCallbackController::class, 'cancel'])->name('cancel');
    Route::post('iyzico/callback', [PaymentCallbackController::class, 'iyzico'])
        ->name('iyzico.callback');
    Route::get('paypal/return', [PaymentCallbackController::class, 'paypal'])
        ->name('paypal.return');
    Route::post('paytr/callback', [PaymentCallbackController::class, 'paytr'])
        ->name('paytr.callback');
});
