<?php

namespace App\Domain\Vtual\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\PlatformKey;
use App\Domain\Vtual\Exceptions\VtualException;
use App\Domain\Vtual\Integrations\Support\ChannelProfileResolver;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\Platform;
use Illuminate\Database\Eloquent\Collection;

class ChannelService
{
    public function __construct(
        private readonly CreatorAccessService $creatorAccess,
        private readonly ChannelProfileResolver $profiles,
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
     * Register channel from free-form profile (id, handle/login, or URL).
     * Resolves via platform API; stores canonical external_id + current handle.
     *
     * @param  array{platform_key: string, profile: string}  $data
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

        $platformKey = $this->platformKey($platform);
        $profile = trim((string) ($data['profile'] ?? ''));
        if ($profile === '') {
            throw new VtualException(__('api/vtual.channel.profile_required'), 422);
        }

        $dto = $this->profiles->resolve($platformKey, $profile);

        $exists = Channel::query()
            ->where('platform_id', $platform->id)
            ->where('external_id', $dto->externalId)
            ->exists();

        if ($exists) {
            throw new VtualException(__('api/vtual.channel.external_id_taken'), 422);
        }

        $channel = Channel::query()->create([
            'creator_id' => $creator->id,
            'platform_id' => $platform->id,
            'external_id' => $dto->externalId,
            'handle' => $dto->handle,
            'display_name' => $dto->displayName,
            'url' => $dto->url,
            'status' => ChannelStatus::Pending,
            'last_synced_at' => now(),
        ]);

        return $channel->load('platform');
    }

    /**
     * Re-fetch profile by stored external_id; update handle/display/url (identity stable).
     */
    public function refresh(User $actor, string $channelId): Channel
    {
        $channel = Channel::query()->with(['platform', 'creator'])->findOrFail($channelId);
        $this->creatorAccess->assertCanMutate($channel->creator, $actor);

        $platform = $channel->platform;
        if ($platform === null || ! $platform->isEnabled()) {
            throw new VtualException(__('api/vtual.channel.platform_disabled'), 422);
        }

        $dto = $this->profiles->resolveByExternalId(
            $this->platformKey($platform),
            (string) $channel->external_id,
        );

        $channel->forceFill([
            'handle' => $dto->handle,
            'display_name' => $dto->displayName,
            'url' => $dto->url,
            'last_synced_at' => now(),
        ])->save();

        return $channel->refresh()->load('platform');
    }

    public function show(User $actor, string $channelId): Channel
    {
        $channel = Channel::query()->with(['platform', 'creator'])->findOrFail($channelId);
        $this->creatorAccess->assertCanView($channel->creator, $actor);

        return $channel;
    }

    private function platformKey(Platform $platform): PlatformKey
    {
        $key = $platform->key;
        if ($key instanceof PlatformKey) {
            return $key;
        }

        return PlatformKey::from((string) $key);
    }
}
