<?php

namespace App\Domain\Vtual\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Exceptions\VtualException;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\Platform;
use Illuminate\Database\Eloquent\Collection;

class ChannelService
{
    public function __construct(
        private readonly CreatorAccessService $creatorAccess,
    ) {}

    /**
     * @return Collection<int, Channel>
     */
    public function listForCreator(User $actor, string $creatorId): Collection
    {
        $creator = $this->creatorAccess->findOrFail($creatorId);
        $this->creatorAccess->assertCanView($creator, $actor);

        return Channel::query()
            ->where('creator_id', $creator->id)
            ->with('platform')
            ->orderBy('created_at')
            ->get();
    }

    /**
     * @param  array{platform_key: string, external_id: string, handle?: string|null, display_name?: string|null, url?: string|null}  $data
     */
    public function create(User $actor, string $creatorId, array $data): Channel
    {
        $creator = $this->creatorAccess->findOrFail($creatorId);
        $this->creatorAccess->assertCanMutate($creator, $actor);

        $platform = Platform::query()->where('key', $data['platform_key'])->first();
        if ($platform === null) {
            throw new VtualException(__('api/vtual.channel.platform_unknown'), 422);
        }

        if (! $platform->isEnabled()) {
            throw new VtualException(__('api/vtual.channel.platform_disabled'), 422);
        }

        $externalId = trim($data['external_id']);
        if ($externalId === '') {
            throw new VtualException(__('api/vtual.channel.external_id_required'), 422);
        }

        $exists = Channel::query()
            ->where('platform_id', $platform->id)
            ->where('external_id', $externalId)
            ->exists();

        if ($exists) {
            throw new VtualException(__('api/vtual.channel.external_id_taken'), 422);
        }

        $channel = Channel::query()->create([
            'creator_id' => $creator->id,
            'platform_id' => $platform->id,
            'external_id' => $externalId,
            'handle' => $data['handle'] ?? null,
            'display_name' => $data['display_name'] ?? null,
            'url' => $data['url'] ?? null,
            'status' => ChannelStatus::Pending,
        ]);

        return $channel->load('platform');
    }

    public function show(User $actor, string $channelId): Channel
    {
        $channel = Channel::query()->with(['platform', 'creator'])->findOrFail($channelId);
        $this->creatorAccess->assertCanView($channel->creator, $actor);

        return $channel;
    }
}
