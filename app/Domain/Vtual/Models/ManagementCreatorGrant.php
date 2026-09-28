<?php

namespace App\Domain\Vtual\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\VtualGrantCapability;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Additive per-creator delegation for one org member
 * (docs/domains/vtual/reference-capability-grants.md).
 *
 * Part of the management audit trail (`log_name = vtual`): who was allowed to
 * touch which creator is exactly the kind of grant change the trail exists for.
 */
class ManagementCreatorGrant extends Model
{
    use HasUuids;
    use LogsActivity;

    protected $table = 'vtual_management_creator_grants';

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('vtual');
    }

    protected $fillable = [
        'management_id',
        'member_id',
        'creator_id',
        'capability',
        'granted_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'capability' => VtualGrantCapability::class,
        ];
    }

    /**
     * @return BelongsTo<Management, $this>
     */
    public function management(): BelongsTo
    {
        return $this->belongsTo(Management::class, 'management_id');
    }

    /**
     * @return BelongsTo<ManagementMember, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(ManagementMember::class, 'member_id');
    }

    /**
     * @return BelongsTo<Creator, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Creator::class, 'creator_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}
