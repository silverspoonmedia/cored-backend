<?php

namespace App\Domain\Vtual\Models;

use App\Domain\Vtual\Enums\VtualEventStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A vTual event submitted by a management org (Owner/Admin). Published
 * directly — no moderation flow. Public surface only exposes upcoming
 * (scheduled/live) rows.
 */
class VtualEvent extends Model
{
    use HasUuids;
    use LogsActivity;
    use SoftDeletes;

    protected $table = 'vtual_events';

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
        'creator_id',
        'slug',
        'title',
        'description',
        'thumbnail_url',
        'url',
        'starts_at',
        'ends_at',
        'timezone',
        'status',
        'is_featured',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'status' => VtualEventStatus::class,
            'is_featured' => 'boolean',
        ];
    }

    public function management(): BelongsTo
    {
        return $this->belongsTo(Management::class, 'management_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Creator::class, 'creator_id');
    }

    public function isUpcoming(): bool
    {
        return in_array($this->status, VtualEventStatus::publicVisible(), true);
    }
}
