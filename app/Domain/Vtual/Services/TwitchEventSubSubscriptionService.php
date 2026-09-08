<?php

namespace App\Domain\Vtual\Services;

use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\PlatformCredentialType;
use App\Domain\Vtual\Enums\PlatformCredentialStatus;
use App\Domain\Vtual\Enums\PlatformKey;
use App\Domain\Vtual\Enums\TwitchEventSubStatus;
use App\Domain\Vtual\Enums\TwitchEventSubType;
use App\Domain\Vtual\Integrations\Support\PlatformHttp;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\EventsubSubscription;
use App\Domain\Vtual\Models\PlatformCredential;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Twitch EventSub webhook subscription lifecycle for channels. One
 * (channel, event_type) row per handled event type; the callback is verified
 * by Twitch via the signed `webhook_callback_verification` message and every
 * delivery is HMAC-signed with the row's secret (see
 * TwitchEventSubNotificationService).
 */
class TwitchEventSubSubscriptionService
{
    public function __construct(
        private readonly PlatformCredentialService $credentials,
    ) {}

    /**
     * Subscribe one channel to all handled event types. Best-effort per type;
     * a partial failure leaves the failed rows in `failed` with last_error.
     */
    public function subscribe(Channel $channel): void
    {
        if (! $this->isEligibleChannel($channel)) {
            return;
        }

        $platform = $channel->platform;
        if ($platform === null) {
            return;
        }

        $credential = $this->resolveTwitchAppCredential($platform);
        if ($credential === null) {
            // EventSub management requires an active Twitch OauthApp
            // (client_id + client_secret) credential — skip without failing.
            Log::info('vtual.eventsub_subscribe_skipped', [
                'reason' => 'no_active_oauth_app_credential',
                'channel_id' => (string) $channel->id,
            ]);

            return;
        }

        $callback = $this->callbackUrl();
        $externalId = trim((string) $channel->external_id);

        foreach (TwitchEventSubType::all() as $type) {
            $this->subscribeType($channel, $credential, $type, $externalId, $callback);
        }
    }

    /**
     * Force re-subscribe (e.g. after channel restore).
     */
    public function resubscribe(Channel $channel): void
    {
        $this->subscribe($channel);
    }

    public function unsubscribe(Channel $channel): void
    {
        $platform = $channel->platform;
        $credential = $platform === null ? null : $this->resolveTwitchAppCredential($platform);

        EventsubSubscription::query()
            ->where('channel_id', $channel->id)
            ->each(function (EventsubSubscription $subscription) use ($credential): void {
                if ($credential !== null && $subscription->twitch_subscription_id !== null) {
                    $this->deleteSubscriptionAtTwitch($credential, (string) $subscription->twitch_subscription_id);
                }

                $subscription->forceFill([
                    'status' => TwitchEventSubStatus::Unsubscribed,
                    'lease_expires_at' => null,
                ])->save();
            });
    }

    /**
     * Challenge callback (webhook_callback_verification). Returns true when
     * the challenge should be echoed.
     */
    public function attemptMarkVerified(string $twitchSubscriptionId): bool
    {
        $subscription = EventsubSubscription::query()
            ->where('twitch_subscription_id', $twitchSubscriptionId)
            ->first();

        if ($subscription === null) {
            return false;
        }

        if (! in_array($subscription->status, [
            TwitchEventSubStatus::Pending,
            TwitchEventSubStatus::Failed,
            TwitchEventSubStatus::Active,
        ], true)) {
            return false;
        }

        $subscription->forceFill([
            'status' => TwitchEventSubStatus::Active,
            'subscribed_at' => now(),
            'last_error' => null,
        ])->save();

        return true;
    }

    /**
     * Resolve a subscription row from the twitch-issued subscription id
     * (present in every message payload as subscription.id).
     */
    public function resolveByTwitchSubscriptionId(string $twitchSubscriptionId): ?EventsubSubscription
    {
        return EventsubSubscription::query()
            ->where('twitch_subscription_id', $twitchSubscriptionId)
            ->with('channel')
            ->first();
    }

    /**
     * @return int Number of subscriptions renewed
     */
    public function renewDueSubscriptions(): int
    {
        $renewBeforeHours = max(1, (int) setting_config('vtual.eventsub.renew_before_hours', 24));
        $cutoff = now()->addHours($renewBeforeHours);

        $count = 0;
        EventsubSubscription::query()
            ->with('channel')
            ->where('status', TwitchEventSubStatus::Active)
            ->whereNotNull('lease_expires_at')
            ->where('lease_expires_at', '<=', $cutoff)
            ->orderBy('lease_expires_at')
            ->limit((int) setting_config('vtual.eventsub.repair_limit', 50))
            ->each(function (EventsubSubscription $subscription) use (&$count): void {
                $channel = $subscription->channel;
                if ($channel === null) {
                    return;
                }

                $this->subscribe($channel);
                $count++;
            });

        return $count;
    }

    /**
     * Subscribe active Twitch channels that have no EventSub rows yet (repair).
     *
     * @return int Number of channels subscribed
     */
    public function subscribeMissingActiveChannels(int $limit = 50): int
    {
        $limit = max(1, $limit);
        $count = 0;

        Channel::query()
            ->with('platform')
            ->where('status', ChannelStatus::Active)
            ->where('external_id', 'regexp', '^[0-9]+$')
            ->whereHas('platform', fn ($q) => $q->where('key', PlatformKey::Twitch->value)->where('enabled', true))
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('vtual_eventsub_subscriptions')
                    ->whereColumn('vtual_eventsub_subscriptions.channel_id', 'vtual_channels.id')
                    ->whereIn('status', [
                        TwitchEventSubStatus::Active->value,
                        TwitchEventSubStatus::Pending->value,
                    ]);
            })
            ->orderBy('created_at')
            ->limit($limit)
            ->each(function (Channel $channel) use (&$count): void {
                $this->subscribe($channel);
                $count++;
            });

        return $count;
    }

    public function callbackUrl(): string
    {
        return route('api.v1.vtual.webhooks.twitch-eventsub');
    }

    private function subscribeType(
        Channel $channel,
        PlatformCredential $credential,
        TwitchEventSubType $type,
        string $externalId,
        string $callback,
    ): void {
        $secret = Str::random(32);

        $subscription = EventsubSubscription::query()->updateOrCreate(
            ['channel_id' => $channel->id, 'event_type' => $type],
            [
                'external_id' => $externalId,
                'secret' => $secret,
                'status' => TwitchEventSubStatus::Pending,
                'twitch_subscription_id' => null,
                'lease_expires_at' => now()->addDays(10),
                'last_error' => null,
            ],
        );

        $body = [
            'type' => $type->value,
            'version' => $type->version(),
            'condition' => ['broadcaster_user_id' => $externalId],
            'transport' => [
                'method' => 'webhook',
                'callback' => $callback,
                'secret' => $secret,
            ],
        ];

        try {
            $response = $this->helixPost($credential, '/eventsub/subscriptions', $body);
        } catch (\Throwable $e) {
            $subscription->forceFill([
                'status' => TwitchEventSubStatus::Failed,
                'last_error' => 'EventSub subscribe exception: '.substr($e->getMessage(), 0, 200),
            ])->save();

            return;
        }

        if (! $response->successful()) {
            $detail = $response->json('message') ?? '';
            $subscription->forceFill([
                'status' => TwitchEventSubStatus::Failed,
                'last_error' => 'EventSub subscribe HTTP '.$response->status()
                    .(is_string($detail) && $detail !== '' ? ': '.substr($detail, 0, 200) : ''),
            ])->save();

            return;
        }

        $data = $response->json('data.0');
        if (! is_array($data) || ! is_string($data['id'] ?? null)) {
            $subscription->forceFill([
                'status' => TwitchEventSubStatus::Failed,
                'last_error' => 'EventSub subscribe response missing subscription id',
            ])->save();

            return;
        }

        $subscription->forceFill([
            'twitch_subscription_id' => (string) $data['id'],
            'status' => TwitchEventSubStatus::Pending,
            'lease_expires_at' => $this->resolveLeaseExpiresAt($response->json('data.0.transport.expires_at')),
        ])->save();
    }

    private function deleteSubscriptionAtTwitch(PlatformCredential $credential, string $twitchSubscriptionId): void
    {
        try {
            $this->helixDelete($credential, '/eventsub/subscriptions', ['id' => $twitchSubscriptionId]);
        } catch (\Throwable $e) {
            // Best-effort cleanup — local row is authoritative for the app.
            Log::warning('vtual.eventsub_delete_failed', [
                'error' => substr($e->getMessage(), 0, 200),
            ]);
        }
    }

    private function helixPost(PlatformCredential $credential, string $path, array $body)
    {
        $url = $this->baseUrl().$path;

        return $this->authedRequest($credential)
            ->post($url, $body);
    }

    private function helixDelete(PlatformCredential $credential, string $path, array $query)
    {
        $url = $this->baseUrl().$path.(isset($query['id']) ? '?'.http_build_query($query) : '');

        return $this->authedRequest($credential)
            ->delete($url);
    }

    private function authedRequest(PlatformCredential $credential)
    {
        $payload = $this->credentials->resolvePayload($credential);
        $clientId = $payload['client_id'] ?? null;
        if (! is_string($clientId) || $clientId === '') {
            return PlatformHttp::client();
        }

        $token = $this->credentials->getAppAccessToken($credential);

        return PlatformHttp::client()->withHeaders([            'Client-Id' => $clientId,
            'Authorization' => 'Bearer '.$token,
        ]);
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('vtual.twitch.helix_base_url', 'https://api.twitch.tv/helix'), '/');
    }

    private function resolveLeaseExpiresAt(mixed $expiresAt): ?Carbon
    {
        if (! is_string($expiresAt) || $expiresAt === '') {
            return null;
        }

        try {
            return Carbon::parse($expiresAt);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * First active Twitch OauthApp credential (priority order).
     */
    private function resolveTwitchAppCredential($platform): ?PlatformCredential
    {
        return PlatformCredential::query()
            ->where('platform_id', $platform->id)
            ->where('type', PlatformCredentialType::OauthApp)
            ->where('status', PlatformCredentialStatus::Active)
            ->where(function ($q): void {
                $q->whereNull('failover_skip_until')
                    ->orWhere('failover_skip_until', '<=', now());
            })
            ->orderBy('priority')
            ->orderBy('created_at')
            ->first();
    }

    private function isEligibleChannel(Channel $channel): bool
    {
        if (! in_array($channel->status, [ChannelStatus::Active, ChannelStatus::Archived], true)) {
            return false;
        }

        $channel->loadMissing('platform');
        $key = $channel->platform?->key;
        if ($key instanceof PlatformKey) {
            $platformKey = $key;
        } else {
            $platformKey = PlatformKey::tryFrom((string) $key);
        }

        if ($platformKey !== PlatformKey::Twitch) {
            return false;
        }

        $externalId = trim((string) $channel->external_id);

        return $externalId !== '' && ctype_digit($externalId);
    }
}
