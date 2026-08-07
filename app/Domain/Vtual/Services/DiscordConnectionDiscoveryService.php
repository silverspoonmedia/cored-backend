<?php

namespace App\Domain\Vtual\Services;

use App\Domain\Identity\Services\DiscordConnectionsClient;
use App\Domain\Vtual\Enums\ChannelDiscoverySource;
use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\ChannelVisibility;
use App\Domain\Vtual\Enums\ExternalLinkKind;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\Creator;
use App\Domain\Vtual\Models\ExternalLink;
use App\Domain\Vtual\Models\Platform;
use App\Domain\Vtual\Support\DiscordChannelOwnershipMatcher;
use App\Domain\Vtual\Support\DiscordConnectionExternalLinkMapper;
use Illuminate\Support\Str;

/**
 * Bind Discord connection snapshot to a creator during orphan claim verify.
 */
class DiscordConnectionDiscoveryService
{
    public function __construct(
        private readonly DiscordConnectionsClient $connectionsClient,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $connections
     */
    public function apply(Creator $creator, array $connections): DiscordConnectionDiscoveryResult
    {
        $eligible = $this->connectionsClient->ownershipEligible($connections);

        $publicActiveChannels = Channel::query()
            ->where('creator_id', $creator->id)
            ->where('status', ChannelStatus::Active)
            ->where('visibility', ChannelVisibility::Public)
            ->with('platform')
            ->get();

        $contractFromProfile = DiscordChannelOwnershipMatcher::firstMatch($publicActiveChannels, $eligible);

        $boundPrivate = [];
        $skippedConflicts = [];
        /** @var array<string, Channel> $boundByKey */
        $boundByKey = [];

        foreach ($eligible as $connection) {
            $channel = $this->bindOwnershipConnection($creator, $connection, $skippedConflicts);
            if ($channel === null) {
                continue;
            }

            $key = (string) $channel->platform_id.'|'.(string) $channel->external_id;
            $boundByKey[$key] = $channel;

            if ($channel->visibility === ChannelVisibility::Private) {
                $boundPrivate[] = $channel;
            }
        }

        $contractChannel = $contractFromProfile;

        if ($contractChannel === null) {
            foreach ($eligible as $connection) {
                $type = (string) ($connection['type'] ?? '');
                $externalId = (string) ($connection['id'] ?? '');
                $platform = Platform::query()->where('key', $type)->first();
                if ($platform === null) {
                    continue;
                }

                $key = (string) $platform->id.'|'.$externalId;
                $channel = $boundByKey[$key] ?? null;
                if ($channel !== null) {
                    $contractChannel = $channel;
                    break;
                }
            }
        }

        $externalLinksUpserted = $this->upsertExternalLinks($creator, $connections);

        return new DiscordConnectionDiscoveryResult(
            contractChannel: $contractChannel,
            boundPrivate: $boundPrivate,
            skippedConflicts: $skippedConflicts,
            externalLinksUpserted: $externalLinksUpserted,
        );
    }

    /**
     * @param  array<string, mixed>  $connection
     * @param  list<string>  $skippedConflicts
     */
    private function bindOwnershipConnection(
        Creator $creator,
        array $connection,
        array &$skippedConflicts,
    ): ?Channel {
        $type = (string) ($connection['type'] ?? '');
        $externalId = (string) ($connection['id'] ?? '');
        $platform = Platform::query()->where('key', $type)->first();
        if ($platform === null || ! $platform->isEnabled()) {
            return null;
        }

        $existing = Channel::query()
            ->where('platform_id', $platform->id)
            ->where('external_id', $externalId)
            ->first();

        if ($existing !== null) {
            if ((string) $existing->creator_id !== (string) $creator->id) {
                $skippedConflicts[] = $externalId;

                return null;
            }

            if ($existing->status === ChannelStatus::Archived) {
                $existing->forceFill([
                    'status' => ChannelStatus::Active,
                    'visibility' => ChannelVisibility::Private,
                    'discovery_source' => ChannelDiscoverySource::DiscordConnection,
                    'display_name' => $existing->display_name ?? (string) ($connection['name'] ?? ''),
                ])->save();

                return $existing->refresh()->load('platform');
            }

            if ($existing->visibility === ChannelVisibility::Public) {
                return $existing->load('platform');
            }

            $existing->forceFill([
                'discovery_source' => $existing->discovery_source ?? ChannelDiscoverySource::DiscordConnection,
                'display_name' => $existing->display_name ?? (string) ($connection['name'] ?? ''),
            ])->save();

            return $existing->refresh()->load('platform');
        }

        $displayName = trim((string) ($connection['name'] ?? ''));
        if ($displayName === '') {
            $displayName = $externalId;
        }

        $channel = Channel::query()->create([
            'id' => (string) Str::uuid7(),
            'creator_id' => $creator->id,
            'platform_id' => $platform->id,
            'external_id' => $externalId,
            'display_name' => $displayName,
            'status' => ChannelStatus::Active,
            'visibility' => ChannelVisibility::Private,
            'discovery_source' => ChannelDiscoverySource::DiscordConnection,
        ]);

        return $channel->load('platform');
    }

    /**
     * @param  list<array<string, mixed>>  $connections
     */
    private function upsertExternalLinks(Creator $creator, array $connections): int
    {
        $candidates = $this->connectionsClient->externalLinkCandidates($connections);
        $upserted = 0;

        $existingUrls = $creator->externalLinks()
            ->pluck('url')
            ->map(fn (string $url): string => strtolower(trim($url)))
            ->all();

        $maxSort = (int) $creator->externalLinks()->max('sort_order');

        foreach ($candidates as $connection) {
            $mapped = DiscordConnectionExternalLinkMapper::map($connection);
            if ($mapped === null) {
                continue;
            }

            $normalizedUrl = strtolower(trim($mapped['url']));
            if (in_array($normalizedUrl, $existingUrls, true)) {
                continue;
            }

            $creator->externalLinks()->create([
                'label' => $mapped['label'],
                'url' => $mapped['url'],
                'kind' => $mapped['kind'],
                'sort_order' => ++$maxSort,
            ]);

            $existingUrls[] = $normalizedUrl;
            $upserted++;
        }

        return $upserted;
    }
}
