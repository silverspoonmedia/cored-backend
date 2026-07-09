<?php

namespace App\Domain\Approval\Models;

use App\Domain\Identity\Models\User;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalInstanceStepApprover extends Model
{
    use HasUuids;

    protected $fillable = [
        'instance_step_id',
        'workflow_step_approver_id',
        'required_role',
        'slot_label',
        'status',
        'assigned_user_id',
        'actor_id',
        'delegated_by',
        'delegation_id',
        'reassigned_to',
        'reassignment_reason',
        'acted_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'acted_at' => 'datetime',
        ];
    }

    public function instanceStep(): BelongsTo
    {
        return $this->belongsTo(ApprovalInstanceStep::class, 'instance_step_id');
    }

    public function workflowStepApprover(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflowStepApprover::class, 'workflow_step_approver_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function delegatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegated_by');
    }

    public function delegation(): BelongsTo
    {
        return $this->belongsTo(ApprovalDelegation::class, 'delegation_id');
    }

    public function reassignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reassigned_to');
    }
}
