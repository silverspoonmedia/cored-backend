<?php

namespace App\Repositories\Contracts;

use App\Models\ApprovalInstance;
use App\Models\ApprovalInstanceStep;
use App\Models\ApprovalInstanceStepApprover;
use App\Models\ApprovalWorkflow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

interface ApprovalRepositoryInterface
{
    public function findActiveWorkflow(string $documentType): ?ApprovalWorkflow;

    public function findInstance(string $documentType, string $documentId): ?ApprovalInstance;

    public function createInstance(ApprovalWorkflow $workflow, Model $document, string $submittedBy): ApprovalInstance;

    public function findPendingApprovals(string $userId): Collection;

    public function findSlot(string $slotId): ?ApprovalInstanceStepApprover;

    public function getUsersWithRole(string $role): Collection;

    public function createInstanceStep(
        string $instanceId,
        string $workflowStepId,
        int $stepOrder,
        string $stepName,
        string $status,
        ?array $conditionEvaluated = null
    ): ApprovalInstanceStep;

    public function createApproverSlot(
        string $instanceStepId,
        string $workflowStepApproverId,
        string $requiredRole,
        string $slotLabel,
        string $assignedUserId
    ): ApprovalInstanceStepApprover;

    public function getActiveStepForInstance(string $instanceId): ?ApprovalInstanceStep;

    public function getNextWaitingStep(string $instanceId): ?ApprovalInstanceStep;

    public function updateSlotStatus(
        string $slotId,
        string $status,
        string $actorId,
        ?string $notes = null,
        ?string $delegationId = null,
        ?string $delegatedBy = null
    ): void;

    public function updateStepStatus(string $stepId, string $status): void;

    public function activateStep(string $stepId): void;

    public function updateInstanceStatus(string $instanceId, string $status): void;

    public function cancelRemainingSlots(string $stepId): void;

    public function reassignSlot(
        string $slotId,
        string $newUserId,
        string $actorId,
        string $reason
    ): void;
}
