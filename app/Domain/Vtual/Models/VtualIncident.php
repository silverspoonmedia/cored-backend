<?php

namespace App\Domain\Vtual\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\IncidentKind;
use App\Domain\Vtual\Enums\IncidentSeverity;
use App\Domain\Vtual\Enums\IncidentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Grouped vTual integration incident (see the migration for the three-store
 * split). One row per distinct failure, with an occurrence count and a triage
 * status — not one row per occurrence.
 *
 * Deliberately **not** in the `vtual` activity log: an incident is machine
 * state, and `VtualIncidentRecorder` writes it from background paths that have
 * no authenticated actor.
 */
class VtualIncident extends Model
{
    use HasUuids;

    protected $table = 'vtual_incidents';

    protected $fillable = [
        'fingerprint',
        'kind',
        'severity',
        'status',
        'platform_id',
        'channel_id',
        'credential_id',
        'title',
        'detail',
        'context',
        'occurrences',
        'first_seen_at',
        'last_seen_at',
        'acknowledged_at',
        'acknowledged_by',
        'resolved_at',
        'resolved_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => IncidentKind::class,
            'severity' => IncidentSeverity::class,
            'status' => IncidentStatus::class,
            'context' => 'array',
            'occurrences' => 'integer',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Platform, $this>
     */
    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class, 'platform_id');
    }

    /**
     * @return BelongsTo<Channel, $this>
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class, 'channel_id');
    }

    /**
     * @return BelongsTo<PlatformCredential, $this>
     */
    public function credential(): BelongsTo
    {
        return $this->belongsTo(PlatformCredential::class, 'credential_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function acknowledger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
