<?php

namespace App\Domain\Vtual\Http\Controllers\API\V1\Webhooks;

use App\Domain\Vtual\Enums\TwitchEventSubSkipReason;
use App\Domain\Vtual\Services\TwitchEventSubNotificationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Twitch EventSub webhook callback. Single endpoint handles all message
 * types: `webhook_callback_verification` (challenge echo), `notification`
 * (signed events) and `revocation`. Twitch requires a 2xx response within the
 * grace period; non-2xx triggers redelivery.
 */
class TwitchEventSubWebhookController extends Controller
{
    public function __construct(
        private readonly TwitchEventSubNotificationService $notifications,
    ) {}

    public function handle(Request $request): Response|SymfonyResponse
    {
        $body = $request->getContent();
        if (! is_string($body)) {
            $body = '';
        }

        $maxBytes = $this->notifications->maxBodyBytes();
        if (strlen($body) > $maxBytes) {
            Log::warning('vtual.twitch_eventsub_notification_skipped', [
                'domain' => 'vtual',
                'component' => 'twitch_eventsub',
                'event' => TwitchEventSubSkipReason::OversizedBody->value,
                'body_bytes' => strlen($body),
                'max_body_bytes' => $maxBytes,
            ]);

            return response('', SymfonyResponse::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        $payload = json_decode($body, true);
        if (! is_array($payload)) {
            $payload = [];
        }

        $headers = [
            'message_id' => $request->header('Twitch-Eventsub-Message-Id'),
            'message_timestamp' => $request->header('Twitch-Eventsub-Message-Timestamp'),
            'message_signature' => $request->header('Twitch-Eventsub-Message-Signature'),
            'message_type' => $request->header('Twitch-Eventsub-Message-Type'),
        ];

        $result = $this->notifications->handleNotification($payload, $body, $headers);

        // Verification must echo the challenge verbatim (200 text/plain).
        if ($result->isVerification()) {
            return response($result->challenge ?? '', SymfonyResponse::HTTP_OK)
                ->header('Content-Type', 'text/plain');
        }

        return response('', SymfonyResponse::HTTP_NO_CONTENT);
    }
}
