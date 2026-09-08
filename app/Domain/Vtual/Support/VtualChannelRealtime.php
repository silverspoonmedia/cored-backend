<?php

namespace App\Domain\Vtual\Support;

use App\Domain\Vtual\Events\ChannelSnapshotUpdated;
use App\Support\Broadcasting\BroadcastConnection;

/**
 * Fire channel-snapshot realtime events after fetch-pool flushes.
 */
class VtualChannelRealtime
{
    public function __construct(
        private readonly ?bool $forceEnabled = null,
    ) {}

    public function enabled(): bool
    {
        return $this->forceEnabled ?? BroadcastConnection::isConfigured();
    }

    /**
     * @param  array<int, string>  $channelIds
     */
    public function snapshotsUpdated(string $creatorId, array $channelIds): void
    {
        if (! $this->enabled() || $channelIds === []) {
            return;
        }

        event(new ChannelSnapshotUpdated($creatorId, array_values(array_unique($channelIds))));
    }
}
