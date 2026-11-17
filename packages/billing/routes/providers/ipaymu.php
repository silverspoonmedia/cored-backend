<?php

use App\Domain\PaymentGateway\Http\Controllers\API\V1\Payments\Ipaymu\IpaymuPaymentController;
use App\Domain\PaymentGateway\Http\Controllers\API\V1\Payments\Ipaymu\IpaymuWebhookController;
use Illuminate\Support\Facades\Route;

Route::match(['get', 'post'], 'webhook', IpaymuWebhookController::class)->name('webhook');

Route::middleware('auth:api')->group(function (): void {
    Route::post('snap/token', [IpaymuPaymentController::class, 'createSnapToken'])->name('snap.token');
    Route::post('transactions/charge', [IpaymuPaymentController::class, 'charge'])->name('transactions.charge');
    Route::get('transactions/{orderId}', [IpaymuPaymentController::class, 'show'])
        ->where('orderId', '[A-Za-z0-9._-]+')
        ->name('transactions.show');
    Route::post('invoices/{invoice}/sync-status', [IpaymuPaymentController::class, 'syncInvoiceStatus'])
        ->whereUuid('invoice')
        ->name('invoices.sync-status');
});
