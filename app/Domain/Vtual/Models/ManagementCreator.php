<?php

namespace App\Domain\Vtual\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManagementCreator extends Model
{
    use HasUuids;

    protected $table = 'vtual_management_creators';

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
