<?php

namespace App\Domain\Vtual\Models;

use App\Domain\Vtual\Enums\CreatorClaimVerifyPoolStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreatorClaimVerifyPoolItem extends Model
{
    use HasUuids;

    protected $table = 'vtual_creator_claim_verify_pool';

    protected $fillable = [
        'claim_id',
        'status',
        'requested_at',
        'processed_at',
        'error',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CreatorClaimVerifyPoolStatus::class,
            'requested_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CreatorClaim, $this>
     */
    public function claim(): BelongsTo
    {
        return $this->belongsTo(CreatorClaim::class, 'claim_id');
    }
}
