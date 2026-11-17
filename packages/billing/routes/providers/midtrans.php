<?php

use App\Domain\PaymentGateway\Http\Controllers\API\V1\Payments\Midtrans\MidtransPaymentController;
use App\Domain\PaymentGateway\Http\Controllers\API\V1\Payments\Midtrans\MidtransWebhookController;
use Illuminate\Support\Facades\Route;

Route::match(['get', 'post'], 'webhook', MidtransWebhookController::class)->name('webhook');

Route::middleware('auth:api')->group(function (): void {
    Route::post('snap/token', [MidtransPaymentController::class, 'createSnapToken'])->name('snap.token');
    Route::post('transactions/charge', [MidtransPaymentController::class, 'charge'])->name('transactions.charge');
    Route::get('transactions/{orderId}', [MidtransPaymentController::class, 'show'])
        ->where('orderId', '[A-Za-z0-9._-]+')
        ->name('transactions.show');
    Route::post('transactions/{orderId}/cancel', [MidtransPaymentController::class, 'cancel'])
        ->where('orderId', '[A-Za-z0-9._-]+')
        ->name('transactions.cancel');
    Route::post('transactions/{orderId}/expire', [MidtransPaymentController::class, 'expire'])
        ->where('orderId', '[A-Za-z0-9._-]+')
        ->name('transactions.expire');
    Route::post('transactions/{orderId}/refund', [MidtransPaymentController::class, 'refund'])
        ->where('orderId', '[A-Za-z0-9._-]+')
        ->name('transactions.refund');
    Route::post('transactions/{orderId}/approve', [MidtransPaymentController::class, 'approve'])
        ->where('orderId', '[A-Za-z0-9._-]+')
        ->name('transactions.approve');
    Route::post('transactions/{orderId}/deny', [MidtransPaymentController::class, 'deny'])
        ->where('orderId', '[A-Za-z0-9._-]+')
        ->name('transactions.deny');
    Route::post('invoices/{invoice}/sync-status', [MidtransPaymentController::class, 'syncInvoiceStatus'])
        ->whereUuid('invoice')
        ->name('invoices.sync-status');
});
