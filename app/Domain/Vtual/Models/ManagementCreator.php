<?php

namespace App\Domain\Vtual\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class ManagementCreator extends Model
{
    use HasUuids;
    use LogsActivity;

    protected $table = 'vtual_management_creators';

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
        'joined_at',
        'left_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
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
     * @return BelongsTo<Creator, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Creator::class, 'creator_id');
    }

    public function isActiveAssignment(): bool
    {
        return $this->left_at === null;
    }
}
