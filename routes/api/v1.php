<?php

use App\Http\Controllers\API\V1\PlaygroundController;
use Illuminate\Support\Facades\Route;

Route::get('ping', [PlaygroundController::class, 'ping']);
