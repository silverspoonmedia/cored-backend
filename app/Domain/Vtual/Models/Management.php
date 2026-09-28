<?php

namespace App\Domain\Vtual\Models;

use App\Domain\Vtual\Enums\EntityStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Management extends Model
{
    use HasUuids;
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'vtual_managements';

    /**
     * Management audit trail (docs/archive/bootstrap-task-2026-08.md:228). Log name `vtual` groups every
     * management-scoped mutation; personal creator/channel/content surfaces
     * are deliberately excluded — see docs/domains/vtual/reference.md.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('vtual');
    }

    protected $fillable = [
        'slug',
        'name',
        'description',
        'logo_url',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EntityStatus::class,
        ];
    }

    /**
     * @return HasMany<ManagementMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(ManagementMember::class, 'management_id');
    }

    /**
     * @return HasMany<ManagementCreator, $this>
     */
    public function creatorAssignments(): HasMany
    {
        return $this->hasMany(ManagementCreator::class, 'management_id');
    }

    /**
     * Delegated per-creator capability grants (additive on top of the role).
     *
     * @return HasMany<ManagementCreatorGrant, $this>
     */
    public function creatorGrants(): HasMany
    {
        return $this->hasMany(ManagementCreatorGrant::class, 'management_id');
    }

    /**
     * @return MorphMany<ExternalLink, $this>
     */
    public function externalLinks(): MorphMany
    {
        return $this->morphMany(ExternalLink::class, 'linkable');
    }

    public function isActive(): bool
    {
        return $this->status === EntityStatus::Active;
    }
}
