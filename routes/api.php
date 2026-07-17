<?php

use App\Http\Controllers\API\PingController;
use Illuminate\Support\Facades\Route;

Route::get('ping', PingController::class);
