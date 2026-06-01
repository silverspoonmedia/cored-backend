<?php

use App\Http\Controllers\API\PlaygroundController;
use Illuminate\Support\Facades\Route;

Route::get('ping', [PlaygroundController::class, 'ping']);
