<?php

use App\Domain\PaymentGateway\Http\Controllers\API\V1\InvoiceChannelCheckoutController;
use App\Domain\PaymentGateway\Http\Controllers\API\V1\InvoiceCheckoutChannelsController;
use App\Domain\PaymentGateway\Http\Controllers\API\V1\InvoiceController;
use App\Domain\PaymentGateway\Http\Controllers\API\V1\PaymentChannelController;
use App\Domain\PaymentGateway\Http\Controllers\API\V1\PaymentChannelRouteController;
use App\Domain\PaymentGateway\Http\Controllers\API\V1\PaymentGatewayController;
use App\Domain\PaymentGateway\Http\Controllers\API\V1\PaymentIncomeController;
use App\Domain\PaymentGateway\Http\Controllers\API\V1\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->group(function (): void {
    Route::apiResource('payment-gateways', PaymentGatewayController::class);
    Route::get('invoices/export', [InvoiceController::class, 'export'])->name('invoices.export');
    Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('payments/income', [PaymentIncomeController::class, 'overview'])
        ->middleware('permission:view invoice')
        ->name('payments.income.overview');
    Route::get('payments/income/invoices', [PaymentIncomeController::class, 'invoices'])
        ->middleware('permission:view invoice')
        ->name('payments.income.invoices');
    Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->whereUuid('invoice')->name('invoices.show');
    Route::post('invoices/{invoice}/channel-checkout', InvoiceChannelCheckoutController::class)
        ->whereUuid('invoice')
        ->middleware('permission:initiate payment')
        ->name('invoices.channel-checkout');
    Route::get('invoices/{invoice}/checkout-channels', InvoiceCheckoutChannelsController::class)
        ->whereUuid('invoice')
        ->name('invoices.checkout-channels');
    Route::get('payment-channels', [PaymentChannelController::class, 'index'])
        ->middleware('permission:view invoice')
        ->name('payment-channels.index');
    Route::get('payment-channels/{code}', [PaymentChannelController::class, 'show'])
        ->middleware('permission:view invoice')
        ->name('payment-channels.show');
    Route::put('payment-channels/{code}', [PaymentChannelController::class, 'update'])
        ->middleware('permission:manage payment gateway')
        ->name('payment-channels.update');
    Route::put('payment-channel-routes/{route}', [PaymentChannelRouteController::class, 'update'])
        ->whereUuid('route')
        ->middleware('permission:manage payment gateway')
        ->name('payment-channel-routes.update');
    Route::post('payment-channels/sync-policy', [PaymentChannelController::class, 'syncRoutingPolicy'])
        ->middleware('permission:manage payment gateway')
        ->name('payment-channels.sync-policy');
    Route::apiResource('subscriptions', SubscriptionController::class);
});

Route::prefix('payments/providers/midtrans')->name('payments.providers.midtrans.')->group(function (): void {
    require __DIR__.'/payment-gateway/providers/midtrans.php';
});

Route::prefix('payments/providers/ipaymu')->name('payments.providers.ipaymu.')->group(function (): void {
    require __DIR__.'/payment-gateway/providers/ipaymu.php';
});
