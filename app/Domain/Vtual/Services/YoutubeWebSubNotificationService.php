<?php

namespace App\Domain\Vtual\Services;

use App\Domain\Vtual\Enums\ContentFetchPoolSource;
use App\Domain\Vtual\Enums\YoutubeWebSubSkipReason;
use App\Domain\Vtual\Integrations\Youtube\YoutubeAtomFeedParser;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\WebsubSubscription;
use App\Domain\Vtual\Services\Dto\YoutubeWebSubHandleResult;
use Illuminate\Support\Facades\Log;

/**
 * Parse WebSub Atom notifications and enqueue video ids.
 */
class YoutubeWebSubNotificationService
{
    public function __construct(
        private readonly ContentFetchPoolService $contentPool,
        private readonly YoutubeWebSubSubscriptionService $subscriptions,
        private readonly YoutubeAtomFeedParser $parser,
        private readonly ContentService $contents,
    ) {}

    public function verifySignature(string $body, ?string $signatureHeader, string $secret): bool
    {
        if ($signatureHeader === null || trim($signatureHeader) === '') {
            return false;
        }

        $sha256Valid = false;
        $sha1Valid = false;

        foreach (array_map('trim', explode(',', $signatureHeader)) as $part) {
            if ($part === '') {
                continue;
            }

            if (str_starts_with($part, 'sha256=')) {
                $digest = substr($part, strlen('sha256='));
                $expectedHex = hash_hmac('sha256', $body, $secret);
                $expectedBase64 = base64_encode(hash_hmac('sha256', $body, $secret, true));
                if (hash_equals($expectedHex, $digest) || hash_equals($expectedBase64, $digest)) {
                    $sha256Valid = true;
                }
            }

            if (str_starts_with($part, 'sha1=')) {
                $digest = substr($part, strlen('sha1='));
                $expectedHex = hash_hmac('sha1', $body, $secret);
                $expectedBase64 = base64_encode(hash_hmac('sha1', $body, $secret, true));
                if (hash_equals($expectedHex, $digest) || hash_equals($expectedBase64, $digest)) {
                    $sha1Valid = true;
                }
            }
        }

        return $sha256Valid || $sha1Valid;
    }

    public function handleNotification(string $topic, string $body, ?string $signatureHeader): YoutubeWebSubHandleResult
    {
        $topic = trim($topic);
        $bodyBytes = strlen($body);

        if ($body === '') {
            $this->logSkip(YoutubeWebSubSkipReason::EmptyBody, $topic, $bodyBytes);

            return new YoutubeWebSubHandleResult(skipReason: YoutubeWebSubSkipReason::EmptyBody);
        }

        $subscription = $this->subscriptions->resolveSubscriptionForTopic($topic);
        if ($subscription === null || $subscription->channel === null) {
            $this->logSkip(YoutubeWebSubSkipReason::UnknownTopic, $topic, $bodyBytes);

            return new YoutubeWebSubHandleResult(skipReason: YoutubeWebSubSkipReason::UnknownTopic);
        }

        if (! $this->verifySignature($body, $signatureHeader, $subscription->secret)) {
            $this->recordSignatureFailure($subscription, $topic, $bodyBytes);

            return new YoutubeWebSubHandleResult(skipReason: YoutubeWebSubSkipReason::BadSignature);
        }

        $videoIds = $this->parser->extractVideoIds($body)->all();
        $deletedVideoIds = $this->parser->extractDeletedVideoIds($body)->all();

        if ($videoIds === [] && $deletedVideoIds === [] && trim($body) !== '') {
            $this->logSkip(
                YoutubeWebSubSkipReason::ParseFailed,
                $topic,
                $bodyBytes,
                (string) $subscription->channel_id,
            );

            return new YoutubeWebSubHandleResult(skipReason: YoutubeWebSubSkipReason::ParseFailed);
        }

        $subscription->forceFill(['last_notify_at' => now(), 'last_error' => null])->save();

        $channel = $subscription->channel;
        $enqueued = 0;

        foreach ($videoIds as $videoId) {
            $this->contentPool->enqueue($channel, $videoId, ContentFetchPoolSource::Websub);
            $enqueued++;
        }

        foreach ($deletedVideoIds as $deletedId) {
            $this->contents->markUnavailable($channel, $deletedId);
        }

        return new YoutubeWebSubHandleResult(
            enqueued: $enqueued,
            videoIds: $videoIds,
        );
    }

    public function resolveChannelForTopic(string $topic): ?Channel
    {
        return $this->subscriptions->resolveSubscriptionForTopic($topic)?->channel;
    }

    public function maxBodyBytes(): int
    {
        return max(1, (int) setting_config('vtual.websub.max_body_bytes', 1_048_576));
    }

    private function recordSignatureFailure(WebsubSubscription $subscription, string $topic, int $bodyBytes): void
    {
        $subscription->forceFill([
            'last_error' => mb_substr('WebSub signature verification failed', 0, 2000),
        ])->save();

        $this->logSkip(
            YoutubeWebSubSkipReason::BadSignature,
            $topic,
            $bodyBytes,
            (string) $subscription->channel_id,
        );
    }

    private function logSkip(
        YoutubeWebSubSkipReason $reason,
        string $topic,
        int $bodyBytes,
        ?string $channelId = null,
    ): void {
        Log::warning('vtual.youtube_websub_notification_skipped', array_filter([
            'domain' => 'vtual',
            'component' => 'youtube_websub',
            'event' => $reason->value,
            'topic_host' => parse_url($topic, PHP_URL_HOST),
            'body_bytes' => $bodyBytes,
            'channel_id' => $channelId,
        ], fn (mixed $value): bool => $value !== null && $value !== ''));
    }
}
