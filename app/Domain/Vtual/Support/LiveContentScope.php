<?php

namespace App\Domain\Vtual\Support;

use App\Domain\Vtual\Enums\ChannelStatus;
use App\Domain\Vtual\Enums\ChannelVisibility;
use App\Domain\Vtual\Enums\ContentStatus;
use App\Domain\Vtual\Enums\ContentType;
use App\Domain\Vtual\Enums\EntityStatus;
use App\Domain\Vtual\Models\Content;
use App\Domain\Vtual\Models\Creator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Single source of truth for the guest-facing "this creator is live" predicate.
 *
 * A creator counts as live when at least one of their non-archived channels has
 * a live content row — either as the **primary host** (`vtual_contents.creator_id`)
 * or as an **appearance** (`vtual_content_appearances.creator_id`, the collaborator
 * / network role). Content on an archived primary channel never counts, matching
 * the public profile content policy.
 *
 * Consumers — do not duplicate this predicate elsewhere:
 *  - `VtuberProfileService::directory()` (public `/vtual` directory cards + sort)
 *  - `ManagementService::showForPublic()` (guest management roster badge)
 *  - `VtuberProfileService::hasLiveContent()` (public profile `is_live`)
 *  - `VtuberProfileService::liveNow()` (landing live strip; adds guest visibility)
 *
 * The creator-side clause cannot use `withExists('contents as is_live')`: that
 * helper ANDs the relation's own correlation (`creator_id = creators.id`) onto
 * the subquery, so an appearance-only row could never satisfy it. The
 * subquery below therefore owns its correlation and ORs both paths.
 */
final class LiveContentScope
{
    /**
     * Base live-content query: live type + live status + no archived primary channel.
     *
     * @return Builder<Content>
     */
    public static function query(): Builder
    {
        return Content::query()
            ->where('type', ContentType::Live)
            ->where('status', ContentStatus::Live)
            ->whereDoesntHave(
                'channel',
                fn (Builder $channel) => $channel->where('status', ChannelStatus::Archived),
            );
    }

    /**
     * Restrict the base query to one creator, counting primary host **and**
     * appearance rows.
     *
     * @param  Builder<Content>  $query
     */
    public static function constrainToCreator(Builder $query, string $creatorId): void
    {
        $query->where(function (Builder $inner) use ($creatorId): void {
            $inner->where('creator_id', $creatorId)
                ->orWhereHas(
                    'appearances',
                    fn (Builder $appearance) => $appearance->where('creator_id', $creatorId),
                );
        });
    }

    /**
     * Guest visibility: the stream must be presentable (title + url) and both
     * its primary channel and its creator must be active + public.
     *
     * @param  Builder<Content>  $query
     */
    public static function constrainToGuestVisible(Builder $query): void
    {
        $query
            ->whereNotNull('title')
            ->where('title', '!=', '')
            ->whereNotNull('url')
            ->where('url', '!=', '')
            ->whereHas('creator', function (Builder $creator): void {
                $creator->where('status', EntityStatus::Active)
                    ->whereHas('channels', function (Builder $channels): void {
                        $channels->where('status', ChannelStatus::Active)
                            ->where('visibility', ChannelVisibility::Public);
                    });
            })
            ->whereHas('channel', function (Builder $channel): void {
                $channel->where('status', ChannelStatus::Active)
                    ->where('visibility', ChannelVisibility::Public);
            });
    }

    /**
     * Add a boolean `is_live` attribute to a Creator query (public directory
     * cards, guest management roster). Stays a single `exists` subquery, so
     * listings do not fan out into N+1 — `vtual_content_appearances(creator_id,
     * role)` is indexed for the appearance branch.
     *
     * @param  Builder<Creator>  $creatorQuery
     */
    public static function applyIsLiveSelect(Builder $creatorQuery): void
    {
        $creatorQuery->addSelect([
            'is_live' => Content::query()
                ->selectRaw('1')
                ->where('type', ContentType::Live)
                ->where('status', ContentStatus::Live)
                ->whereDoesntHave(
                    'channel',
                    fn (Builder $channel) => $channel->where('status', ChannelStatus::Archived),
                )
                ->where(function (Builder $inner): void {
                    $inner->whereColumn('vtual_contents.creator_id', 'vtual_creators.id')
                        ->orWhereExists(function ($appearance): void {
                            $appearance->selectRaw('1')
                                ->from('vtual_content_appearances')
                                ->whereColumn('vtual_content_appearances.content_id', 'vtual_contents.id')
                                ->whereColumn('vtual_content_appearances.creator_id', 'vtual_creators.id');
                        });
                })
                ->limit(1),
        ]);
    }
}
