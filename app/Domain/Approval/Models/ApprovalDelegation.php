<?php

namespace App\Domain\Approval\Models;

use App\Domain\Identity\Models\User;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalDelegation extends Model
{
    use HasUuids;

    protected $fillable = [
        'delegator_id',
        'delegate_id',
        'scope',
        'document_type',
        'instance_id',
        'valid_from',
        'valid_until',
        'reason',
        'is_active',
        'created_by',
        'revoked_at',
        'revoked_by',
    ];

    protected function casts(): array
    {
        return [
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
            'is_active' => 'boolean',
            'revoked_at' => 'datetime',
        ];
    }

    public function delegator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegator_id');
    }

    public function delegate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegate_id');
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(ApprovalInstance::class, 'instance_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ApprovalDelegationLog::class, 'delegation_id');
    }
}
