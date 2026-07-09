<?php

use App\Domain\Playground\Http\Controllers\API\PlaygroundController;
use Illuminate\Support\Facades\Route;

Route::get('ping', [PlaygroundController::class, 'ping']);

require __DIR__.'/api/playground.php';
