<?php

namespace App\Domain\Vtual\Models;

use App\Domain\Vtual\Enums\ContentAppearanceRole;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Channel appearance on canonical content (primary host or extra association).
 * Not multi-ownership of the platform video.
 */
class ContentAppearance extends Model
{
    use HasUuids;

    protected $table = 'vtual_content_appearances';

    protected $fillable = [
        'content_id',
        'channel_id',
        'creator_id',
        'role',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => ContentAppearanceRole::class,
        ];
    }

    /**
     * @return BelongsTo<Content, $this>
     */
    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class, 'content_id');
    }

    /**
     * @return BelongsTo<Channel, $this>
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class, 'channel_id');
    }

    /**
     * @return BelongsTo<Creator, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Creator::class, 'creator_id');
    }

    public function isPrimary(): bool
    {
        return $this->role === ContentAppearanceRole::Primary;
    }
}
