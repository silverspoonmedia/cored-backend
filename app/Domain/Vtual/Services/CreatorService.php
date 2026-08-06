<?php

namespace App\Domain\Vtual\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\CreatorMemberRole;
use App\Domain\Vtual\Enums\EntityStatus;
use App\Domain\Vtual\Exceptions\VtualException;
use App\Domain\Vtual\Models\Creator;
use App\Domain\Vtual\Models\CreatorMember;
use App\Domain\Vtual\Support\SlugGenerator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CreatorService
{
    public function __construct(
        private readonly CreatorAccessService $access,
    ) {}

    /**
     * Creators the actor can see (membership or any-view permission).
     *
     * @return Collection<int, Creator>
     */
    public function listForUser(User $actor): Collection
    {
        if ($actor->can('view any vtual resource') || $actor->can('manage any vtual resource')) {
            return Creator::query()
                ->where('status', EntityStatus::Active)
                ->withCount('channels')
                ->orderBy('display_name')
                ->get();
        }

        $ids = CreatorMember::query()
            ->where('user_id', $actor->id)
            ->pluck('creator_id');

        return Creator::query()
            ->whereIn('id', $ids)
            ->where('status', EntityStatus::Active)
            ->withCount('channels')
            ->orderBy('display_name')
            ->get();
    }

    /**
     * @param  array{display_name: string, slug?: string|null, bio?: string|null, avatar_url?: string|null}  $data
     */
    public function create(User $actor, array $data): Creator
    {
        return $this->createInternal($actor, $data, bindOwner: true);
    }

    /**
     * Staff seed: active creator with no membership (claimable orphan).
     *
     * @param  array{display_name: string, slug?: string|null, bio?: string|null, avatar_url?: string|null}  $data
     */
    public function createOrphan(User $actor, array $data): Creator
    {
        if (! $actor->can('manage any vtual resource')) {
            throw new VtualException(__('api/vtual.policy.denied'), 403);
        }

        return $this->createInternal($actor, $data, bindOwner: false);
    }

    /**
     * Claimable = active + no owner membership (admin-seeded orphan).
     */
    public function isClaimable(Creator $creator): bool
    {
        if ($creator->status !== EntityStatus::Active) {
            return false;
        }

        return ! CreatorMember::query()
            ->where('creator_id', $creator->id)
            ->where('role', CreatorMemberRole::Owner)
            ->exists();
    }

    /**
     * Active creators without an owner member (for claim UI).
     *
     * @return Collection<int, Creator>
     */
    public function listClaimable(): Collection
    {
        return Creator::query()
            ->where('status', EntityStatus::Active)
            ->whereDoesntHave('members', function ($q): void {
                $q->where('role', CreatorMemberRole::Owner);
            })
            ->with(['channels.platform'])
            ->withCount('channels')
            ->orderBy('display_name')
            ->get();
    }

    /**
     * @param  array{display_name: string, slug?: string|null, bio?: string|null, avatar_url?: string|null}  $data
     */
    private function createInternal(User $actor, array $data, bool $bindOwner): Creator
    {
        $displayName = trim($data['display_name']);
        if ($displayName === '') {
            throw new VtualException(__('api/vtual.creator.name_required'), 422);
        }

        $explicitSlug = isset($data['slug']) && is_string($data['slug']) && trim($data['slug']) !== ''
            ? trim($data['slug'])
            : null;

        return DB::transaction(function () use ($actor, $data, $displayName, $explicitSlug, $bindOwner): Creator {
            $exists = fn (string $s): bool => Creator::query()->where('slug', $s)->exists();

            if ($explicitSlug !== null) {
                $slug = SlugGenerator::unique($explicitSlug, $exists);
            } elseif (! $bindOwner) {
                $slug = SlugGenerator::uniquePrefixed($displayName, $exists);
            } else {
                $slug = SlugGenerator::unique($displayName, $exists);
            }

            $creator = Creator::query()->create([
                'slug' => $slug,
                'display_name' => $displayName,
                'bio' => $data['bio'] ?? null,
                'avatar_url' => $data['avatar_url'] ?? null,
                'status' => EntityStatus::Active,
            ]);

            if ($bindOwner) {
                CreatorMember::query()->create([
                    'creator_id' => $creator->id,
                    'user_id' => $actor->id,
                    'role' => CreatorMemberRole::Owner,
                ]);
            }

            return $creator->load('members.user');
        });
    }

    public function show(User $actor, string $creatorId): Creator
    {
        $creator = $this->access->findOrFail($creatorId);
        $this->access->assertCanView($creator, $actor);

        return $creator->load([
            'members.user',
            'channels.platform',
            'externalLinks',
        ])->loadCount('channels');
    }

    /**
     * @param  array{display_name?: string, slug?: string, bio?: string|null, avatar_url?: string|null, status?: string}  $data
     */
    public function update(User $actor, string $creatorId, array $data): Creator
    {
        $creator = $this->access->findOrFail($creatorId);
        $this->access->assertCanMutate($creator, $actor);

        if (array_key_exists('display_name', $data)) {
            $name = trim((string) $data['display_name']);
            if ($name === '') {
                throw new VtualException(__('api/vtual.creator.name_required'), 422);
            }
            $creator->display_name = $name;
        }

        if (array_key_exists('slug', $data) && is_string($data['slug']) && trim($data['slug']) !== '') {
            $slug = SlugGenerator::from(trim($data['slug']));
            $taken = Creator::query()
                ->where('slug', $slug)
                ->whereKeyNot($creator->id)
                ->exists();
            if ($taken) {
                throw new VtualException(__('api/vtual.creator.slug_taken'), 422);
            }
            $creator->slug = $slug;
        }

        if (array_key_exists('bio', $data)) {
            $creator->bio = $data['bio'];
        }

        if (array_key_exists('avatar_url', $data)) {
            $creator->avatar_url = $data['avatar_url'];
        }

        if (array_key_exists('status', $data) && is_string($data['status'])) {
            $creator->status = EntityStatus::from($data['status']);
        }

        $creator->save();

        return $creator->refresh()->load(['members.user', 'channels.platform']);
    }

    public function archive(User $actor, string $creatorId): Creator
    {
        $creator = $this->access->findOrFail($creatorId);
        $this->access->assertCanManageMembers($creator, $actor);

        $creator->status = EntityStatus::Archived;
        $creator->save();

        return $creator->refresh();
    }
}
