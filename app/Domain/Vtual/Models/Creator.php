<?php

namespace App\Domain\Vtual\Models;

use App\Domain\Vtual\Enums\EntityStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Creator extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $table = 'vtual_creators';

    protected $fillable = [
        'slug',
        'display_name',
        'bio',
        'avatar_url',
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
     * @return HasMany<CreatorMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(CreatorMember::class, 'creator_id');
    }

    /**
     * @return HasMany<CreatorClaim, $this>
     */
    public function claims(): HasMany
    {
        return $this->hasMany(CreatorClaim::class, 'creator_id');
    }

    /**
     * @return HasMany<Channel, $this>
     */
    public function channels(): HasMany
    {
        return $this->hasMany(Channel::class, 'creator_id');
    }

    /**
     * @return HasMany<ChannelClaim, $this>
     */
    public function channelClaims(): HasMany
    {
        return $this->hasMany(ChannelClaim::class, 'creator_id');
    }

    /**
     * @return HasMany<Content, $this>
     */
    public function contents(): HasMany
    {
        return $this->hasMany(Content::class, 'creator_id');
    }

    /**
     * @return MorphMany<ExternalLink, $this>
     */
    public function externalLinks(): MorphMany
    {
        return $this->morphMany(ExternalLink::class, 'linkable');
    }

    /**
     * @return HasMany<ManagementCreator, $this>
     */
    public function managementAssignments(): HasMany
    {
        return $this->hasMany(ManagementCreator::class, 'creator_id');
    }

    public function isActive(): bool
    {
        return $this->status === EntityStatus::Active;
    }
}
