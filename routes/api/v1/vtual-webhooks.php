<?php

use App\Domain\Vtual\Http\Controllers\API\V1\Webhooks\TwitchEventSubWebhookController;
use App\Domain\Vtual\Http\Controllers\API\V1\Webhooks\YoutubeWebSubWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('vtual/webhooks/youtube-websub', [YoutubeWebSubWebhookController::class, 'verify'])
    ->name('vtual.webhooks.youtube-websub');

Route::post('vtual/webhooks/youtube-websub', [YoutubeWebSubWebhookController::class, 'notify'])
    ->middleware('throttle:120,1');

Route::post('vtual/webhooks/twitch-eventsub', [TwitchEventSubWebhookController::class, 'handle'])
    ->name('vtual.webhooks.twitch-eventsub')
    ->middleware('throttle:120,1');
