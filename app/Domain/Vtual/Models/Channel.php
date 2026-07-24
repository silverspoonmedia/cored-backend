<?php

namespace App\Domain\Vtual\Models;

use App\Domain\Vtual\Enums\ChannelStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Channel extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $table = 'vtual_channels';

    protected $fillable = [
        'creator_id',
        'platform_id',
        'external_id',
        'handle',
        'display_name',
        'description',
        'avatar_url',
        'banner_url',
        'url',
        'platform_created_at',
        'status',
        'last_synced_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ChannelStatus::class,
            'platform_created_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Creator, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Creator::class, 'creator_id');
    }

    /**
     * @return BelongsTo<Platform, $this>
     */
    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class, 'platform_id');
    }

    /**
     * Contents where this channel is the primary host (denormalized channel_id).
     *
     * @return HasMany<Content, $this>
     */
    public function contents(): HasMany
    {
        return $this->hasMany(Content::class, 'channel_id');
    }

    /**
     * Alias: primary-host contents only.
     *
     * @return HasMany<Content, $this>
     */
    public function primaryContents(): HasMany
    {
        return $this->contents();
    }

    /**
     * @return HasMany<ContentAppearance, $this>
     */
    public function contentAppearances(): HasMany
    {
        return $this->hasMany(ContentAppearance::class, 'channel_id');
    }

    /**
     * Contents this channel appears on (primary or association) via pivot.
     *
     * @return BelongsToMany<Content, $this>
     */
    public function appearingContents(): BelongsToMany
    {
        return $this->belongsToMany(Content::class, 'vtual_content_appearances', 'channel_id', 'content_id')
            ->withPivot(['id', 'creator_id', 'role'])
            ->withTimestamps();
    }
}
