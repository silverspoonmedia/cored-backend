<?php

namespace App\Repositories\Contracts;

use App\Models\ApprovalInstance;
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
}
