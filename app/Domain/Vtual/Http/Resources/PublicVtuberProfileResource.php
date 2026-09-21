<?php

namespace App\Domain\Vtual\Http\Resources;

use App\Domain\Vtual\Enums\ContentStatus;
use App\Domain\Vtual\Enums\ContentType;
use App\Domain\Vtual\Services\CreatorService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Number;

/**
 * Guest-safe public vTuber profile. No UUID, no claim URLs/tokens.
 * `is_claimable` is a directory fact (orphan vs owned), not a secret.
 *
 * @mixin \App\Domain\Vtual\Models\Creator
 */
class PublicVtuberProfileResource extends JsonResource
{
    /**
     * Applied content-grid filters (already clamped by the controller).
     *
     * @var array{platform: string|null, type: string|null, sort: string}
     */
    private array $filters;

    /**
     * @param  array{platform?: string|null, type?: string|null, sort?: string|null}  $filters
     */
    public function __construct($resource, array $filters = [])
    {
        parent::__construct($resource);

        $this->filters = [
            'platform' => $filters['platform'] ?? null,
            'type' => $filters['type'] ?? null,
            'sort' => $filters['sort'] ?? 'newest',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var LengthAwarePaginator<int, \App\Domain\Vtual\Models\Content>|null $paginator */
        $paginator = $this->relationLoaded('profileContents')
            ? $this->getRelation('profileContents')
            : null;
        $contents = $paginator?->getCollection() ?? collect();

        $channelsLoaded = $this->relationLoaded('channels');
        $subscriberTotal = $channelsLoaded
            ? (int) $this->channels->sum(fn ($channel): int => (int) ($channel->subscriber_count ?? 0))
            : 0;

        return [
            'slug' => $this->slug,
            'display_name' => $this->display_name,
            'bio' => $this->bio,
            'avatar_url' => $this->avatar_url,
            'is_claimable' => app(CreatorService::class)->isClaimable($this->resource),
            'is_live' => (bool) ($this->is_live ?? $contents->contains(
                fn ($content): bool => $content->type === ContentType::Live
                    && $content->status === ContentStatus::Live,
            )),
            'total_subscribers' => $channelsLoaded ? $subscriberTotal : null,
            'total_subscribers_formatted' => $channelsLoaded
                ? Number::format($subscriberTotal)
                : null,
            'channels' => $this->whenLoaded(
                'channels',
                fn () => $this->channels->map(fn ($channel): array => [
                    'platform_key' => $channel->platform?->key instanceof \BackedEnum
                        ? $channel->platform->key->value
                        : $channel->platform?->key,
                    'platform_name' => $channel->platform?->name,
                    'handle' => $channel->handle,
                    'display_name' => $channel->display_name,
                    'avatar_url' => $channel->avatar_url,
                    'banner_url' => $channel->banner_url,
                    'url' => $channel->url,
                    'subscriber_count' => $channel->subscriber_count,
                    'subscriber_count_formatted' => $channel->subscriber_count !== null
                        ? Number::format($channel->subscriber_count)
                        : null,
                ])->values()->all(),
            ),
            'links' => $this->whenLoaded(
                'externalLinks',
                fn () => $this->externalLinks->map(fn ($link): array => [
                    'kind' => $link->kind instanceof \BackedEnum ? $link->kind->value : $link->kind,
                    'label' => $link->label,
                    'url' => $link->url,
                ])->values()->all(),
            ),
            'contents' => [
                'items' => $contents->map(fn ($content): array => [
                    'type' => $content->type instanceof \BackedEnum ? $content->type->value : $content->type,
                    'status' => $content->status instanceof \BackedEnum ? $content->status->value : $content->status,
                    'content_category' => $content->content_category instanceof \BackedEnum
                        ? $content->content_category->value
                        : $content->content_category,
                    'content_category_detail' => $content->content_category_detail,
                    'content_type' => $content->content_type instanceof \BackedEnum
                        ? $content->content_type->value
                        : $content->content_type,
                    'title' => $content->title,
                    'thumbnail_url' => $content->thumbnail_url,
                    'url' => $content->url,
                    'published_at' => $content->published_at?->toIso8601String(),
                    'platform_key' => $content->platform?->key instanceof \BackedEnum
                        ? $content->platform->key->value
                        : $content->platform?->key,
                ])->values()->all(),
                'pagination' => $paginator !== null ? [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'has_more' => $paginator->hasMorePages(),
                ] : [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 12,
                    'total' => 0,
                    'has_more' => false,
                ],
                'filters' => $this->filters,
            ],
        ];
    }
}
