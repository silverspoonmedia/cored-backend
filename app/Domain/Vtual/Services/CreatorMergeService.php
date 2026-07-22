<?php

namespace App\Domain\Vtual\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\CreatorClaimClosedReason;
use App\Domain\Vtual\Enums\CreatorClaimStatus;
use App\Domain\Vtual\Enums\CreatorMemberRole;
use App\Domain\Vtual\Enums\EntityStatus;
use App\Domain\Vtual\Exceptions\VtualException;
use App\Domain\Vtual\Models\Channel;
use App\Domain\Vtual\Models\ChannelClaim;
use App\Domain\Vtual\Models\Content;
use App\Domain\Vtual\Models\ContentAppearance;
use App\Domain\Vtual\Models\Creator;
use App\Domain\Vtual\Models\CreatorClaim;
use App\Domain\Vtual\Models\CreatorMember;
use App\Domain\Vtual\Models\ExternalLink;
use App\Domain\Vtual\Models\ManagementCreator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Absorb source vTuber profile into destination: move children, cancel pendings, archive source.
 */
class CreatorMergeService
{
    public function __construct(
        private readonly CreatorAccessService $access,
    ) {}

    /**
     * @return Creator Destination after merge (fresh).
     */
    public function merge(User $actor, string $sourceId, string $destinationId): Creator
    {
        if ($sourceId === $destinationId) {
            throw new VtualException(__('api/vtual.merge.self'), 422);
        }

        return DB::transaction(function () use ($actor, $sourceId, $destinationId): Creator {
            // Stable lock order by id avoids deadlocks when two merges race.
            $ids = [$sourceId, $destinationId];
            sort($ids);
            $first = Creator::query()->whereKey($ids[0])->lockForUpdate()->firstOrFail();
            $second = Creator::query()->whereKey($ids[1])->lockForUpdate()->firstOrFail();

            $source = (string) $first->id === $sourceId ? $first : $second;
            $destination = (string) $first->id === $destinationId ? $first : $second;

            $this->assertCanMerge($actor, $source, $destination);
            $this->assertNoChannelClash($source, $destination);

            $this->cancelPendingClaims((string) $source->id);
            $this->moveChannels((string) $source->id, (string) $destination->id);
            $this->repointContentsAndAppearances((string) $source->id, (string) $destination->id);
            $this->moveExternalLinks($source, $destination);
            $this->mergeManagementAssignments((string) $source->id, (string) $destination->id);
            $this->stripSourceMembers((string) $source->id);
            $this->archiveSource($source);

            return $destination->fresh(['members', 'channels.platform', 'externalLinks']) ?? $destination;
        });
    }

    private function assertCanMerge(User $actor, Creator $source, Creator $destination): void
    {
        if ($destination->status === EntityStatus::Archived) {
            throw new VtualException(__('api/vtual.merge.destination_archived'), 422);
        }

        if ($source->status === EntityStatus::Archived) {
            throw new VtualException(__('api/vtual.merge.source_archived'), 422);
        }

        if ($actor->can('manage any vtual resource')) {
            return;
        }

        // Owner on both (not editor-only).
        $sourceRole = $this->membershipRole($source, $actor);
        $destRole = $this->membershipRole($destination, $actor);

        if ($sourceRole !== CreatorMemberRole::Owner || $destRole !== CreatorMemberRole::Owner) {
            throw new VtualException(__('api/vtual.merge.owner_required'), 403);
        }
    }

    private function membershipRole(Creator $creator, User $actor): ?CreatorMemberRole
    {
        /** @var CreatorMember|null $member */
        $member = CreatorMember::query()
            ->where('creator_id', $creator->id)
            ->where('user_id', $actor->id)
            ->first();

        return $member?->role;
    }

    private function assertNoChannelClash(Creator $source, Creator $destination): void
    {
        $sourceKeys = Channel::query()
            ->where('creator_id', $source->id)
            ->get(['platform_id', 'external_id'])
            ->map(fn (Channel $c) => $c->platform_id.'|'.$c->external_id)
            ->all();

        if ($sourceKeys === []) {
            return;
        }

        $clash = Channel::query()
            ->where('creator_id', $destination->id)
            ->where(function ($q) use ($sourceKeys): void {
                foreach ($sourceKeys as $key) {
                    [$platformId, $externalId] = explode('|', $key, 2);
                    $q->orWhere(function ($inner) use ($platformId, $externalId): void {
                        $inner->where('platform_id', $platformId)
                            ->where('external_id', $externalId);
                    });
                }
            })
            ->first();

        if ($clash !== null) {
            throw new VtualException(__('api/vtual.merge.channel_clash', [
                'external_id' => (string) $clash->external_id,
            ]), 422);
        }
    }

    private function cancelPendingClaims(string $sourceId): void
    {
        CreatorClaim::query()
            ->where('creator_id', $sourceId)
            ->where('status', CreatorClaimStatus::Pending)
            ->update([
                'status' => CreatorClaimStatus::Cancelled->value,
                'closed_reason' => CreatorClaimClosedReason::Merged->value,
            ]);

        ChannelClaim::query()
            ->where('creator_id', $sourceId)
            ->where('status', CreatorClaimStatus::Pending)
            ->update([
                'status' => CreatorClaimStatus::Cancelled->value,
                'closed_reason' => CreatorClaimClosedReason::Merged->value,
            ]);
    }

    private function moveChannels(string $sourceId, string $destinationId): void
    {
        Channel::query()
            ->where('creator_id', $sourceId)
            ->update(['creator_id' => $destinationId]);
    }

    private function repointContentsAndAppearances(string $sourceId, string $destinationId): void
    {
        Content::query()
            ->where('creator_id', $sourceId)
            ->update(['creator_id' => $destinationId]);

        ContentAppearance::query()
            ->where('creator_id', $sourceId)
            ->update(['creator_id' => $destinationId]);
    }

    private function moveExternalLinks(Creator $source, Creator $destination): void
    {
        ExternalLink::query()
            ->where('linkable_type', $source->getMorphClass())
            ->where('linkable_id', $source->id)
            ->update([
                'linkable_id' => $destination->id,
            ]);
    }

    private function mergeManagementAssignments(string $sourceId, string $destinationId): void
    {
        $sourceAssignments = ManagementCreator::query()
            ->where('creator_id', $sourceId)
            ->whereNull('left_at')
            ->get();

        foreach ($sourceAssignments as $row) {
            $existsOnDest = ManagementCreator::query()
                ->where('management_id', $row->management_id)
                ->where('creator_id', $destinationId)
                ->whereNull('left_at')
                ->exists();

            if ($existsOnDest) {
                // Conflict on unique active pair: leave source assignment.
                $row->forceFill(['left_at' => now()])->save();
            } else {
                // May hit unique if historical row exists for dest+management.
                $historical = ManagementCreator::query()
                    ->where('management_id', $row->management_id)
                    ->where('creator_id', $destinationId)
                    ->first();

                if ($historical !== null) {
                    $historical->forceFill([
                        'left_at' => null,
                        'joined_at' => $historical->joined_at ?? now(),
                    ])->save();
                    $row->forceFill(['left_at' => now()])->save();
                } else {
                    $row->forceFill(['creator_id' => $destinationId])->save();
                }
            }
        }
    }

    private function stripSourceMembers(string $sourceId): void
    {
        CreatorMember::query()->where('creator_id', $sourceId)->delete();
    }

    private function archiveSource(Creator $source): void
    {
        $oldSlug = (string) $source->slug;
        $suffix = Str::lower(Str::substr(str_replace('-', '', (string) $source->id), 0, 8));
        $base = 'merged-'.Str::limit($oldSlug, 80, '');
        $newSlug = Str::limit($base.'-'.$suffix, 120, '');

        // Ensure unique if collision.
        $candidate = $newSlug;
        $i = 0;
        while (Creator::query()->where('slug', $candidate)->whereKeyNot($source->id)->exists()) {
            $i++;
            $candidate = Str::limit($newSlug.'-'.$i, 120, '');
        }

        $source->forceFill([
            'status' => EntityStatus::Archived,
            'slug' => $candidate,
        ])->save();
    }
}
