<?php

namespace App\Domain\Vtual\Http\Controllers\API\V1\Webhooks;

use App\Domain\Vtual\Enums\YoutubeWebSubSkipReason;
use App\Domain\Vtual\Services\YoutubeWebSubNotificationService;
use App\Domain\Vtual\Services\YoutubeWebSubSubscriptionService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class YoutubeWebSubWebhookController extends Controller
{
    public function __construct(
        private readonly YoutubeWebSubNotificationService $notifications,
        private readonly YoutubeWebSubSubscriptionService $subscriptions,
    ) {}

    public function verify(Request $request): Response|SymfonyResponse
    {
        $mode = (string) $request->query('hub_mode', '');
        $topic = (string) $request->query('hub_topic', '');
        $challenge = (string) $request->query('hub_challenge', '');

        if ($challenge === '' || $topic === '') {
            return response('', SymfonyResponse::HTTP_BAD_REQUEST);
        }

        if (! in_array($mode, ['subscribe', 'unsubscribe'], true)) {
            return response('', SymfonyResponse::HTTP_FORBIDDEN);
        }

        if (! $this->subscriptions->attemptMarkVerified($topic, $mode)) {
            return response('', SymfonyResponse::HTTP_NOT_FOUND);
        }

        return response($challenge, SymfonyResponse::HTTP_OK)->header('Content-Type', 'text/plain');
    }

    public function notify(Request $request): Response|SymfonyResponse
    {
        $body = $request->getContent();
        if (! is_string($body)) {
            $body = '';
        }

        $maxBytes = $this->notifications->maxBodyBytes();
        if (strlen($body) > $maxBytes) {
            Log::warning('vtual.youtube_websub_notification_skipped', [
                'domain' => 'vtual',
                'component' => 'youtube_websub',
                'event' => YoutubeWebSubSkipReason::OversizedBody->value,
                'body_bytes' => strlen($body),
                'max_body_bytes' => $maxBytes,
            ]);

            return response('', SymfonyResponse::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        $topic = (string) $request->header('X-Hub-Topic', $request->query('hub_topic', ''));
        $signature = $request->header('X-Hub-Signature');

        $this->notifications->handleNotification(
            $topic,
            $body,
            is_string($signature) ? $signature : null,
        );

        return response('', SymfonyResponse::HTTP_NO_CONTENT);
    }
}
