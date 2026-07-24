<?php

namespace App\Domain\Vtual\Models;

use App\Domain\Vtual\Enums\EntityStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Management extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $table = 'vtual_managements';

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
