<?php

namespace App\Domain\Vtual\Support;

use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\ContentStatus;
use App\Domain\Vtual\Enums\ContentType;
use Illuminate\Database\Eloquent\Builder;

/**
 * Single source of truth for the guest-facing "this creator is live" predicate.
 *
 * Yields the `withExists` clause producing a boolean `is_live` attribute on a
 * Creator query (public directory cards, management roster). Content on an
 * archived primary channel never counts as live, matching the public profile
 * content policy.
 */
final class LiveContentScope
{
    /**
     * @return array<string, callable(Builder): void>
     */
    public static function withExistsClause(): array
    {
        return [
            'contents as is_live' => function (Builder $query): void {
                $query->where('type', ContentType::Live)
                    ->where('status', ContentStatus::Live)
                    ->whereDoesntHave(
                        'channel',
                        fn ($channel) => $channel->where('status', ChannelStatus::Archived),
                    );
            },
        ];
    }
}
