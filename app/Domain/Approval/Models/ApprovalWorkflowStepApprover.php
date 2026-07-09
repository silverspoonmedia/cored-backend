<?php

namespace App\Domain\Approval\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalWorkflowStepApprover extends Model
{
    use HasUuids;

    protected $fillable = [
        'workflow_step_id',
        'required_role',
        'slot_label',
    ];

    public function workflowStep(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflowStep::class, 'workflow_step_id');
    }

    public function instanceApprovers(): HasMany
    {
        return $this->hasMany(ApprovalInstanceStepApprover::class, 'workflow_step_approver_id');
    }
}
