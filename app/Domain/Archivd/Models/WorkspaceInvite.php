<?php

namespace App\Domain\Archivd\Models;

use App\Domain\Archivd\Enums\WorkspaceInviteStatus;
use App\Domain\Archivd\Enums\WorkspaceRole;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceInvite extends Model
{
    use HasUuids;

    protected $table = 'archivd_workspace_invites';

    protected $fillable = [
        'workspace_id',
        'email',
        'user_id',
        'role',
        'token',
        'status',
        'invited_by',
        'expires_at',
        'accepted_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => WorkspaceRole::class,
            'status' => WorkspaceInviteStatus::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isPending(): bool
    {
        return $this->status === WorkspaceInviteStatus::Pending
            && $this->expires_at !== null
            && $this->expires_at->isFuture();
    }

    public function isExpired(): bool
    {
        return $this->status === WorkspaceInviteStatus::Pending
            && $this->expires_at !== null
            && $this->expires_at->isPast();
    }
}
