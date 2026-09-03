<?php

namespace App\Domain\Vtual\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Channel snapshots for one creator changed after a fetch-pool flush.
 *
 * Payload is ids only — clients refetch channel data.
 */
class ChannelSnapshotUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  array<int, string>  $channelIds
     */
    public function __construct(
        public readonly string $creatorId,
        public readonly array $channelIds,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('vtual.creator.'.$this->creatorId)];
    }

    public function broadcastAs(): string
    {
        return 'channel.snapshot.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'creator_id' => $this->creatorId,
            'channel_ids' => $this->channelIds,
        ];
    }
}
