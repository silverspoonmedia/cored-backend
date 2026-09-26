<?php

namespace App\Domain\Vtual\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\ManagementInviteStatus;
use App\Domain\Vtual\Enums\ManagementMemberRole;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class ManagementInvite extends Model
{
    use HasUuids;
    use LogsActivity;

    protected $table = 'vtual_management_invites';

    public function getActivitylogOptions(): LogOptions
    {
        // `token` is a bearer secret for the invite-respond endpoint: log the
        // lifecycle without ever writing it into the activity log.
        return LogOptions::defaults()
            ->logFillable()
            ->logExcept(['token'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('vtual');
    }

    protected $fillable = [
        'management_id',
        'email',
        'user_id',
        'role',
        'token',
        'status',
        'invited_by',
        'expires_at',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'role' => ManagementMemberRole::class,
            'status' => ManagementInviteStatus::class,
            'expires_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function management(): BelongsTo
    {
        return $this->belongsTo(Management::class);
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
