<?php

namespace App\Domain\Vtual\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

/**
 * Public directory item. Guest-safe: no UUID, no claim URLs — slug-addressed only.
 *
 * @mixin \App\Domain\Vtual\Models\Creator
 */
class PublicVtuberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $channels = $this->whenLoaded('channels');
        $bio = is_string($this->bio) ? trim($this->bio) : '';

        return [
            'slug' => $this->slug,
            'display_name' => $this->display_name,
            'avatar_url' => $this->avatar_url,
            'bio_snippet' => $bio !== '' ? Str::limit($bio, 140) : null,
            'is_live' => (bool) ($this->is_live ?? false),
            'channels' => $channels instanceof \Illuminate\Http\Resources\MissingValue
                ? $channels
                : $this->channels->map(fn ($channel): array => [
                    'platform_key' => $channel->platform?->key instanceof \BackedEnum
                        ? $channel->platform->key->value
                        : $channel->platform?->key,
                    'handle' => $channel->handle,
                    'url' => $channel->url,
                    'subscriber_count' => $channel->subscriber_count,
                ])->values()->all(),
            'total_subscribers' => $channels instanceof \Illuminate\Http\Resources\MissingValue
                ? null
                : (int) $this->channels->sum(fn ($channel): int => (int) ($channel->subscriber_count ?? 0)),
            'total_subscribers_formatted' => $channels instanceof \Illuminate\Http\Resources\MissingValue
                ? null
                : Number::format((int) $this->channels->sum(fn ($channel): int => (int) ($channel->subscriber_count ?? 0))),
        ];
    }
}
