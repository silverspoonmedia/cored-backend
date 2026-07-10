<?php

use App\Domain\PaymentGateway\Http\Controllers\API\V1\InvoiceController;
use App\Domain\PaymentGateway\Http\Controllers\API\V1\PaymentGatewayController;
use App\Domain\PaymentGateway\Http\Controllers\API\V1\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->group(function (): void {
    Route::apiResource('payment-gateways', PaymentGatewayController::class);
    Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->whereUuid('invoice')->name('invoices.show');
    Route::apiResource('subscriptions', SubscriptionController::class);
});

Route::prefix('payments/providers/midtrans')->name('payments.providers.midtrans.')->group(function (): void {
    require __DIR__.'/payment-gateway/providers/midtrans.php';
});

Route::prefix('payments/providers/ipaymu')->name('payments.providers.ipaymu.')->group(function (): void {
    require __DIR__.'/payment-gateway/providers/ipaymu.php';
});
