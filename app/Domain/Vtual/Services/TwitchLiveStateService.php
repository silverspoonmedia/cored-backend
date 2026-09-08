<?php

namespace App\Domain\Vtual\Services;

use App\Domain\Vtual\Enums\ContentStatus;
use App\Domain\Vtual\Enums\ContentType;
use App\Domain\Vtual\Integrations\Dto\PlatformStreamDto;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\Content;

/**
 * Apply Twitch live-state transitions to content rows, driven by EventSub
 * notifications (stream.online / stream.offline) and the live-contents poll
 * backstop. Live state stays derived from vtual_contents — no extra flag.
 */
class TwitchLiveStateService
{
    public function __construct(
        private readonly ContentService $contents,
        private readonly PlatformVideoDtoMapper $mapper,
    ) {}

    /**
     * Upsert (or refresh) the live content row for a running stream session.
     * The Twitch stream session id is the content external_id, so repeated
     * notifications for the same session refresh CCV/title in place.
     */
    public function markOnline(Channel $channel, PlatformStreamDto $stream): Content
    {
        return $this->contents->upsertFromPrimary(
            $channel,
            $this->mapper->toUpsertPayloadFromStream($stream),
        );
    }

    /**
     * End all live rows of a channel. Idempotent.
     *
     * @return int Number of rows ended
     */
    public function markOffline(Channel $channel): int
    {
        return $this->contents->endLiveStreamsForChannel($channel);
    }

    /**
     * Whether the channel currently has any live content row.
     */
    public function hasLiveRow(Channel $channel): bool
    {
        return Content::query()
            ->where('platform_id', $channel->platform_id)
            ->where('channel_id', $channel->id)
            ->where('type', ContentType::Live)
            ->where('status', ContentStatus::Live)
            ->exists();
    }
}
