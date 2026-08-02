<?php

use App\Domain\PaymentGateway\Http\Controllers\Web\InvoiceController;
use App\Domain\PaymentGateway\Http\Controllers\Web\PaymentChannelController;
use App\Domain\PaymentGateway\Http\Controllers\Web\PaymentCheckoutController;
use App\Domain\PaymentGateway\Http\Controllers\Web\PaymentGatewayController;
use App\Domain\PaymentGateway\Http\Controllers\Web\PaymentIncomeController;
use App\Domain\PaymentGateway\Http\Controllers\Web\QrCodeController;
use App\Domain\PaymentGateway\Http\Controllers\Web\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::get('payments/income', [PaymentIncomeController::class, 'index'])->name('payments.income.index');
Route::get('payments/income/overview', [PaymentIncomeController::class, 'overview'])->name('payments.income.overview');
Route::get('payments/income/invoices', [PaymentIncomeController::class, 'invoices'])->name('payments.income.invoices');

Route::get('payment-gateways/datatable', [PaymentGatewayController::class, 'datatable'])->name('payment-gateways.datatable');
Route::get('payment-gateways/create', [PaymentGatewayController::class, 'create'])->name('payment-gateways.create');
Route::post('payment-gateways', [PaymentGatewayController::class, 'store'])->name('payment-gateways.store');
Route::get('payment-gateways/{paymentGateway}/edit', [PaymentGatewayController::class, 'edit'])->name('payment-gateways.edit');
Route::put('payment-gateways/{paymentGateway}', [PaymentGatewayController::class, 'update'])->name('payment-gateways.update');
Route::delete('payment-gateways/{paymentGateway}', [PaymentGatewayController::class, 'destroy'])->name('payment-gateways.destroy');
Route::get('payment-gateways', [PaymentGatewayController::class, 'index'])->name('payment-gateways.index');

Route::get('payment-channels', [PaymentChannelController::class, 'index'])->name('payment-channels.index');
Route::post('payment-channels/sync-routing-policy', [PaymentChannelController::class, 'syncRoutingPolicy'])
    ->name('payment-channels.sync-policy');
Route::put('payment-channels/{code}', [PaymentChannelController::class, 'update'])->name('payment-channels.update');
Route::put('payment-channel-routes/{route}', [PaymentChannelController::class, 'updateRoute'])
    ->whereUuid('route')
    ->name('payment-channel-routes.update');

Route::get('invoices/export', [InvoiceController::class, 'export'])->name('invoices.export');
Route::get('invoices/datatable', [InvoiceController::class, 'datatable'])->name('invoices.datatable');
Route::get('invoices/{invoice}/checkout/channels', [PaymentCheckoutController::class, 'checkoutChannels'])
    ->whereUuid('invoice')
    ->name('invoices.checkout.channels');
Route::post('invoices/{invoice}/checkout/snap-token', [PaymentCheckoutController::class, 'createSnapToken'])
    ->whereUuid('invoice')
    ->name('invoices.checkout.snap-token');
Route::post('invoices/{invoice}/checkout/channel', [PaymentCheckoutController::class, 'createChannelCheckout'])
    ->whereUuid('invoice')
    ->name('invoices.checkout.channel');
Route::post('invoices/{invoice}/checkout/sync-status', [PaymentCheckoutController::class, 'syncInvoiceStatus'])
    ->whereUuid('invoice')
    ->name('invoices.checkout.sync-status');
Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->whereUuid('invoice')->name('invoices.show');
Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');

Route::get('subscriptions/datatable', [SubscriptionController::class, 'datatable'])->name('subscriptions.datatable');
Route::get('subscriptions/create', [SubscriptionController::class, 'create'])->name('subscriptions.create');
Route::get('subscriptions/{subscription}/edit', [SubscriptionController::class, 'edit'])->whereUuid('subscription')->name('subscriptions.edit');
Route::apiResource('subscriptions', SubscriptionController::class)->except(['create', 'edit'])->whereUuid('subscription');

// Local QR generator — replaces third-party api.qrserver.com fallback.
Route::get('qr', [QrCodeController::class, 'show'])->name('qr.show');
