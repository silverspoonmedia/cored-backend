<?php

use App\Domain\PaymentGateway\Http\Controllers\Web\IpaymuReturnController;
use Illuminate\Support\Facades\Route;

/*
| iPaymu browser return/cancel — no web.auth: payer may land here from iPaymu
| in a fresh tab without the Monolith JWT cookie.
*/
Route::prefix('payment/ipaymu')->name('payment.ipaymu.')->group(function (): void {
    Route::get('return', [IpaymuReturnController::class, 'return'])->name('return');
    Route::get('cancel', [IpaymuReturnController::class, 'cancel'])->name('cancel');
});
