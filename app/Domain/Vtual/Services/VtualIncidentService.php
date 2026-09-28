<?php

namespace App\Domain\Vtual\Services;

use App\Domain\Vtual\Enums\IncidentStatus;
use App\Domain\Vtual\Models\VtualIncident;
use App\Domain\Vtual\Support\VtualIncidentRealtime;
use App\Domain\Vtual\Support\VtualIncidentRecorder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Read + triage side of the incident table.
 *
 * The write side is {@see VtualIncidentRecorder} —
 * nothing here creates an incident, and nothing there changes triage state.
 */
class VtualIncidentService
{
    public function __construct(
        private readonly VtualIncidentRealtime $realtime,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, VtualIncident>
     */
    public function list(array $filters, int $limit = 50): Collection
    {
        $query = VtualIncident::query()
            ->with([
                'platform:id,key,name',
                'channel:id,creator_id,platform_id,external_id,handle,display_name',
                'credential:id,platform_id,label',
                'acknowledger:id,name,email',
                'resolver:id,name,email',
            ])
            ->latest('last_seen_at');

        if (! empty($filters['status']) && is_string($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['severity']) && is_string($filters['severity'])) {
            $query->where('severity', $filters['severity']);
        }

        if (! empty($filters['kind']) && is_string($filters['kind'])) {
            $query->where('kind', $filters['kind']);
        }

        foreach (['platform_id', 'channel_id', 'credential_id'] as $column) {
            if (! empty($filters[$column]) && is_string($filters[$column])) {
                $query->where($column, $filters[$column]);
            }
        }

        if (! empty($filters['q']) && is_string($filters['q'])) {
            $term = '%'.$filters['q'].'%';
            $query->where(function ($q) use ($term): void {
                $q->where('title', 'like', $term)
                    ->orWhere('detail', 'like', $term);
            });
        }

        if (! empty($filters['from']) && is_string($filters['from'])) {
            $query->where('last_seen_at', '>=', Carbon::parse($filters['from'])->startOfDay());
        }

        if (! empty($filters['to']) && is_string($filters['to'])) {
            $query->where('last_seen_at', '<=', Carbon::parse($filters['to'])->endOfDay());
        }

        return $query->limit($limit)->get();
    }

    public function get(string $id): VtualIncident
    {
        return VtualIncident::query()
            ->with([
                'platform:id,key,name',
                'channel:id,creator_id,platform_id,external_id,handle,display_name',
                'credential:id,platform_id,label',
                'acknowledger:id,name,email',
                'resolver:id,name,email',
            ])
            ->findOrFail($id);
    }

    /**
     * Apply a triage transition and stamp the acting user.
     *
     * `open` clears the triage stamps rather than pretending the incident was
     * never seen: the occurrence count and `first_seen_at` stay, so reopening by
     * hand does not rewrite history.
     */
    public function updateStatus(VtualIncident $incident, IncidentStatus $status, ?string $actorId = null): VtualIncident
    {
        $now = Carbon::now();

        switch ($status) {
            case IncidentStatus::Open:
                $incident->status = IncidentStatus::Open;
                $incident->acknowledged_at = null;
                $incident->acknowledged_by = null;
                $incident->resolved_at = null;
                $incident->resolved_by = null;
                break;

            case IncidentStatus::Acknowledged:
                $incident->status = IncidentStatus::Acknowledged;
                $incident->acknowledged_at = $incident->acknowledged_at ?? $now;
                $incident->acknowledged_by = $incident->acknowledged_by ?? $actorId;
                // Acknowledging is not resolving: a previously resolved row that
                // someone is re-triaging must not keep claiming a fix.
                $incident->resolved_at = null;
                $incident->resolved_by = null;
                break;

            case IncidentStatus::Resolved:
                $incident->status = IncidentStatus::Resolved;
                $incident->resolved_at = $now;
                $incident->resolved_by = $actorId;
                break;
        }

        $incident->save();

        $fresh = $incident->refresh()->load([
            'platform:id,key,name',
            'channel:id,creator_id,platform_id,external_id,handle,display_name',
            'credential:id,platform_id,label',
            'acknowledger:id,name,email',
            'resolver:id,name,email',
        ]);

        $this->realtime->changed(
            'status',
            (string) $fresh->id,
            $fresh->kind,
            $fresh->severity,
            $fresh->status,
        );

        return $fresh;
    }
}
