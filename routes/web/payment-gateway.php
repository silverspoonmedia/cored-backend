<?php

use App\Domain\PaymentGateway\Http\Controllers\Web\InvoiceController;
use App\Domain\PaymentGateway\Http\Controllers\Web\PaymentCheckoutController;
use App\Domain\PaymentGateway\Http\Controllers\Web\PaymentGatewayController;
use App\Domain\PaymentGateway\Http\Controllers\Web\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::get('payment-gateways/datatable', [PaymentGatewayController::class, 'datatable'])->name('payment-gateways.datatable');
Route::get('payment-gateways', [PaymentGatewayController::class, 'index'])->name('payment-gateways.index');

Route::get('invoices/datatable', [InvoiceController::class, 'datatable'])->name('invoices.datatable');
Route::post('invoices/{invoice}/checkout/snap-token', [PaymentCheckoutController::class, 'createSnapToken'])
    ->whereUuid('invoice')
    ->name('invoices.checkout.snap-token');
Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->whereUuid('invoice')->name('invoices.show');
Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');

Route::get('subscriptions/datatable', [SubscriptionController::class, 'datatable'])->name('subscriptions.datatable');
Route::get('subscriptions/create', [SubscriptionController::class, 'create'])->name('subscriptions.create');
Route::get('subscriptions/{subscription}/edit', [SubscriptionController::class, 'edit'])->whereUuid('subscription')->name('subscriptions.edit');
Route::apiResource('subscriptions', SubscriptionController::class)->except(['create', 'edit'])->whereUuid('subscription');
