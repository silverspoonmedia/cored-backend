<?php

namespace App\Domain\FileHosting\Models;

use App\Domain\FileHosting\Enums\WorkspaceKind;
use App\Domain\FileHosting\Enums\WorkspaceMemberStatus;
use App\Domain\FileHosting\Enums\WorkspaceStatus;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workspace extends Model
{
    use HasUuids;

    protected $table = 'file_hosting_workspaces';

    protected $fillable = [
        'owner_user_id',
        'name',
        'status',
        'kind',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => WorkspaceStatus::class,
            'kind' => WorkspaceKind::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * @return HasMany<WorkspaceMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(WorkspaceMember::class, 'workspace_id');
    }

    /**
     * @return HasMany<WorkspaceMember, $this>
     */
    public function activeMembers(): HasMany
    {
        return $this->members()->where('status', WorkspaceMemberStatus::Active);
    }

    /**
     * @return HasMany<WorkspaceInvite, $this>
     */
    public function invites(): HasMany
    {
        return $this->hasMany(WorkspaceInvite::class, 'workspace_id');
    }

    /**
     * @return HasMany<Folder, $this>
     */
    public function folders(): HasMany
    {
        return $this->hasMany(Folder::class, 'workspace_id');
    }

    /**
     * @return HasMany<HostedFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(HostedFile::class, 'workspace_id');
    }

    public function isActive(): bool
    {
        return $this->status === WorkspaceStatus::Active;
    }

    public function isArchived(): bool
    {
        return $this->status === WorkspaceStatus::Archived;
    }

    public function isSystem(): bool
    {
        return $this->kind->isSystem();
    }
}
