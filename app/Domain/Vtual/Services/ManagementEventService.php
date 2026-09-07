<?php

namespace App\Domain\Vtual\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\EntityStatus;
use App\Domain\Vtual\Enums\VtualEventStatus;
use App\Domain\Vtual\Exceptions\VtualException;
use App\Domain\Vtual\Models\Management;
use App\Domain\Vtual\Models\VtualEvent;
use App\Domain\Vtual\Support\SlugGenerator;
use App\Domain\Vtual\Support\VtualManagementRealtime;
use Illuminate\Database\Eloquent\Collection;

/**
 * Management-submitted vTual events. Mutations are reserved to org
 * Owner/Admin (staff bypass via assertCanAssignCreators); rows publish
 * directly — no moderation flow. The public surface only exposes upcoming
 * (scheduled/live) rows through VtuberProfileService::upcomingEvents.
 */
class ManagementEventService
{
    public function __construct(
        private readonly ManagementAccessService $access,
        private readonly VtualManagementRealtime $realtime,
    ) {}

    /**
     * Cross-management list for the standalone SPA page: staff sees all,
     * org members see events of managements they belong to.
     *
     * @return Collection<int, VtualEvent>
     */
    public function listForActor(User $actor, int $perPage = 15, int $page = 1): array
    {
        $query = VtualEvent::query()
            ->with(['management', 'creator'])
            ->orderByDesc('starts_at');

        if (! $actor->can('manage any vtual resource') && ! $actor->can('view any vtual resource')) {
            $managementIds = $this->actorManagementIds($actor);

            if ($managementIds === []) {
                return ['items' => collect(), 'total' => 0];
            }

            $query->whereIn('management_id', $managementIds);
        }

        $total = (clone $query)->count();
        $items = $query
            ->forPage(max(1, $page), max(1, $perPage))
            ->get();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * @return array{items: Collection<int, VtualEvent>, total: int}
     */
    public function listForManagement(User $actor, string $managementId, int $perPage = 15, int $page = 1): array
    {
        $management = $this->access->findOrFail($managementId);
        $this->access->assertCanView($management, $actor);

        $query = VtualEvent::query()
            ->where('management_id', $management->id)
            ->with(['creator'])
            ->orderByDesc('starts_at');

        $total = (clone $query)->count();
        $items = $query
            ->forPage(max(1, $page), max(1, $perPage))
            ->get();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * @param  array{title: string, description?: string|null, thumbnail_url?: string|null, url?: string|null, starts_at: string, ends_at?: string|null, timezone?: string|null, creator_id?: string|null, is_featured?: bool}  $data
     */
    public function create(User $actor, string $managementId, array $data): VtualEvent
    {
        $management = $this->access->findOrFail($managementId);
        $this->access->assertCanAssignCreators($management, $actor);

        $event = VtualEvent::query()->create([
            'management_id' => $management->id,
            'creator_id' => $data['creator_id'] ?? null,
            'slug' => SlugGenerator::unique(
                $data['title'],
                fn (string $s): bool => VtualEvent::query()->where('slug', $s)->exists(),
            ),
            'title' => trim($data['title']),
            'description' => $data['description'] ?? null,
            'thumbnail_url' => $data['thumbnail_url'] ?? null,
            'url' => $data['url'] ?? null,
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'] ?? null,
            'timezone' => $data['timezone'] ?? null,
            'status' => VtualEventStatus::Scheduled,
            'is_featured' => (bool) ($data['is_featured'] ?? false),
        ]);

        $this->realtime->changed($management->id, 'event', (string) $event->id, 'created');

        return $event->load(['management', 'creator']);
    }

    /**
     * Cancel an upcoming event (Owner/Admin of the owning org). Ended events
     * are immutable — they are history.
     */
    public function cancel(User $actor, string $managementId, string $eventId): VtualEvent
    {
        $management = $this->access->findOrFail($managementId);
        $this->access->assertCanAssignCreators($management, $actor);

        $event = VtualEvent::query()
            ->where('management_id', $management->id)
            ->whereKey($eventId)
            ->first();

        if ($event === null) {
            throw new VtualException(__('api/vtual.management.event_not_found'), 404);
        }

        if ($event->status !== VtualEventStatus::Scheduled && $event->status !== VtualEventStatus::Live) {
            throw new VtualException(__('api/vtual.management.event_not_actionable'), 422);
        }

        $event->status = VtualEventStatus::Cancelled;
        $event->save();

        $this->realtime->changed($management->id, 'event', (string) $event->id, 'cancelled');

        return $event->load(['management', 'creator']);
    }

    /**
     * @return list<string>
     */
    private function actorManagementIds(User $actor): array
    {
        return Management::query()
            ->whereHas('members', fn ($q) => $q->where('user_id', $actor->id))
            ->where('status', EntityStatus::Active)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();
    }
}
