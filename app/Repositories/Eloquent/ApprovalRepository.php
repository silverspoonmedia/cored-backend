<?php

namespace App\Repositories\Eloquent;

use App\Models\ApprovalInstance;
use App\Models\ApprovalInstanceStep;
use App\Models\ApprovalInstanceStepApprover;
use App\Models\ApprovalWorkflow;
use App\Models\User;
use App\Repositories\Contracts\ApprovalRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class ApprovalRepository implements ApprovalRepositoryInterface
{
    public function findActiveWorkflow(string $documentType): ?ApprovalWorkflow
    {
        return ApprovalWorkflow::where('document_type', $documentType)
            ->where('is_active', true)
            ->with(['steps.approvers'])
            ->first();
    }

    public function findInstance(string $documentType, string $documentId): ?ApprovalInstance
    {
        return ApprovalInstance::where('document_type', $documentType)
            ->where('document_id', $documentId)
            ->with(['steps.approvers'])
            ->first();
    }

    public function createInstance(ApprovalWorkflow $workflow, Model $document, string $submittedBy): ApprovalInstance
    {
        $instance = ApprovalInstance::create([
            'workflow_id' => $workflow->id,
            'workflow_version' => $workflow->version,
            'document_type' => $workflow->document_type,
            'document_id' => $document->id,
            'status' => 'PENDING',
            'submitted_by' => $submittedBy,
        ]);

        return $instance->load(['steps.approvers']);
    }

    public function findPendingApprovals(string $userId): Collection
    {
        return ApprovalInstanceStepApprover::with([
            'instanceStep.instance',
            'instanceStep.workflowStep',
            'assignedUser',
        ])
            ->where('assigned_user_id', $userId)
            ->where('status', 'WAITING')
            ->whereHas('instanceStep', function ($query) {
                $query->where('status', 'IN_PROGRESS');
            })
            ->orderBy('created_at', 'asc')
            ->get();
    }

    public function findSlot(string $slotId): ?ApprovalInstanceStepApprover
    {
        return ApprovalInstanceStepApprover::with([
            'instanceStep.instance',
            'instanceStep.workflowStep',
            'assignedUser',
            'delegation',
        ])->find($slotId);
    }

    public function getUsersWithRole(string $role): Collection
    {
        return User::role($role)->get();
    }

    public function createInstanceStep(
        string $instanceId,
        string $workflowStepId,
        int $stepOrder,
        string $stepName,
        string $status,
        ?array $conditionEvaluated = null
    ): ApprovalInstanceStep {
        return ApprovalInstanceStep::create([
            'instance_id' => $instanceId,
            'workflow_step_id' => $workflowStepId,
            'step_order' => $stepOrder,
            'step_name' => $stepName,
            'status' => $status,
            'condition_evaluated' => $conditionEvaluated,
        ]);
    }

    public function createApproverSlot(
        string $instanceStepId,
        string $workflowStepApproverId,
        string $requiredRole,
        string $slotLabel,
        string $assignedUserId
    ): ApprovalInstanceStepApprover {
        return ApprovalInstanceStepApprover::create([
            'instance_step_id' => $instanceStepId,
            'workflow_step_approver_id' => $workflowStepApproverId,
            'required_role' => $requiredRole,
            'slot_label' => $slotLabel,
            'status' => 'WAITING',
            'assigned_user_id' => $assignedUserId,
        ]);
    }

    public function getActiveStepForInstance(string $instanceId): ?ApprovalInstanceStep
    {
        return ApprovalInstanceStep::where('instance_id', $instanceId)
            ->where('status', 'IN_PROGRESS')
            ->with(['approvers', 'workflowStep'])
            ->first();
    }

    public function getNextWaitingStep(string $instanceId): ?ApprovalInstanceStep
    {
        return ApprovalInstanceStep::where('instance_id', $instanceId)
            ->where('status', 'WAITING')
            ->orderBy('step_order')
            ->with(['approvers', 'workflowStep'])
            ->first();
    }

    public function updateSlotStatus(
        string $slotId,
        string $status,
        string $actorId,
        ?string $notes = null,
        ?string $delegationId = null,
        ?string $delegatedBy = null
    ): void {
        ApprovalInstanceStepApprover::where('id', $slotId)->update([
            'status' => $status,
            'actor_id' => $actorId,
            'acted_at' => now(),
            'notes' => $notes,
            'delegation_id' => $delegationId,
            'delegated_by' => $delegatedBy,
        ]);
    }

    public function updateStepStatus(string $stepId, string $status): void
    {
        ApprovalInstanceStep::where('id', $stepId)->update([
            'status' => $status,
            'completed_at' => now(),
        ]);
    }

    public function activateStep(string $stepId): void
    {
        $step = ApprovalInstanceStep::find($stepId);

        $step->update([
            'status' => 'IN_PROGRESS',
            'activated_at' => now(),
            'sla_target_date' => now()->addDays($step->workflowStep->reminder_after_days),
        ]);
    }

    public function updateInstanceStatus(string $instanceId, string $status): void
    {
        ApprovalInstance::where('id', $instanceId)->update([
            'status' => $status,
            'completed_at' => now(),
        ]);
    }

    public function cancelRemainingSlots(string $stepId): void
    {
        ApprovalInstanceStepApprover::where('instance_step_id', $stepId)
            ->where('status', 'WAITING')
            ->update(['status' => 'CANCELLED']);
    }

    public function reassignSlot(
        string $slotId,
        string $newUserId,
        string $actorId,
        string $reason
    ): void {
        ApprovalInstanceStepApprover::where('id', $slotId)->update([
            'assigned_user_id' => $newUserId,
            'reassigned_to' => $newUserId,
            'actor_id' => $actorId,
            'reassignment_reason' => $reason,
        ]);
    }
}
