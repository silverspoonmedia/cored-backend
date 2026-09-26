<?php

namespace App\Domain\Vtual\Models;

use App\Domain\Vtual\Enums\ContentAppearanceRole;
use App\Domain\Vtual\Enums\ContentCategory;
use App\Domain\Vtual\Enums\ContentKind;
use App\Domain\Vtual\Enums\ContentStatus;
use App\Domain\Vtual\Enums\ContentType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Canonical platform content (one row per platform + external_id).
 * channel_id / creator_id = primary host; multi-channel via appearances, not multi-ownership.
 */
class Content extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $table = 'vtual_contents';

    protected $fillable = [
        'creator_id',
        'channel_id',
        'platform_id',
        'external_id',
        'type',
        'content_category',
        'content_category_detail',
        'content_type',
        'status',
        'title',
        'description',
        'thumbnail_url',
        'url',
        'concurrent_viewers',
        'scheduled_at',
        'started_at',
        'ended_at',
        'published_at',
        'hydrated_at',
        'first_unavailable_at',
        'region_allowed',
        'region_blocked',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
        'type' => ContentType::class,
        'content_category' => ContentCategory::class,
        'content_type' => ContentKind::class,
        'status' => ContentStatus::class,
            'concurrent_viewers' => 'integer',
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'published_at' => 'datetime',
            'hydrated_at' => 'datetime',
            'first_unavailable_at' => 'datetime',
            'region_allowed' => 'array',
            'region_blocked' => 'array',
        ];
    }

    /**
     * Primary host creator (denormalized from primary channel).
     *
     * @return BelongsTo<Creator, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Creator::class, 'creator_id');
    }

    /**
     * Primary host channel (platform upload/host).
     *
     * @return BelongsTo<Channel, $this>
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class, 'channel_id');
    }

    /**
     * @return BelongsTo<Platform, $this>
     */
    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class, 'platform_id');
    }

    /**
     * @return HasMany<ContentAppearance, $this>
     */
    public function appearances(): HasMany
    {
        return $this->hasMany(ContentAppearance::class, 'content_id');
    }

    /**
     * All channels this content appears on (primary + associations).
     *
     * @return BelongsToMany<Channel, $this>
     */
    public function channels(): BelongsToMany
    {
        return $this->belongsToMany(Channel::class, 'vtual_content_appearances', 'content_id', 'channel_id')
            ->withPivot(['id', 'creator_id', 'role'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<ContentAppearance, $this>
     */
    public function primaryAppearance(): HasMany
    {
        return $this->appearances()->where('role', ContentAppearanceRole::Primary->value);
    }

    /**
     * Primary music/non-music layer. True = music, false = non-music,
     * null = unknown (categoryId unavailable).
     */
    public function isMusic(): ?bool
    {
        return $this->content_type?->value === ContentKind::Music->value
            ? true
            : ($this->content_type?->value === ContentKind::NonMusic->value ? false : null);
    }

    /**
     * Duration derived from the live/archive window. VODs (uploads) carry no
     * persisted duration, so this returns null for them — classification then
     * simply skips the duration signal.
     */
    public function durationSeconds(): ?int
    {
        if ($this->started_at === null || $this->ended_at === null) {
            return null;
        }

        return (int) abs($this->ended_at->diffInSeconds($this->started_at));
    }
}
