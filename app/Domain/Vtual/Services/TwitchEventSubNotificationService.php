<?php

namespace App\Domain\Vtual\Services;

use App\Domain\Vtual\Enums\IncidentKind;
use App\Domain\Vtual\Enums\IncidentSeverity;
use App\Domain\Vtual\Enums\PlatformKey;
use App\Domain\Vtual\Enums\TwitchEventSubSkipReason;
use App\Domain\Vtual\Enums\TwitchEventSubStatus;
use App\Domain\Vtual\Enums\TwitchEventSubType;
use App\Domain\Vtual\Exceptions\VtualException;
use App\Domain\Vtual\Integrations\PlatformApiGateway;
use App\Domain\Vtual\Models\EventsubSubscription;
use App\Domain\Vtual\Services\Dto\TwitchEventSubHandleResult;
use App\Domain\Vtual\Support\VtualChannelRealtime;
use App\Domain\Vtual\Support\VtualIncidentRecorder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Handle Twitch EventSub webhook deliveries:
 *  - `webhook_callback_verification` → mark row Active, echo challenge.
 *  - `notification` → verify HMAC, dedupe by message id, enqueue a channel
 *    snapshot refresh (channel.update / stream.online / stream.offline all
 *    coalesce into the channel fetch pool → realtime snapshot push).
 *  - `revocation` → Twitch revoked the subscription (callback failed, banned,
 *    etc.) — row becomes Unsubscribed and renewal will recreate it.
 *
 * Signature scheme: `Twitch-Eventsub-Message-Signature: sha256=<hex>` where
 * HMAC-SHA256 key = subscription secret and message =
 * message_id + message_timestamp + raw body.
 */
class TwitchEventSubNotificationService
{
    public function __construct(
        private readonly TwitchEventSubSubscriptionService $subscriptions,
        private readonly ChannelFetchPoolService $channelPool,
        private readonly PlatformApiGateway $gateway,
        private readonly TwitchLiveStateService $liveState,
        private readonly VtualChannelRealtime $realtime,
    ) {}

    public function verifySignature(
        string $messageId,
        string $timestamp,
        string $body,
        ?string $signatureHeader,
        string $secret,
    ): bool {
        if ($signatureHeader === null || trim($signatureHeader) === '') {
            return false;
        }

        $part = trim($signatureHeader);
        if (! str_starts_with($part, 'sha256=')) {
            return false;
        }

        $digest = substr($part, strlen('sha256='));
        $expected = hash_hmac('sha256', $messageId.$timestamp.$body, $secret);

        return hash_equals($expected, $digest);
    }

    /**
     * @param  array<string, mixed>  $payload  Decoded JSON body (may be [] when unparseable)
     * @param  array<string, string|null>  $headers  Subset of Twitch-Eventsub-* headers
     */
    public function handleNotification(
        array $payload,
        string $rawBody,
        array $headers,
    ): TwitchEventSubHandleResult {
        $bodyBytes = strlen($rawBody);
        $messageType = (string) ($headers['message_type'] ?? '');

        if ($rawBody === '' || $payload === []) {
            $this->logSkip(TwitchEventSubSkipReason::EmptyBody, $messageType, $bodyBytes);

            return new TwitchEventSubHandleResult(skipReason: TwitchEventSubSkipReason::EmptyBody);
        }

        $twitchSubscriptionId = (string) ($payload['subscription']['id'] ?? '');
        if ($twitchSubscriptionId === '') {
            $this->logSkip(TwitchEventSubSkipReason::ParseFailed, $messageType, $bodyBytes);

            return new TwitchEventSubHandleResult(skipReason: TwitchEventSubSkipReason::ParseFailed);
        }

        // Verification challenge may arrive before the row is resolvable by
        // twitch_subscription_id (e.g. across queue re-delivery) — accept and
        // echo regardless; the row flip is best-effort.
        if ($messageType === 'webhook_callback_verification') {
            $this->subscriptions->attemptMarkVerified($twitchSubscriptionId);

            return new TwitchEventSubHandleResult(challenge: (string) ($payload['challenge'] ?? ''));
        }

        $subscription = $this->subscriptions->resolveByTwitchSubscriptionId($twitchSubscriptionId);
        if ($subscription === null || $subscription->channel === null) {
            $this->logSkip(TwitchEventSubSkipReason::UnknownSubscription, $messageType, $bodyBytes, $twitchSubscriptionId);

            return new TwitchEventSubHandleResult(skipReason: TwitchEventSubSkipReason::UnknownSubscription);
        }

        if ($messageType === 'revocation') {
            $revocationStatus = (string) ($payload['subscription']['status'] ?? '');

            $subscription->forceFill([
                'status' => TwitchEventSubStatus::Unsubscribed,
                'lease_expires_at' => null,
                'last_error' => mb_substr('revoked by twitch: '.$revocationStatus, 0, 500),
            ])->save();
            Log::info('vtual.eventsub_revoked', [
                'twitch_subscription_id' => $twitchSubscriptionId,
                'channel_id' => (string) $subscription->channel_id,
            ]);

            // A revocation is Twitch telling us the webhook is dead — the
            // loudest signal in this service, and the one an operator must act
            // on before the next stream goes unrecorded.
            app(VtualIncidentRecorder::class)->record(
                kind: IncidentKind::Eventsub,
                severity: IncidentSeverity::Critical,
                title: 'Twitch EventSub revoked ('.$this->eventTypeValue($subscription).')',
                detail: 'Revoked by Twitch'.($revocationStatus !== '' ? ': '.$revocationStatus : ''),
                context: [
                    'twitch_subscription_id' => $twitchSubscriptionId,
                    'revocation_status' => $revocationStatus,
                ],
                platformId: $subscription->channel->platform_id !== null
                    ? (string) $subscription->channel->platform_id
                    : null,
                channelId: (string) $subscription->channel_id,
            );

            return new TwitchEventSubHandleResult;
        }

        if ($messageType !== 'notification') {
            $this->logSkip(TwitchEventSubSkipReason::ParseFailed, $messageType, $bodyBytes, $twitchSubscriptionId);

            return new TwitchEventSubHandleResult(skipReason: TwitchEventSubSkipReason::ParseFailed);
        }

        $messageId = (string) ($headers['message_id'] ?? '');
        $timestamp = (string) ($headers['message_timestamp'] ?? '');

        if (! $this->verifySignature($messageId, $timestamp, $rawBody, $headers['message_signature'] ?? null, (string) $subscription->secret)) {
            $subscription->forceFill([
                'last_error' => mb_substr('EventSub signature verification failed', 0, 2000),
            ])->save();
            $this->logSkip(TwitchEventSubSkipReason::BadSignature, $messageType, $bodyBytes, $twitchSubscriptionId);

            // Bad signature means the delivery was not from Twitch, or the row
            // secret drifted from the one Twitch holds. Either way deliveries
            // are being dropped, which is a warning rather than a hard failure:
            // the renewal job re-subscribes and re-issues the secret.
            app(VtualIncidentRecorder::class)->record(
                kind: IncidentKind::EventsubNotification,
                severity: IncidentSeverity::Warning,
                title: 'Twitch EventSub signature verification failed ('.$this->eventTypeValue($subscription).')',
                detail: 'Inbound notification rejected: HMAC did not match the stored subscription secret.',
                context: [
                    'twitch_subscription_id' => $twitchSubscriptionId,
                    'message_id' => $messageId,
                    'message_type' => $messageType,
                    'body_bytes' => $bodyBytes,
                ],
                platformId: $subscription->channel->platform_id !== null
                    ? (string) $subscription->channel->platform_id
                    : null,
                channelId: (string) $subscription->channel_id,
            );

            return new TwitchEventSubHandleResult(skipReason: TwitchEventSubSkipReason::BadSignature);
        }

        // Twitch redelivers on timeouts — drop duplicate message ids.
        if ($messageId !== '' && ! $this->rememberMessageId($messageId)) {
            $this->logSkip(TwitchEventSubSkipReason::DuplicateMessage, $messageType, $bodyBytes, $twitchSubscriptionId);

            return new TwitchEventSubHandleResult(skipReason: TwitchEventSubSkipReason::DuplicateMessage);
        }

        $subscription->forceFill(['last_notify_at' => now(), 'last_error' => null])->save();

        $event = is_array($payload['event'] ?? null) ? $payload['event'] : [];
        $broadcasterId = (string) ($event['broadcaster_user_id'] ?? $subscription->external_id);

        $liveAction = $this->applyLiveState($subscription, $broadcasterId);

        // All three handled event types coalesce into one channel snapshot
        // refresh; pool flush applies fresh channel attributes and fires
        // ChannelSnapshotUpdated realtime.
        $this->channelPool->enqueue(
            PlatformKey::Twitch->value,
            $broadcasterId,
            (string) $subscription->channel_id,
        );

        return new TwitchEventSubHandleResult(enqueued: 1, liveAction: $liveAction);
    }

    /**
     * Event-type specific live-state transitions on vtual_contents:
     *  - stream.online  → fetch the running stream from Helix and upsert a
     *    Live content row (is_live for profiles derives from these rows).
     *  - stream.offline → end all live rows of the channel (idempotent).
     *  - channel.update → no live-state change.
     *
     * Failures are graceful: the snapshot enqueue below still runs. Returns
     * the applied action for tests/logs: created|updated|ended|null.
     */
    private function applyLiveState(EventsubSubscription $subscription, string $broadcasterId): ?string
    {
        $eventType = $subscription->event_type;

        if ($eventType === TwitchEventSubType::StreamOffline) {
            $ended = $this->liveState->markOffline($subscription->channel);

            if ($ended > 0) {
                $this->realtime->snapshotsUpdated(
                    (string) $subscription->channel->creator_id,
                    [(string) $subscription->channel_id],
                );

                return 'ended';
            }

            return null;
        }

        if ($eventType !== TwitchEventSubType::StreamOnline) {
            return null;
        }

        try {
            $streams = $this->gateway->fetchStreamsByUserIds(PlatformKey::Twitch, [$broadcasterId]);
        } catch (VtualException $e) {
            // No active credential / platform unavailable — live row will be
            // reconciled by the poll backstop.
            Log::warning('vtual.eventsub_stream_fetch_skipped', [
                'reason' => $e->getMessage(),
                'channel_id' => (string) $subscription->channel_id,
            ]);

            return null;
        }

        $stream = $streams->firstWhere('userExternalId', $broadcasterId);
        if ($stream === null) {
            // Event arrived late / stream already ended — end any live rows.
            $ended = $this->liveState->markOffline($subscription->channel);

            if ($ended > 0) {
                $this->realtime->snapshotsUpdated(
                    (string) $subscription->channel->creator_id,
                    [(string) $subscription->channel_id],
                );

                return 'ended';
            }

            return null;
        }

        $existed = $this->liveState->hasLiveRow($subscription->channel);
        $this->liveState->markOnline($subscription->channel, $stream);
        $this->realtime->snapshotsUpdated(
            (string) $subscription->channel->creator_id,
            [(string) $subscription->channel_id],
        );

        return $existed ? 'updated' : 'created';
    }

    public function maxBodyBytes(): int
    {
        return max(1, (int) setting_config('vtual.eventsub.max_body_bytes', 1_048_576));
    }

    /**
     * @return bool True when the message id is fresh (first delivery)
     */
    private function rememberMessageId(string $messageId): bool
    {
        $key = 'vtual.eventsub.msgid.'.$messageId;
        $ttlHours = max(1, (int) setting_config('vtual.eventsub.dedupe_ttl_hours', 6));

        return Cache::add($key, 1, now()->addHours($ttlHours));
    }

    private function logSkip(
        TwitchEventSubSkipReason $reason,
        string $messageType,
        int $bodyBytes,
        ?string $twitchSubscriptionId = null,
    ): void {
        Log::warning('vtual.twitch_eventsub_notification_skipped', array_filter([
            'domain' => 'vtual',
            'component' => 'twitch_eventsub',
            'event' => $reason->value,
            'message_type' => $messageType !== '' ? $messageType : null,
            'body_bytes' => $bodyBytes,
            'twitch_subscription_id' => $twitchSubscriptionId,
        ], fn (mixed $value): bool => $value !== null && $value !== ''));
    }

    /**
     * Stable string for the subscription's event type, used to keep one
     * incident per (channel, event type) instead of merging them.
     */
    private function eventTypeValue(EventsubSubscription $subscription): string
    {
        $type = $subscription->event_type;

        return $type instanceof TwitchEventSubType ? $type->value : (string) $type;
    }
}
