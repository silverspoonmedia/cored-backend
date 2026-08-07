<?php

namespace App\Domain\Vtual\Services;

use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\PlatformKey;
use App\Domain\Vtual\Enums\WebsubSubscriptionStatus;
use App\Domain\Vtual\Integrations\Youtube\YoutubeChannelVideoFeedClient;
use App\Domain\Vtual\Integrations\Youtube\YoutubeFeedTopicParser;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\WebsubSubscription;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * YouTube PubSubHubbub subscription lifecycle for channel video feeds.
 */
class YoutubeWebSubSubscriptionService
{
    private const string HUB_URL = 'https://pubsubhubbub.appspot.com/subscribe';

    public function __construct(
        private readonly YoutubeChannelVideoFeedClient $feedClient,
        private readonly YoutubeFeedTopicParser $topicParser,
    ) {}

    public function subscribe(Channel $channel): ?WebsubSubscription
    {
        if (! $this->isYoutubeActiveChannel($channel)) {
            return null;
        }

        $topic = $this->feedClient->topicUrl((string) $channel->external_id);
        $callback = $this->callbackUrl();
        $secret = Str::random(32);
        $leaseSeconds = max(3600, (int) setting_config('vtual.websub.lease_seconds', 864000));

        $subscription = WebsubSubscription::query()->updateOrCreate(
            ['channel_id' => $channel->id],
            [
                'topic' => $topic,
                'callback_url' => $callback,
                'secret' => $secret,
                'status' => WebsubSubscriptionStatus::Pending,
                'lease_expires_at' => now()->addSeconds($leaseSeconds),
                'last_error' => null,
            ],
        );

        $response = Http::asForm()->timeout(20)->post(self::HUB_URL, [
            'hub.mode' => 'subscribe',
            'hub.topic' => $topic,
            'hub.callback' => $callback,
            'hub.verify' => 'async',
            'hub.lease_seconds' => (string) $leaseSeconds,
            'hub.secret' => $secret,
        ]);

        if (! $response->successful() && ! in_array($response->status(), [202, 204], true)) {
            $subscription->forceFill([
                'status' => WebsubSubscriptionStatus::Failed,
                'last_error' => 'Hub subscribe HTTP '.$response->status(),
            ])->save();

            return $subscription->refresh();
        }

        return $subscription->refresh();
    }

    public function unsubscribe(Channel $channel): void
    {
        $subscription = WebsubSubscription::query()->where('channel_id', $channel->id)->first();
        if ($subscription === null) {
            return;
        }

        Http::asForm()->timeout(20)->post(self::HUB_URL, [
            'hub.mode' => 'unsubscribe',
            'hub.topic' => $subscription->topic,
            'hub.callback' => $subscription->callback_url,
            'hub.verify' => 'async',
        ]);

        $subscription->forceFill([
            'status' => WebsubSubscriptionStatus::Unsubscribed,
            'lease_expires_at' => null,
        ])->save();
    }

    /**
     * Hub verification callback (GET). Returns true when challenge should be echoed.
     */
    public function attemptMarkVerified(string $topic, string $mode): bool
    {
        if (! in_array($mode, ['subscribe', 'unsubscribe'], true)) {
            return false;
        }

        $subscription = $this->resolveSubscriptionForTopic($topic);
        if ($subscription === null) {
            return false;
        }

        if ($mode === 'subscribe') {
            if (! in_array($subscription->status, [
                WebsubSubscriptionStatus::Pending,
                WebsubSubscriptionStatus::Failed,
                WebsubSubscriptionStatus::Active,
            ], true)) {
                return false;
            }

            $subscription->forceFill([
                'status' => WebsubSubscriptionStatus::Active,
                'subscribed_at' => now(),
                'last_error' => null,
            ])->save();

            return true;
        }

        if (! in_array($subscription->status, [
            WebsubSubscriptionStatus::Active,
            WebsubSubscriptionStatus::Pending,
        ], true)) {
            return false;
        }

        $subscription->forceFill([
            'status' => WebsubSubscriptionStatus::Unsubscribed,
            'lease_expires_at' => null,
        ])->save();

        return true;
    }

    public function resolveSubscriptionForTopic(string $topic): ?WebsubSubscription
    {
        $topic = trim($topic);
        if ($topic === '') {
            return null;
        }

        $subscription = WebsubSubscription::query()
            ->where('topic', $topic)
            ->with('channel')
            ->first();

        if ($subscription !== null) {
            return $subscription;
        }

        $channelExternalId = $this->topicParser->parseChannelExternalId($topic);
        if ($channelExternalId === null) {
            return null;
        }

        return WebsubSubscription::query()
            ->whereHas('channel', fn ($q) => $q->where('external_id', $channelExternalId))
            ->with('channel')
            ->first();
    }

    /**
     * @return int Number of subscriptions renewed
     */
    public function renewDueSubscriptions(): int
    {
        $renewBeforeHours = max(1, (int) setting_config('vtual.websub.renew_before_hours', 24));
        $cutoff = now()->addHours($renewBeforeHours);

        $count = 0;
        WebsubSubscription::query()
            ->with('channel')
            ->where('status', WebsubSubscriptionStatus::Active)
            ->whereNotNull('lease_expires_at')
            ->where('lease_expires_at', '<=', $cutoff)
            ->orderBy('lease_expires_at')
            ->limit(50)
            ->each(function (WebsubSubscription $subscription) use (&$count): void {
                $channel = $subscription->channel;
                if ($channel === null || ! $this->isYoutubeActiveChannel($channel)) {
                    return;
                }

                $this->subscribe($channel);
                $count++;
            });

        return $count;
    }

    /**
     * Subscribe active YouTube channels that have no WebSub row yet (legacy repair).
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
            ->where('external_id', 'like', 'UC%')
            ->whereHas('platform', fn ($q) => $q->where('key', PlatformKey::Youtube->value)->where('enabled', true))
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('vtual_websub_subscriptions')
                    ->whereColumn('vtual_websub_subscriptions.channel_id', 'vtual_channels.id');
            })
            ->orderBy('created_at')
            ->limit($limit)
            ->each(function (Channel $channel) use (&$count): void {
                if (! $this->isYoutubeActiveChannel($channel)) {
                    return;
                }

                $this->subscribe($channel);
                $count++;
            });

        return $count;
    }

    public function callbackUrl(): string
    {
        return route('api.v1.vtual.webhooks.youtube-websub');
    }

    private function isYoutubeActiveChannel(Channel $channel): bool
    {
        if ($channel->status !== ChannelStatus::Active) {
            return false;
        }

        $channel->loadMissing('platform');
        $key = $channel->platform?->key;
        if ($key instanceof PlatformKey) {
            $platformKey = $key;
        } else {
            $platformKey = PlatformKey::tryFrom((string) $key);
        }

        if ($platformKey !== PlatformKey::Youtube) {
            return false;
        }

        $externalId = trim((string) $channel->external_id);

        return $externalId !== '' && str_starts_with($externalId, 'UC');
    }
}
