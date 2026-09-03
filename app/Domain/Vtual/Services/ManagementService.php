<?php

namespace App\Domain\Vtual\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\EntityStatus;
use App\Domain\Vtual\Enums\ManagementMemberRole;
use App\Domain\Vtual\Exceptions\VtualException;
use App\Domain\Vtual\Models\Creator;
use App\Domain\Vtual\Models\Management;
use App\Domain\Vtual\Models\ManagementCreator;
use App\Domain\Vtual\Models\ManagementMember;
use App\Domain\Vtual\Support\SlugGenerator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ManagementService
{
    public function __construct(
        private readonly ManagementAccessService $access,
        private readonly CreatorAccessService $creatorAccess,
    ) {}

    /**
     * @return Collection<int, Management>
     */
    public function listForUser(User $actor): Collection
    {
        if ($actor->can('view any vtual resource') || $actor->can('manage any vtual resource')) {
            return Management::query()
                ->where('status', EntityStatus::Active)
                ->orderBy('name')
                ->get();
        }

        $ids = ManagementMember::query()
            ->where('user_id', $actor->id)
            ->pluck('management_id');

        return Management::query()
            ->whereIn('id', $ids)
            ->where('status', EntityStatus::Active)
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array{name: string, slug?: string|null, description?: string|null, logo_url?: string|null}  $data
     */
    public function create(User $actor, array $data): Management
    {
        $name = trim($data['name']);
        if ($name === '') {
            throw new VtualException(__('api/vtual.management.name_required'), 422);
        }

        $baseSlug = isset($data['slug']) && is_string($data['slug']) && trim($data['slug']) !== ''
            ? trim($data['slug'])
            : $name;

        return DB::transaction(function () use ($actor, $data, $name, $baseSlug): Management {
            $slug = SlugGenerator::unique($baseSlug, fn (string $s): bool => Management::query()
                ->where('slug', $s)
                ->exists());

            $management = Management::query()->create([
                'slug' => $slug,
                'name' => $name,
                'description' => $data['description'] ?? null,
                'logo_url' => $data['logo_url'] ?? null,
                'status' => EntityStatus::Active,
            ]);

            ManagementMember::query()->create([
                'management_id' => $management->id,
                'user_id' => $actor->id,
                'role' => ManagementMemberRole::Owner,
            ]);

            return $management->load('members.user');
        });
    }

    public function show(User $actor, string $managementId): Management
    {
        $management = $this->access->findOrFail($managementId);
        $this->access->assertCanView($management, $actor);

        return $management->load([
            'members.user',
            'creatorAssignments' => fn ($q) => $q->whereNull('left_at')->with('creator'),
        ]);
    }

    /**
     * Guest-safe public profile: active management, roster limited to
     * publicly listed creators (active + at least one active public channel).
     */
    public function showForPublic(string $slugOrUuid): Management
    {
        $query = Management::query()->where('status', EntityStatus::Active);

        if (\Illuminate\Support\Str::isUuid($slugOrUuid)) {
            $query->whereKey($slugOrUuid);
        } else {
            $query->where('slug', $slugOrUuid);
        }

        $management = $query->first();

        if ($management === null) {
            throw new VtualException(__('api/vtual.management.not_found'), 404);
        }

        return $management->load([
            'externalLinks' => fn ($q) => $q->orderBy('sort_order'),
            'creatorAssignments' => fn ($q) => $q->whereNull('left_at')
                ->with(['creator' => fn ($cq) => $cq
                    ->where('status', EntityStatus::Active)
                    ->whereHas('channels', function ($chq): void {
                        $chq->where('status', \App\Domain\Vtual\Enums\ChannelStatus::Active)
                            ->where('visibility', \App\Domain\Vtual\Enums\ChannelVisibility::Public);
                    })
                    ->with(['channels' => fn ($chq) => $chq
                        ->where('status', \App\Domain\Vtual\Enums\ChannelStatus::Active)
                        ->where('visibility', \App\Domain\Vtual\Enums\ChannelVisibility::Public)
                        ->with('platform'),
                    ]),
                ]),
        ]);
    }

    /**
     * @param  array{name?: string, slug?: string, description?: string|null, logo_url?: string|null, status?: string}  $data
     */
    public function update(User $actor, string $managementId, array $data): Management
    {
        $management = $this->access->findOrFail($managementId);
        $this->access->assertCanMutate($management, $actor);

        if (array_key_exists('name', $data)) {
            $name = trim((string) $data['name']);
            if ($name === '') {
                throw new VtualException(__('api/vtual.management.name_required'), 422);
            }
            $management->name = $name;
        }

        if (array_key_exists('slug', $data) && is_string($data['slug']) && trim($data['slug']) !== '') {
            $slug = SlugGenerator::from(trim($data['slug']));
            $taken = Management::query()
                ->where('slug', $slug)
                ->whereKeyNot($management->id)
                ->exists();
            if ($taken) {
                throw new VtualException(__('api/vtual.management.slug_taken'), 422);
            }
            $management->slug = $slug;
        }

        if (array_key_exists('description', $data)) {
            $management->description = $data['description'];
        }

        if (array_key_exists('logo_url', $data)) {
            $management->logo_url = $data['logo_url'];
        }

        if (array_key_exists('status', $data) && is_string($data['status'])) {
            $management->status = EntityStatus::from($data['status']);
        }

        $management->save();

        return $management->refresh()->load('members.user');
    }

    /**
     * Attach a creator to this management. Actor must manage the org and mutate the creator.
     */
    public function assignCreator(User $actor, string $managementId, string $creatorId): ManagementCreator
    {
        $management = $this->access->findOrFail($managementId);
        $this->access->assertCanAssignCreators($management, $actor);

        $creator = Creator::query()->findOrFail($creatorId);
        $this->creatorAccess->assertCanMutate($creator, $actor);

        /** @var ManagementCreator $assignment */
        $assignment = ManagementCreator::query()->firstOrNew([
            'management_id' => $management->id,
            'creator_id' => $creator->id,
        ]);

        if ($assignment->exists && $assignment->left_at === null) {
            return $assignment->load('creator');
        }

        $assignment->joined_at = now();
        $assignment->left_at = null;
        $assignment->save();

        return $assignment->load('creator');
    }

    public function unassignCreator(User $actor, string $managementId, string $creatorId): ManagementCreator
    {
        $management = $this->access->findOrFail($managementId);
        $this->access->assertCanAssignCreators($management, $actor);

        /** @var ManagementCreator|null $assignment */
        $assignment = ManagementCreator::query()
            ->where('management_id', $management->id)
            ->where('creator_id', $creatorId)
            ->whereNull('left_at')
            ->first();

        if ($assignment === null) {
            throw new VtualException(__('api/vtual.management.assignment_not_found'), 404);
        }

        $assignment->left_at = now();
        $assignment->save();

        return $assignment->load('creator');
    }
}
