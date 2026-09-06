<?php

namespace App\Domain\Vtual\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\ManagementInviteStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Invite linking (or creating) a vTuber profile into a management org.
 * Mode `existing` pins a creator the invitee already owns; mode `new`
 * carries a display name and mints the profile on accept.
 */
class CreatorManagementInvite extends Model
{
    use HasUuids;

    protected $table = 'vtual_creator_management_invites';

    protected $fillable = [
        'management_id',
        'email',
        'user_id',
        'mode',
        'creator_id',
        'new_display_name',
        'token',
        'status',
        'invited_by',
        'expires_at',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ManagementInviteStatus::class,
            'expires_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function management(): BelongsTo
    {
        return $this->belongsTo(Management::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Creator::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isPending(): bool
    {
        return $this->status === ManagementInviteStatus::Pending;
    }

    public function isExpired(): bool
    {
        return $this->status === ManagementInviteStatus::Expired
            || ($this->isPending() && $this->expires_at !== null && $this->expires_at->isPast());
    }
}
