<?php

namespace App\Domain\Vtual\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\CreatorClaimClosedReason;
use App\Domain\Vtual\Enums\CreatorClaimStatus;
use App\Domain\Vtual\Enums\CreatorClaimVerifiedVia;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pending attach of a platform channel to a creator (bio token / Accept Anyway).
 * Reuses CreatorClaim* enums (same status/via/closed_reason values).
 */
class ChannelClaim extends Model
{
    use HasUuids;

    protected $table = 'vtual_channel_claims';

    protected $fillable = [
        'user_id',
        'creator_id',
        'platform_id',
        'external_id',
        'profile_input',
        'handle',
        'display_name',
        'url',
        'token',
        'status',
        'closed_reason',
        'attempt_count',
        'last_attempt_at',
        'expires_at',
        'verified_at',
        'verified_via',
        'verified_by_user_id',
        'channel_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CreatorClaimStatus::class,
            'closed_reason' => CreatorClaimClosedReason::class,
            'verified_via' => CreatorClaimVerifiedVia::class,
            'attempt_count' => 'integer',
            'last_attempt_at' => 'datetime',
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
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
     * @return BelongsTo<Channel, $this>
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class, 'channel_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    public function isPending(): bool
    {
        return $this->status === CreatorClaimStatus::Pending;
    }
}
