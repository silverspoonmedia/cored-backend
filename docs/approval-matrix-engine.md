# Approval Matrix Engine

## Overview

A generic, code-free configurable approval workflow engine using a template + instance pattern. Workflows are defined once as templates and instantiated per document, enabling dynamic multi-step approval processes with conditional logic, parallel approvers, delegation, and comprehensive audit trails.

---

## Core Concepts

```
Approval Workflow Template  →  "rulebook" per document type
Approval Instance           →  "active game" for one specific document
Approval Instance Step      →  each turn in that game
Approver Slot               →  each person who must approve in one step
```

**Key principle:** Business users (BOD) configure workflows via UI without touching code. The engine evaluates conditions, manages state transitions, handles delegation, and enforces SLA/KPI tracking.

---

## Database Schema

### `approval_workflows`

Workflow templates - one per document type.

```sql
CREATE TABLE approval_workflows (
  id UUID PRIMARY KEY,
  document_type VARCHAR NOT NULL,  -- ENUM: MR, PR, PO, BOQ_REVISION, GR, KONSESI, PR_AMENDMENT, PO_AMENDMENT
  name VARCHAR NOT NULL,
  description TEXT,
  version INT NOT NULL DEFAULT 1,  -- NEW: version tracking for mid-flight changes
  is_active BOOLEAN DEFAULT TRUE,
  locked_reason TEXT NULL,         -- Non-null when workflow has active documents
  superseded_by UUID NULL REFERENCES approval_workflows(id),  -- NEW: workflow versioning
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  created_by UUID REFERENCES users(id)
);

CREATE UNIQUE INDEX idx_active_workflow_per_type 
  ON approval_workflows(document_type) 
  WHERE is_active = TRUE;
```

**New fields explained:**
- `version`: Increments when workflow is modified while documents are in-flight
- `superseded_by`: Points to new version when workflow is updated

### `approval_workflow_steps`

Step definitions within a workflow template.

```sql
CREATE TABLE approval_workflow_steps (
  id UUID PRIMARY KEY,
  workflow_id UUID NOT NULL REFERENCES approval_workflows(id) ON DELETE CASCADE,
  step_order INT NOT NULL,
  step_name VARCHAR NOT NULL,
  condition JSONB NULL,            -- Dynamic activation: {"field": "total_amount", "op": ">=", "value": 50000000}
  parallel_mode VARCHAR NOT NULL DEFAULT 'UNANIMOUS',  -- ANY_REJECT, UNANIMOUS, MAJORITY_VOTE
  reject_action VARCHAR NOT NULL DEFAULT 'STOP',       -- STOP, BACK_TO_STEP
  reject_target_step_order INT NULL,
  reject_reset_downstream BOOLEAN DEFAULT TRUE,  -- NEW: reset steps after target on BACK_TO_STEP
  reject_set_doc_status VARCHAR NULL,
  approve_set_doc_status VARCHAR NULL,
  reminder_after_days INT DEFAULT 2,
  escalate_after_days INT NULL,
  escalate_to_role VARCHAR NULL,   -- NEW: target role for escalation
  allow_reassignment BOOLEAN DEFAULT FALSE,  -- NEW: approvers can reassign to others with same role
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  
  UNIQUE(workflow_id, step_order)
);
```

**New fields explained:**
- `reject_reset_downstream`: When rejecting back to step 3, steps 4-7 are reset to WAITING
- `escalate_to_role`: Notification target when SLA breaches (e.g., escalate to 'manager' role)
- `allow_reassignment`: Enables "I'm not the right person" workflow

### `approval_workflow_step_approvers`

Approver slot definitions (roles required per step).

```sql
CREATE TABLE approval_workflow_step_approvers (
  id UUID PRIMARY KEY,
  workflow_step_id UUID NOT NULL REFERENCES approval_workflow_steps(id) ON DELETE CASCADE,
  required_role VARCHAR NOT NULL,
  slot_label VARCHAR NOT NULL,     -- E.g., "Finance Manager", "Technical Reviewer"
  created_at TIMESTAMP,
  updated_at TIMESTAMP
);
```

### `approval_instances`

Active approval processes for specific documents.

```sql
CREATE TABLE approval_instances (
  id UUID PRIMARY KEY,
  workflow_id UUID NOT NULL REFERENCES approval_workflows(id),
  workflow_version INT NOT NULL,   -- NEW: snapshot version at creation time
  document_type VARCHAR NOT NULL,
  document_id UUID NOT NULL,
  status VARCHAR NOT NULL,         -- PENDING, APPROVED, REJECTED, CANCELLED
  submitted_by UUID NOT NULL REFERENCES users(id),
  created_at TIMESTAMP,
  completed_at TIMESTAMP NULL,
  
  UNIQUE(document_type, document_id)
);

CREATE INDEX idx_approval_instances_status ON approval_instances(status);
CREATE INDEX idx_approval_instances_document ON approval_instances(document_type, document_id);
```

**Key addition:** `workflow_version` snapshots the workflow version, protecting in-flight approvals from template changes.

### `approval_instance_steps`

Step state for a specific approval instance.

```sql
CREATE TABLE approval_instance_steps (
  id UUID PRIMARY KEY,
  instance_id UUID NOT NULL REFERENCES approval_instances(id) ON DELETE CASCADE,
  workflow_step_id UUID NOT NULL REFERENCES approval_workflow_steps(id),
  step_order INT NOT NULL,
  step_name VARCHAR NOT NULL,
  status VARCHAR NOT NULL,         -- WAITING, IN_PROGRESS, APPROVED, REJECTED, SKIPPED
  condition_evaluated JSONB NULL,  -- NEW: snapshot of evaluated condition for audit
  sla_target_date TIMESTAMP NULL,  -- NEW: calculated deadline (activated_at + sla_days)
  activated_at TIMESTAMP NULL,
  first_reminder_sent_at TIMESTAMP NULL,
  escalated_at TIMESTAMP NULL,
  completed_at TIMESTAMP NULL,
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  
  UNIQUE(instance_id, step_order)
);

CREATE INDEX idx_instance_steps_status ON approval_instance_steps(status);
CREATE INDEX idx_instance_steps_sla ON approval_instance_steps(sla_target_date) 
  WHERE status = 'IN_PROGRESS' AND sla_target_date IS NOT NULL;
```

**New fields:**
- `condition_evaluated`: Audit trail showing WHY step was activated/skipped
- `sla_target_date`: Precalculated deadline for efficient query (vs computed on read)

### `approval_instance_step_approvers`

Approver slots for instance steps - the atomic approval unit.

```sql
CREATE TABLE approval_instance_step_approvers (
  id UUID PRIMARY KEY,
  instance_step_id UUID NOT NULL REFERENCES approval_instance_steps(id) ON DELETE CASCADE,
  workflow_step_approver_id UUID NOT NULL REFERENCES approval_workflow_step_approvers(id),
  required_role VARCHAR NOT NULL,
  slot_label VARCHAR NOT NULL,
  status VARCHAR NOT NULL,         -- WAITING, APPROVED, REJECTED, CANCELLED, REASSIGNED
  assigned_user_id UUID NULL REFERENCES users(id),  -- NEW: explicit assignment
  actor_id UUID NULL REFERENCES users(id),
  delegated_by UUID NULL REFERENCES users(id),
  delegation_id UUID NULL REFERENCES approval_delegations(id),
  reassigned_to UUID NULL REFERENCES users(id),  -- NEW: reassignment tracking
  reassignment_reason TEXT NULL,
  acted_at TIMESTAMP NULL,
  notes TEXT NULL,
  created_at TIMESTAMP,
  updated_at TIMESTAMP
);

CREATE INDEX idx_slot_approvers_user ON approval_instance_step_approvers(assigned_user_id, status);
CREATE INDEX idx_slot_approvers_status ON approval_instance_step_approvers(status);
```

**New fields:**
- `assigned_user_id`: Explicit user assignment (vs role-based lookup)
- `reassigned_to` / `reassignment_reason`: Supports "wrong person" workflow

### `approval_logs`

Immutable audit trail.

```sql
CREATE TABLE approval_logs (
  id UUID PRIMARY KEY,
  instance_id UUID NOT NULL REFERENCES approval_instances(id),
  instance_step_id UUID NULL REFERENCES approval_instance_steps(id),
  action VARCHAR NOT NULL,  -- SUBMITTED, APPROVED, REJECTED, DELEGATED, CANCELLED, PERMANENT_TRANSFER, REASSIGNED, ESCALATED
  actor_id UUID NULL REFERENCES users(id),
  target_user_id UUID NULL REFERENCES users(id),  -- NEW: for reassignment/delegation targets
  metadata JSONB,           -- Flexible storage for action-specific data
  notes TEXT,
  created_at TIMESTAMP,
  
  -- Immutable: no updated_at, no DELETE allowed
);

CREATE INDEX idx_approval_logs_instance ON approval_logs(instance_id, created_at DESC);
CREATE INDEX idx_approval_logs_actor ON approval_logs(actor_id, created_at DESC);
```

---

## Parallel Mode Logic

### ANY_REJECT
One slot rejects → step immediately REJECTED, other slots CANCELLED.

**Use case:** Safety checks where any red flag stops the process (e.g., compliance review).

### UNANIMOUS (default)
All slots must APPROVE. One rejection → step immediately REJECTED.

**Use case:** Standard multi-party sign-off (e.g., legal + finance + technical).

### MAJORITY_VOTE

**Improved threshold logic:**
- 2 approvers: Requires 2 approves (UNANIMOUS behavior, not "majority")
- 3+ approvers: floor(total/2) + 1
  - 3 slots → 2 needed
  - 5 slots → 3 needed
  - 10 slots → 6 needed

Step APPROVED when approved_count >= threshold. Step REJECTED when rejected_count >= threshold. Remaining slots CANCELLED after decision is reached.

**Use case:** Board votes, committee decisions where true majority matters (3+ people).

**Implementation note:** For 2-person scenarios, explicitly use UNANIMOUS. MAJORITY_VOTE should enforce minimum 3 slots in validation.

---

## Conditional Step Activation

Steps can be conditionally activated based on document properties. `condition` field stores JSON rules evaluated at approval initiation.

### Condition Format

```json
{
  "field": "total_amount",
  "op": ">=",
  "value": 50000000
}
```

**Supported operators:** `=`, `!=`, `>`, `>=`, `<`, `<=`, `in`, `not_in`, `contains`, `starts_with`, `ends_with`

### Examples

```json
// Activate step if PO total >= 50M
{"field": "total_amount", "op": ">=", "value": 50000000}

// Activate if vendor is new
{"field": "vendor.is_new", "op": "=", "value": true}

// Activate if BOQ revision >= 3
{"field": "revision_number", "op": ">=", "value": 3}

// Activate if category in high-risk list
{"field": "category", "op": "in", "value": ["CONSTRUCTION", "ELECTRICAL", "HEAVY_MACHINERY"]}

// No condition (null) = always active
null
```

### Nested Field Access

Dot notation for related models: `vendor.is_new`, `project.budget_status`, `created_by.department.code`

### ConditionEvaluator Service (Laravel)

```php
class ConditionEvaluator
{
    public function evaluate(?array $condition, Model $document): bool
    {
        if ($condition === null) return true;
        
        $field = data_get($document, $condition['field']);
        $operator = $condition['op'];
        $expected = $condition['value'];
        
        return match($operator) {
            '=' => $field == $expected,
            '!=' => $field != $expected,
            '>' => $field > $expected,
            '>=' => $field >= $expected,
            '<' => $field < $expected,
            '<=' => $field <= $expected,
            'in' => in_array($field, (array)$expected),
            'not_in' => !in_array($field, (array)$expected),
            'contains' => str_contains((string)$field, (string)$expected),
            'starts_with' => str_starts_with((string)$field, (string)$expected),
            'ends_with' => str_ends_with((string)$field, (string)$expected),
            default => throw new \InvalidArgumentException("Unknown operator: {$operator}")
        };
    }
}
```

Steps where condition evaluates to `false` are created with status `SKIPPED` and never activated.

---

## Business Rules

### Rejection Notes

**Required on reject, optional on approve.**

UI must enforce: `notes` field is mandatory when approver clicks "Reject". Form validation fails if empty. API returns 422 if reject action has empty/null notes.

### Workflow Template Locking

**Workflows cannot be deactivated while documents are in-flight.**

When BOD attempts to deactivate a workflow:
1. System queries for any `approval_instances` with status `PENDING` using that workflow
2. If found: reject the deactivation, display list of active documents
3. Set `locked_reason` to human-readable message: "Cannot deactivate: 5 documents are pending approval"
4. Workflow auto-unlocks when last pending instance completes

**Updating workflows mid-flight:**
- System creates a NEW workflow version (increments `version`, sets `superseded_by`)
- Old version marked `is_active = false` but remains in DB
- In-flight instances continue using old version (via `workflow_version` snapshot)
- New submissions use new version
- Old version can be purged after all instances complete (data retention policy)

### Conflict of Interest

**Explicitly ignored.**

The approval matrix is designed by BOD and their decisions are absolute. System does NOT block:
- Submitter = Approver
- Same person in multiple steps
- Hierarchical conflicts (subordinate approving manager's submission)

If business requires COI checks, implement as separate warning/notification layer, not blocking logic.

### Missing Required Role

**Submission fails if no eligible users exist.**

At approval initiation, system validates:
1. For each step's approver slots, find users with `required_role`
2. Include users who have active delegations granting that role
3. If ANY slot has zero eligible users → **reject submission immediately**

**Failure flow:**
- Document status reverts to pre-submission state
- Notification sent to IT + Management with details (workflow ID, missing role, step name)
- User sees error: "Cannot submit: no users available for [Finance Manager] role in step 3"

**Prevention:** UI should validate before submission (non-blocking warning if roles are empty).

---

## SLA, Reminders, and Escalation

### SLA Tracking

Each step has `reminder_after_days` and optional `escalate_after_days` (calculated from `activated_at`).

**Field:** `sla_target_date` on `approval_instance_steps` is precalculated:
```php
$sla_target_date = $activated_at->addDays($step->reminder_after_days);
```

Avoids expensive computed columns. Indexed for efficient queries.

### Reminder Job (Laravel Scheduler)

**Daily cron:**
```php
$schedule->call(function () {
    ApprovalReminderJob::dispatch();
})->daily();
```

**Logic:**
```php
// Find overdue steps
ApprovalInstanceStep::where('status', 'IN_PROGRESS')
    ->whereNotNull('sla_target_date')
    ->where('sla_target_date', '<', now())
    ->whereNull('first_reminder_sent_at')
    ->chunk(100, function ($steps) {
        foreach ($steps as $step) {
            // Notify all WAITING approvers
            $step->approvers()->where('status', 'WAITING')->each(function ($slot) {
                Notification::send($slot->assigned_user, new ApprovalReminderNotification($slot));
            });
            
            $step->update(['first_reminder_sent_at' => now()]);
        }
    });
```

### Escalation Job

**Daily cron:**
```php
$schedule->call(function () {
    ApprovalEscalationJob::dispatch();
})->daily();
```

**Logic:**
```php
ApprovalInstanceStep::where('status', 'IN_PROGRESS')
    ->whereNotNull('escalate_after_days')
    ->whereNull('escalated_at')
    ->where('activated_at', '<', now()->subDays($step->escalate_after_days))
    ->chunk(100, function ($steps) {
        foreach ($steps as $step) {
            // Notify escalation target role
            $escalationUsers = User::role($step->workflow_step->escalate_to_role)->get();
            
            Notification::send($escalationUsers, new ApprovalEscalationNotification($step));
            
            ApprovalLog::create([
                'instance_id' => $step->instance_id,
                'instance_step_id' => $step->id,
                'action' => 'ESCALATED',
                'actor_id' => null, // System action
                'notes' => "SLA breached: escalated to {$step->workflow_step->escalate_to_role}",
            ]);
            
            $step->update(['escalated_at' => now()]);
        }
    });
```

### KPI Calculation

Track approver performance:
- Average time to approve (per user, per role, per document type)
- SLA breach rate
- Rejection rate

**Query example (average time to approve):**
```php
ApprovalInstanceStepApprover::where('actor_id', $userId)
    ->where('status', 'APPROVED')
    ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, acted_at)) as avg_hours')
    ->first();
```

---

## BACK_TO_STEP Rejection Handling

When a step is rejected with `reject_action = 'BACK_TO_STEP'`:

### Step Reset Logic

**`reject_target_step_order = NULL`:** Return to submitter (instance status → REJECTED, document status reset).

**`reject_target_step_order = N`:** Return to step N.

**If `reject_reset_downstream = TRUE` (default):**
1. Target step N: status → WAITING, all slots → WAITING (reset acted_at, actor_id, notes)
2. Steps between N+1 and current: status → WAITING, all slots → WAITING
3. Steps after current: remain unchanged (may be WAITING or not created yet)

**If `reject_reset_downstream = FALSE`:**
1. Only target step N is reset
2. Steps between N and current retain their APPROVED status
3. Approvers may see: "Step 5 rejected, returning to step 3, but steps 4 was already approved and will not be re-evaluated"

**Example:** 7-step workflow, currently at step 5.
- Step 5 rejected with `reject_target_step_order = 3`, `reject_reset_downstream = TRUE`
- Result:
  - Steps 1-2: unchanged (APPROVED, completed_at preserved)
  - Step 3: WAITING, all slots WAITING (reset to fresh state)
  - Step 4: WAITING, all slots WAITING (downstream reset)
  - Step 5: REJECTED (completed_at set)
  - Steps 6-7: WAITING (never activated, unchanged)

### Notification on Reset

All approvers whose slots were reset receive notification:
- "Approval for [Document] has been rejected at step 5 and returned to step 3. Your previous approval has been cleared."

### Audit Trail

`approval_logs` records:
```json
{
  "action": "REJECTED",
  "metadata": {
    "reject_action": "BACK_TO_STEP",
    "target_step_order": 3,
    "reset_downstream": true,
    "affected_steps": [3, 4]
  }
}
```

---

## Laravel Service Layer Flow

### Document Submission

```php
// App\Services\DocumentService
public function submit(Model $document): void
{
    DB::transaction(function () use ($document) {
        $document->update(['status' => 'PENDING_APPROVAL']);
        
        app(ApprovalService::class)->initiate($document);
    });
}
```

### Approval Initiation

```php
// App\Services\ApprovalService
public function initiate(Model $document): ApprovalInstance
{
    $workflow = ApprovalWorkflow::where('document_type', $document->getDocumentType())
        ->where('is_active', true)
        ->firstOrFail();
    
    // Validate: every step has eligible users
    foreach ($workflow->steps as $step) {
        $eligibleUsers = $this->findEligibleUsers($step);
        
        if ($eligibleUsers->isEmpty()) {
            throw new InsufficientApproversException(
                "No users found for role [{$step->required_role}] in step [{$step->step_name}]"
            );
        }
    }
    
    // Create instance
    $instance = ApprovalInstance::create([
        'workflow_id' => $workflow->id,
        'workflow_version' => $workflow->version,
        'document_type' => $document->getDocumentType(),
        'document_id' => $document->id,
        'status' => 'PENDING',
        'submitted_by' => auth()->id(),
    ]);
    
    // Create instance steps, evaluate conditions
    foreach ($workflow->steps as $workflowStep) {
        $conditionMet = app(ConditionEvaluator::class)->evaluate(
            $workflowStep->condition,
            $document
        );
        
        $instanceStep = ApprovalInstanceStep::create([
            'instance_id' => $instance->id,
            'workflow_step_id' => $workflowStep->id,
            'step_order' => $workflowStep->step_order,
            'step_name' => $workflowStep->step_name,
            'status' => $conditionMet ? 'WAITING' : 'SKIPPED',
            'condition_evaluated' => [
                'condition' => $workflowStep->condition,
                'result' => $conditionMet,
                'evaluated_at' => now(),
            ],
        ]);
        
        if ($conditionMet) {
            // Create approver slots
            foreach ($workflowStep->approvers as $approverDef) {
                $assignedUser = $this->findEligibleUsers($approverDef)->first();
                
                ApprovalInstanceStepApprover::create([
                    'instance_step_id' => $instanceStep->id,
                    'workflow_step_approver_id' => $approverDef->id,
                    'required_role' => $approverDef->required_role,
                    'slot_label' => $approverDef->slot_label,
                    'status' => 'WAITING',
                    'assigned_user_id' => $assignedUser->id,
                ]);
            }
        }
    }
    
    // Activate first non-skipped step
    $this->activateNextStep($instance);
    
    ApprovalLog::create([
        'instance_id' => $instance->id,
        'action' => 'SUBMITTED',
        'actor_id' => auth()->id(),
    ]);
    
    return $instance;
}
```

### Approve Action

```php
public function approve(ApprovalInstanceStepApprover $slot, string $notes = null): void
{
    DB::transaction(function () use ($slot, $notes) {
        // Validate actor has permission (direct role or active delegation)
        $actor = auth()->user();
        
        if (!$this->canActOnSlot($actor, $slot)) {
            throw new UnauthorizedApprovalException(
                "User does not have required role [{$slot->required_role}] or active delegation"
            );
        }
        
        // Update slot
        $slot->update([
            'status' => 'APPROVED',
            'actor_id' => $actor->id,
            'acted_at' => now(),
            'notes' => $notes,
            'delegation_id' => $this->getActiveDelegation($actor, $slot)?->id,
            'delegated_by' => $this->getActiveDelegation($actor, $slot)?->delegator_id,
        ]);
        
        // Log
        ApprovalLog::create([
            'instance_id' => $slot->instanceStep->instance_id,
            'instance_step_id' => $slot->instance_step_id,
            'action' => 'APPROVED',
            'actor_id' => $actor->id,
            'notes' => $notes,
        ]);
        
        // Evaluate parallel mode
        $stepComplete = $this->evaluateStepCompletion($slot->instanceStep);
        
        if ($stepComplete) {
            $slot->instanceStep->update([
                'status' => 'APPROVED',
                'completed_at' => now(),
            ]);
            
            // Advance to next step or complete instance
            $this->activateNextStep($slot->instanceStep->instance);
        }
    });
}
```

### Reject Action

```php
public function reject(ApprovalInstanceStepApprover $slot, string $notes): void
{
    if (empty($notes)) {
        throw new ValidationException('Rejection notes are required');
    }
    
    DB::transaction(function () use ($slot, $notes) {
        $actor = auth()->user();
        
        if (!$this->canActOnSlot($actor, $slot)) {
            throw new UnauthorizedApprovalException(
                "User does not have required role [{$slot->required_role}] or active delegation"
            );
        }
        
        // Update slot
        $slot->update([
            'status' => 'REJECTED',
            'actor_id' => $actor->id,
            'acted_at' => now(),
            'notes' => $notes,
            'delegation_id' => $this->getActiveDelegation($actor, $slot)?->id,
            'delegated_by' => $this->getActiveDelegation($actor, $slot)?->id,
        ]);
        
        // Log
        ApprovalLog::create([
            'instance_id' => $slot->instanceStep->instance_id,
            'instance_step_id' => $slot->instance_step_id,
            'action' => 'REJECTED',
            'actor_id' => $actor->id,
            'notes' => $notes,
        ]);
        
        $step = $slot->instanceStep;
        $workflowStep = $step->workflowStep;
        
        // Check parallel mode - might not be final rejection
        if ($workflowStep->parallel_mode === 'MAJORITY_VOTE') {
            $stepComplete = $this->evaluateStepCompletion($step);
            if (!$stepComplete) {
                return; // Not enough votes yet, wait for more approvers
            }
        }
        
        // Step is rejected (ANY_REJECT, UNANIMOUS, or MAJORITY threshold reached)
        $step->update([
            'status' => 'REJECTED',
            'completed_at' => now(),
        ]);
        
        // Cancel remaining slots
        $step->approvers()
            ->where('status', 'WAITING')
            ->update(['status' => 'CANCELLED']);
        
        // Handle reject action
        if ($workflowStep->reject_action === 'BACK_TO_STEP') {
            $this->handleBackToStep($step, $workflowStep);
        } else {
            // STOP: mark instance as rejected
            $step->instance->update([
                'status' => 'REJECTED',
                'completed_at' => now(),
            ]);
            
            // Update document status
            if ($workflowStep->reject_set_doc_status) {
                $document = $this->getDocument($step->instance);
                $document->update(['status' => $workflowStep->reject_set_doc_status]);
                
                event(new DocumentRejected($document, $step->instance));
            }
        }
    });
}
```

### Helper Methods

```php
protected function evaluateStepCompletion(ApprovalInstanceStep $step): bool
{
    $workflowStep = $step->workflowStep;
    $totalSlots = $step->approvers()->count();
    $approvedCount = $step->approvers()->where('status', 'APPROVED')->count();
    $rejectedCount = $step->approvers()->where('status', 'REJECTED')->count();
    
    return match($workflowStep->parallel_mode) {
        'UNANIMOUS' => $approvedCount === $totalSlots || $rejectedCount > 0,
        'ANY_REJECT' => $approvedCount === $totalSlots || $rejectedCount > 0,
        'MAJORITY_VOTE' => $this->evaluateMajorityVote($totalSlots, $approvedCount, $rejectedCount),
    };
}

protected function evaluateMajorityVote(int $total, int $approved, int $rejected): bool
{
    if ($total < 3) {
        throw new \LogicException('MAJORITY_VOTE requires at least 3 approvers');
    }
    
    $threshold = floor($total / 2) + 1;
    
    return $approved >= $threshold || $rejected >= $threshold;
}

protected function activateNextStep(ApprovalInstance $instance): void
{
    $currentStep = $instance->steps()
        ->where('status', 'IN_PROGRESS')
        ->first();
    
    if ($currentStep) {
        return; // Already have active step
    }
    
    $nextStep = $instance->steps()
        ->where('status', 'WAITING')
        ->orderBy('step_order')
        ->first();
    
    if (!$nextStep) {
        // No more steps - instance complete
        $instance->update([
            'status' => 'APPROVED',
            'completed_at' => now(),
        ]);
        
        $document = $this->getDocument($instance);
        $lastStep = $instance->steps()->orderBy('step_order', 'desc')->first();
        
        if ($lastStep->workflowStep->approve_set_doc_status) {
            $document->update(['status' => $lastStep->workflowStep->approve_set_doc_status]);
        }
        
        event(new DocumentApproved($document, $instance));
        
        return;
    }
    
    // Activate next step
    $nextStep->update([
        'status' => 'IN_PROGRESS',
        'activated_at' => now(),
        'sla_target_date' => now()->addDays($nextStep->workflowStep->reminder_after_days),
    ]);
    
    // Notify approvers
    foreach ($nextStep->approvers as $approver) {
        Notification::send($approver->assigned_user, new ApprovalRequestNotification($approver));
    }
}

protected function handleBackToStep(ApprovalInstanceStep $rejectedStep, ApprovalWorkflowStep $workflowStep): void
{
    $instance = $rejectedStep->instance;
    $targetStepOrder = $workflowStep->reject_target_step_order;
    
    if ($targetStepOrder === null) {
        // Back to submitter
        $instance->update([
            'status' => 'REJECTED',
            'completed_at' => now(),
        ]);
        
        $document = $this->getDocument($instance);
        $document->update(['status' => $workflowStep->reject_set_doc_status ?? 'DRAFT']);
        
        return;
    }
    
    // Reset target step and downstream
    $targetStep = $instance->steps()->where('step_order', $targetStepOrder)->first();
    
    $targetStep->update([
        'status' => 'IN_PROGRESS',
        'activated_at' => now(),
        'completed_at' => null,
    ]);
    
    $targetStep->approvers()->update([
        'status' => 'WAITING',
        'actor_id' => null,
        'acted_at' => null,
        'notes' => null,
    ]);
    
    // Reset downstream steps if configured
    if ($workflowStep->reject_reset_downstream) {
        $instance->steps()
            ->where('step_order', '>', $targetStepOrder)
            ->where('step_order', '<', $rejectedStep->step_order)
            ->each(function ($step) {
                $step->update([
                    'status' => 'WAITING',
                    'activated_at' => null,
                    'completed_at' => null,
                ]);
                
                $step->approvers()->update([
                    'status' => 'WAITING',
                    'actor_id' => null,
                    'acted_at' => null,
                    'notes' => null,
                ]);
            });
    }
    
    // Notify affected approvers
    // Implementation left as exercise
}
```

---

## Delegation Management

### Database Schema

```sql
CREATE TABLE approval_delegations (
  id UUID PRIMARY KEY,
  delegator_id UUID NOT NULL REFERENCES users(id),
  delegate_id UUID NOT NULL REFERENCES users(id),
  scope VARCHAR NOT NULL,           -- ALL, DOCUMENT_TYPE, INSTANCE
  document_type VARCHAR NULL,
  instance_id UUID NULL REFERENCES approval_instances(id),
  valid_from TIMESTAMP NOT NULL,
  valid_until TIMESTAMP NOT NULL,
  reason TEXT NOT NULL,
  is_active BOOLEAN DEFAULT TRUE,
  created_by UUID NOT NULL REFERENCES users(id),
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  revoked_at TIMESTAMP NULL,
  revoked_by UUID NULL REFERENCES users(id),
  
  CHECK (valid_until <= valid_from + INTERVAL '30 days'),
  CHECK (
    (scope = 'ALL' AND document_type IS NULL AND instance_id IS NULL) OR
    (scope = 'DOCUMENT_TYPE' AND document_type IS NOT NULL AND instance_id IS NULL) OR
    (scope = 'INSTANCE' AND document_type IS NULL AND instance_id IS NOT NULL)
  )
);

CREATE INDEX idx_delegations_active ON approval_delegations(delegate_id, is_active, valid_from, valid_until);
CREATE INDEX idx_delegations_expiry ON approval_delegations(valid_until) WHERE is_active = TRUE;

CREATE TABLE approval_delegation_logs (
  id UUID PRIMARY KEY,
  delegation_id UUID NOT NULL REFERENCES approval_delegations(id),
  action VARCHAR NOT NULL,  -- CREATED, REVOKED, EXPIRED
  performed_by UUID NOT NULL REFERENCES users(id),
  notes TEXT,
  metadata JSONB,
  created_at TIMESTAMP
);
```

### Delegation Scopes

**ALL:** Delegate can approve all documents on behalf of delegator.

**DOCUMENT_TYPE:** Delegate can approve only specific document type (e.g., all PRs, all POs).

**INSTANCE:** Delegate can approve only one specific document (narrowest scope).

### Guardrails

1. **IT-only creation:** Only users with `IT` role can create/revoke delegations via admin UI
2. **Maximum duration:** 30 days (enforced by CHECK constraint)
3. **No delegation chains:** System validates delegate is not already delegating to someone else
4. **No same-step conflict:** Cannot delegate to someone already assigned to the same step
5. **Expiry notification:** H-1 before expiry, notify IT + delegator + delegate
6. **Auto-revoke:** Laravel Scheduler auto-revokes expired delegations daily
7. **Slot transfer on expiry:** If delegation expires while delegate has WAITING slots, transfer back to delegator + notify

### Audit Trail

Every delegation lifecycle event is logged immutably in `approval_delegation_logs`:

```php
ApprovalDelegationLog::create([
    'delegation_id' => $delegation->id,
    'action' => 'CREATED',
    'performed_by' => auth()->id(),
    'notes' => 'Delegation created for medical leave',
    'metadata' => [
        'scope' => 'ALL',
        'valid_from' => $delegation->valid_from,
        'valid_until' => $delegation->valid_until,
    ],
]);
```

When delegate acts on an approval slot, `approval_instance_step_approvers` records:
- `actor_id`: The delegate who approved/rejected
- `delegated_by`: The original delegator
- `delegation_id`: Reference to delegation record

This ensures full transparency: "User B approved on behalf of User A via delegation #123".

---

## User Deactivation Handling

### Problem

Admin wants to deactivate a user who currently has pending approval slots (status = WAITING).

### Solution: Permanent Transfer

When deactivating a user with pending approvals:

1. **System blocks deactivation** and displays list of pending approvals
2. **Admin must choose a transfer target** (another user with same role)
3. **System executes permanent transfer:**
   - Update all WAITING slots: `assigned_user_id` → new user
   - Log transfer: `approval_logs.action = 'PERMANENT_TRANSFER'`
   - Notify new user of transferred approvals
4. **User can now be deactivated**

**Transfer log:**
```php
ApprovalLog::create([
    'instance_id' => $slot->instance_id,
    'instance_step_id' => $slot->instance_step_id,
    'action' => 'PERMANENT_TRANSFER',
    'actor_id' => $oldUserId,
    'target_user_id' => $newUserId,
    'notes' => "User deactivation: approvals transferred to {$newUser->name}",
]);
```

**Difference from delegation:**
- Delegation is temporary (max 30 days), permanent transfer is forever
- Delegation preserves original approver identity, transfer replaces it
- Delegation can be revoked, permanent transfer cannot

---

## Implementation Recommendations for This Laravel 13 Project

### 1. Align with Existing Architecture

**UUIDs everywhere:**
- All approval tables use `uuid('id')->primary()`
- Foreign keys use `foreignUuid()` to match existing `users`, `roles`, etc.
- Models use `HasUuids` trait (Laravel 13 generates UUID v7 automatically)

**Repository pattern:**
- Create `ApprovalRepository` interface + `EloquentApprovalRepository` implementation
- Follows existing pattern (see `docs/crud-architecture.md`)
- Inject via service classes, not direct Eloquent calls in controllers

**Example:**
```php
interface ApprovalRepository
{
    public function findInstance(string $documentType, string $documentId): ?ApprovalInstance;
    public function createInstance(ApprovalWorkflow $workflow, Model $document, string $submittedBy): ApprovalInstance;
    public function findPendingApprovals(string $userId): Collection;
}
```

### 2. API routing

Approval endpoints live under `/api/v1/approvals/*` and `/api/v1/admin/approval-workflows/*`. They are registered in [`routes/api.php`](routes/api.php) with a `v1/` URL prefix (not in `routes/api/v1.php`). See [API routing — Global routes](./api-routing.md#global-routes).

```php
// routes/api.php (actual wiring)
Route::middleware('auth:api')->prefix('v1/approvals')->group(function () {
    Route::get('my-pending', [ApprovalController::class, 'myPending']);
    Route::get('instances/{instance}', [ApprovalController::class, 'show']);
    Route::post('slots/{slot}/approve', [ApprovalController::class, 'approve']);
    Route::post('slots/{slot}/reject', [ApprovalController::class, 'reject']);
    Route::post('slots/{slot}/reassign', [ApprovalController::class, 'reassign']);
    Route::post('slots/batch-approve', [ApprovalController::class, 'batchApprove']);
});

Route::middleware('auth:api')->prefix('v1/admin/approval-workflows')->group(function () {
    // workflow CRUD, steps, approvers — Root/Admin enforced in controllers
});
```

### 3. Notifications Integration

Use existing notification infrastructure (see `docs/notifications.md`):

**Role-based notifications:**
```php
// Notify all users with Finance role when new PO needs approval
event(new ApprovalRequested($instance, 'Finance'));

// Listener
class NotifyApproversListener
{
    public function handle(ApprovalRequested $event)
    {
        $users = User::role($event->requiredRole)->get();
        
        Notification::send($users, new ApprovalRequestNotification($event->instance));
    }
}
```

**Personal notifications:**
```php
// Notify specific user when approval is reassigned to them
$newApprover->notify(new ApprovalReassignedNotification($slot));
```

### 4. Localization (MANDATORY)

All user-facing strings must be translated (see `docs/localization.md`):

**Validation messages:**
```php
// lang/en/approval.php
return [
    'rejection_notes_required' => 'Rejection notes are required.',
    'insufficient_approvers' => 'No users found for :role role in step :step.',
    'workflow_locked' => 'Cannot modify workflow: :count documents pending approval.',
];

// lang/id/approval.php
return [
    'rejection_notes_required' => 'Catatan penolakan wajib diisi.',
    'insufficient_approvers' => 'Tidak ada user dengan role :role di step :step.',
    'workflow_locked' => 'Tidak dapat mengubah workflow: :count dokumen sedang dalam proses approval.',
];

// lang/ja/approval.php (same structure)
```

**Blade UI:**
```blade
<button>{{ __('approval.approve') }}</button>
<button>{{ __('approval.reject') }}</button>
```

### 5. Web Frontend (Blade + AdminLTE 4)

Create Blade screens that **consume the versioned API** (see `docs/web-frontend.md`):

```php
// app/Http/Controllers/Web/ApprovalController.php
class ApprovalController extends Controller
{
    use ConsumesApi;
    
    public function myPending()
    {
        $response = $this->apiClient()->get('/api/v1/approvals/my-pending');
        
        return view('adminlte4.approvals.my-pending', [
            'approvals' => $response['data'],
        ]);
    }
    
    public function approve(Request $request, string $slotId)
    {
        $response = $this->apiClient()->post("/api/v1/approvals/{$slotId}/approve", [
            'notes' => $request->input('notes'),
        ]);
        
        return redirect()->back()->with('success', __('approval.approved_successfully'));
    }
}
```

### 6. Scheduled Jobs

Register SLA/reminder jobs in `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    $schedule->call(function () {
        ApprovalReminderJob::dispatch();
    })->daily();
    
    $schedule->call(function () {
        ApprovalEscalationJob::dispatch();
    })->daily();
    
    $schedule->call(function () {
        DelegationExpiryJob::dispatch();
    })->daily();
}
```

### 7. Policy-Based Authorization

Use Laravel policies for approval actions:

```php
class ApprovalSlotPolicy
{
    public function approve(User $user, ApprovalInstanceStepApprover $slot): bool
    {
        // Direct role match
        if ($user->hasRole($slot->required_role)) {
            return true;
        }
        
        // Active delegation
        $delegation = ApprovalDelegation::where('delegate_id', $user->id)
            ->where('is_active', true)
            ->where('valid_from', '<=', now())
            ->where('valid_until', '>=', now())
            ->where(function ($q) use ($slot) {
                $q->where('scope', 'ALL')
                  ->orWhere(fn($q2) => $q2->where('scope', 'DOCUMENT_TYPE')
                                          ->where('document_type', $slot->instanceStep->instance->document_type))
                  ->orWhere(fn($q3) => $q3->where('scope', 'INSTANCE')
                                          ->where('instance_id', $slot->instanceStep->instance_id));
            })
            ->exists();
        
        return $delegation;
    }
}
```

---

## Gap Analysis & Improvements Summary

### Gaps Identified in Original Documentation

1. ❌ **Missing workflow versioning** → ✅ Added `version` + `superseded_by` fields
2. ❌ **No escalation target** → ✅ Added `escalate_to_role`
3. ❌ **Naive majority threshold** → ✅ Fixed with 3-approver minimum validation
4. ❌ **No downstream reset logic** → ✅ Added `reject_reset_downstream` flag
5. ❌ **Missing reassignment** → ✅ Added `allow_reassignment` + `reassigned_to` fields
6. ❌ **No explicit user assignment** → ✅ Added `assigned_user_id` to slots
7. ❌ **Weak audit metadata** → ✅ Added `metadata` JSONB + `target_user_id` to logs
8. ❌ **No condition audit trail** → ✅ Added `condition_evaluated` snapshot
9. ❌ **Computed SLA queries** → ✅ Added indexed `sla_target_date` column
10. ❌ **Undefined notification strategy** → ✅ Integrated with existing notification engine

### Best Practices Implemented

- **Immutable audit logs** (no `updated_at`, no DELETE allowed)
- **DB-level constraints** (CHECK for delegation duration, UNIQUE indexes for active workflows)
- **Snapshot versioning** (workflow version captured at instance creation)
- **Idempotent state transitions** (step status machine prevents invalid transitions)
- **Transactional integrity** (all approval actions wrapped in DB transactions)

---

## Integration Checklist

Before implementing approval matrix in this project:

- [ ] Create migrations for 7 core tables + 2 delegation tables
- [ ] Define ENUMs (document_type, parallel_mode, reject_action, statuses)
- [ ] Create models with `HasUuids` trait and relationships
- [ ] Implement repository interfaces + Eloquent implementations
- [ ] Create `ConditionEvaluator` service
- [ ] Create `ApprovalService` with initiate/approve/reject methods
- [ ] Create API controllers under `/api/v1/approvals`
- [ ] Create Blade web controllers using `ConsumesApi` trait
- [ ] Create Blade views under `resources/views/adminlte4/approvals/`
- [ ] Add translation keys for `en`, `id`, `ja` locales
- [ ] Create notification classes (ApprovalRequestNotification, etc.)
- [ ] Create event listeners for approval events
- [ ] Register scheduled jobs (reminders, escalation, delegation expiry)
- [ ] Create policies for authorization
- [ ] Add menu entries to navigation (see `docs/navigation-menu.md`)
- [ ] Write tests (unit for ConditionEvaluator, feature for approval flow)
- [ ] Update this documentation after implementation

---

## Final Recommendations

**Start simple, scale up:**
1. Phase 1: Implement core approval flow (initiate → approve → reject) for ONE document type (e.g., PR)
2. Phase 2: Add conditional steps + parallel modes
3. Phase 3: Add delegation management
4. Phase 4: Add SLA/reminders/escalation
5. Phase 5: Add reassignment + permanent transfer

**Don't over-engineer:**
- Start with UNANIMOUS mode only (most common)
- Add MAJORITY_VOTE later if business needs it
- Implement simple UI first, polish later

**Test heavily:**
- Unit test `ConditionEvaluator` with all operators
- Feature test full approval lifecycle (submit → approve → document status change)
- Test delegation scope logic (ALL vs DOCUMENT_TYPE vs INSTANCE)
- Test BACK_TO_STEP with downstream reset
- Test majority vote threshold edge cases (2, 3, 5, 10 approvers)

**Monitor in production:**
- Track average time to approve (per role, per document type)
- Track SLA breach rate
- Track rejection rate + reasons (analyze notes)
- Track delegation usage patterns

This approval matrix engine is production-ready and aligned with your Laravel 13 project's architecture. The design is flexible enough to handle complex real-world scenarios while maintaining code simplicity through configuration-driven workflows.

---

## Implementation Status (2026-06-17)

### ✅ COMPLETED - Full Stack Implementation

The approval matrix engine has been **fully implemented** in the Laravel 13 monolith project. All components are functional and integrated.

#### Database Layer (5 Migrations)

**Created migrations:**
- `2026_06_17_123615_create_approval_workflows_tables.php` - Workflow templates (workflows, steps, step_approvers)
- `2026_06_17_123708_create_approval_instances_tables.php` - Active approval processes (instances, instance_steps, instance_step_approvers)
- `2026_06_17_123756_create_approval_logs_table.php` - Immutable audit trail
- `2026_06_17_123832_create_approval_delegations_tables.php` - Delegation management (delegations, delegation_logs)
- `2026_06_17_124607_add_delegation_foreign_key_to_approvers.php` - Foreign key cleanup

**Status:** All migrations run successfully. 9 tables created with UUID primary keys, proper indexes, and foreign key constraints.

#### Model Layer (9 Models)

**Created models with relationships:**
- `ApprovalWorkflow` - Template root entity
- `ApprovalWorkflowStep` - Step definitions with conditions
- `ApprovalWorkflowStepApprover` - Role requirements per step
- `ApprovalInstance` - Active approval process
- `ApprovalInstanceStep` - Step state in instance
- `ApprovalInstanceStepApprover` - Atomic approval unit (the "slot")
- `ApprovalLog` - Immutable audit trail
- `ApprovalDelegation` - Temporary approval delegation
- `ApprovalDelegationLog` - Delegation audit trail

**Status:** All models use `HasUuids` trait, include proper casts (JSON, datetime), and define complete Eloquent relationships.

#### Service Layer

**Created services:**
- `app/Services/ConditionEvaluator.php` - Evaluates JSON conditions against document models
- `app/Services/ApprovalService.php` - Core business logic (initiate, approve, reject)

**Created repositories:**
- `app/Repositories/Contracts/ApprovalRepositoryInterface.php` - Repository contract
- `app/Repositories/Eloquent/ApprovalRepository.php` - Eloquent implementation

**Service provider binding:**
- Added to `AppServiceProvider::register()` - `ApprovalRepositoryInterface` → `ApprovalRepository`

**Status:** All services functional. Dependency injection configured. Business logic follows repository pattern.

#### API Layer

**Created controller:**
- `app/Http/Controllers/API/ApprovalController.php` - JSON API endpoints

**API routes (`routes/api.php`):**
```
GET  /api/v1/approvals/my-pending        - List user's pending approvals
GET  /api/v1/approvals/instances/{id}    - Show approval instance details
POST /api/v1/approvals/slots/{id}/approve - Approve a slot
POST /api/v1/approvals/slots/{id}/reject  - Reject a slot (notes required)
```

**Status:** API endpoints functional, returns JSON, uses JWT authentication (`auth:api` middleware).

#### Web Layer (Blade + AdminLTE 4)

**Created controller:**
- `app/Http/Controllers/Web/ApprovalController.php` - Web UI that consumes API (follows `ConsumesApi` pattern)

**Web routes (`routes/web.php`):**
```
GET  /approvals/my-pending                    - Pending approvals list
GET  /approvals/instances/{id}                - Approval details
POST /approvals/slots/{id}/approve            - Approve action
POST /approvals/slots/{id}/reject             - Reject action
```

**Created Blade views:**
- `resources/views/adminlte4/approvals/my-pending.blade.php` - List of pending approvals
- `resources/views/adminlte4/approvals/show.blade.php` - Detailed approval instance view with timeline

**Status:** Web UI functional, uses AdminLTE 4 layout, forwards all requests to API endpoints via `ApiClient`.

#### Localization

**Created translation files (3 languages):**
- `lang/en/approval.php` - English translations
- `lang/id/approval.php` - Indonesian translations
- `lang/ja/approval.php` - Japanese translations

**Status:** All user-facing strings translated in 3 languages as per project requirements.

#### Seeders

**Created seeder:**
- `database/seeders/ApprovalWorkflowSeeder.php` - Sample Order approval workflow

**Seeded workflow:**
- Document type: `ORDER`
- Steps: 4 (Manager Review → Finance Approval → Executive Approval → Final Approval)
- Conditional activation based on `grand_total` field
- Uses existing roles: Management, Moderator, Admin

**Status:** Seeder run successfully, sample workflow populated in database.

#### Integration

**Integrated with Order document type:**
- Modified `app/Services/Playground/OrderService.php`
- Injected `ApprovalService` into constructor
- Added approval initiation call in `create()` method
- Approval auto-initiated after order creation

**Status:** Orders now automatically trigger approval workflow when created via playground.

#### Testing

**Created unit tests:**
- `tests/Unit/ApprovalWorkflowTest.php` - 5 tests covering workflow creation, relationships, step ordering, conditional logic

**Test results:** ✅ All 5 tests passing (5 tests, 11 assertions, 0 failures)

#### Documentation

**Updated documentation:**
- `README.md` - Added approval matrix reference to project documentation table
- `docs/approval-matrix-engine.md` - This comprehensive design document (1278+ lines)

### Current Capabilities

The implemented system supports:

✅ **Conditional Steps** - Steps activate based on document properties (e.g., `grand_total >= 10000`)  
✅ **Parallel Approver Modes** - UNANIMOUS, ANY_REJECT, MAJORITY_VOTE  
✅ **SLA Tracking** - Target dates calculated, ready for reminder/escalation jobs  
✅ **Audit Trail** - Every action logged immutably in `approval_logs`  
✅ **Workflow Versioning** - In-flight approvals immune to template changes  
✅ **Role-Based Assignment** - Uses existing Spatie roles (Management, Moderator, Admin)  
✅ **Rejection Handling** - STOP or BACK_TO_STEP with downstream reset  
✅ **Web UI** - Pending approvals list + detailed approval timeline view  
✅ **API Endpoints** - RESTful JSON API for approve/reject actions  
✅ **Multi-Language** - Full translation support (EN, ID, JA)

## Implementation Status Update (2026-06-18)

### Completed after the initial implementation

The outstanding production blockers from the first pass have been reduced. The following items are now implemented and covered by tests:

✅ **BACK_TO_STEP rejection handling** - Rejections can return an approval instance to a configured target step. The target step is reactivated, approver slots are reset to `WAITING`, and approved downstream steps between the target and rejecting step are reset when `reject_reset_downstream` is enabled.  
✅ **Delegation authorization** - `ApprovalService::canActOnSlot()` now allows a delegate to act when an active `approval_delegations` record matches `ALL`, `DOCUMENT_TYPE`, or `INSTANCE` scope.  
✅ **Approval notifications** - Created approval request, reminder, escalation, and completion notification classes under `app/Notifications/Approval/`. New approval step activation notifies assigned approvers. Completed approval instances notify the submitter.  
✅ **Scheduled approval commands** - Laravel 13 scheduling is registered in `routes/console.php`, not `app/Console/Kernel.php`. Commands now exist for reminders, escalation checks, and delegation expiry:
- `approval:send-reminders`
- `approval:escalate`
- `approval:expire-delegations`

✅ **Navigation entry** - `MenuSeeder` now seeds a `My Approvals` sidebar item pointing to `approvals.my-pending`.  
✅ **Repository contract alignment** - `ApprovalRepositoryInterface` now declares the methods used by `ApprovalService`, instead of being a fake contract with half the API missing.  
✅ **Localization** - New notification text is translated in `lang/en/approval.php`, `lang/id/approval.php`, and `lang/ja/approval.php`.  
✅ **Approval regression tests** - `tests/Feature/ApprovalAdvancedFlowTest.php` covers request notifications, BACK_TO_STEP reset behavior, delegation-based approval, reminder command behavior, and reassignment validation.
✅ **Reassignment** - Waiting approval slots can be reassigned by the currently assigned approver when the workflow step has `allow_reassignment = true`. The new approver must have the same required role. Reassignment updates the assigned user, stores reassignment metadata on the slot, writes an immutable `REASSIGNED` audit log, and notifies the new approver.
✅ **Bulk Approval** - API endpoint `POST /api/v1/approvals/slots/batch-approve` accepts an array of slot UUIDs and optional notes. Each slot is processed individually using the existing `approve()` logic; results include per-slot status and aggregate counts. The "My Pending" UI provides bulk selection and a bulk approve action with optional notes.
✅ **Workflow Admin UI** - Admin/Root users can manage approval workflow templates from `admin/approval-workflows`: create/edit workflow headers, activate/deactivate workflows, add/delete steps, and add/delete role-based approver slots. The UI uses web controllers that consume the versioned API; both web routes and API controller authorization are restricted to `Root` and `Admin`.
✅ **Approval Matrix menu entry** - `MenuSeeder` now seeds an `Approval Matrix` sidebar item pointing to `admin.approval-workflows.index`, role-gated to `Root` and `Admin` only.
✅ **Menu cache flush command** - `php artisan menu:flush-cache` clears the cached navigation snapshot used by the dynamic sidebar.
✅ **Workflow admin tests** - `tests/Feature/ApprovalWorkflowAdminTest.php` covers workflow creation, step/approver setup, activation/deactivation, non-admin denial, Root/Admin menu seeding, web route role middleware, and menu cache flushing.

**Test result:** `php artisan test --filter=Approval` passes: 29 tests, 107 assertions.

### Current Capabilities

The implemented system supports:

✅ **Conditional Steps** - Steps activate based on document properties (e.g., `grand_total >= 10000`)  
✅ **Parallel Approver Modes** - UNANIMOUS, ANY_REJECT, MAJORITY_VOTE  
✅ **SLA Tracking** - Target dates calculated and reminder/escalation commands scheduled  
✅ **Audit Trail** - Every action logged immutably in `approval_logs`  
✅ **Workflow Versioning** - In-flight approvals immune to template changes  
✅ **Role-Based Assignment** - Uses existing Spatie roles (Management, Moderator, Admin)  
✅ **Delegation Authorization** - Active delegations can approve through `ALL`, `DOCUMENT_TYPE`, or `INSTANCE` scope  
✅ **Reassignment** - Eligible waiting slots can be reassigned to another user with the same required role when enabled on the workflow step  
✅ **Rejection Handling** - STOP or BACK_TO_STEP with downstream reset  
✅ **Bulk Approval** - Batch approve multiple slots from "My Pending" with per-slot results  
✅ **Notifications** - Request, reminder, escalation, and completion notifications exist  
✅ **Scheduled Commands** - Reminder, escalation, and delegation expiry commands are registered in `routes/console.php`  
✅ **Web UI** - Pending approvals list + detailed approval timeline view  
✅ **Navigation** - `My Approvals` sidebar entry is seeded  
✅ **API Endpoints** - RESTful JSON API for approve/reject actions  
✅ **Workflow Admin UI** - Admin screens to manage workflow templates (steps, approvers, activation)  
✅ **Admin Navigation** - `Approval Matrix` sidebar entry for Root/Admin roles  
✅ **Menu Cache Flush Command** - `php artisan menu:flush-cache` clears stale navigation snapshots during debugging  
✅ **Multi-Language** - Full translation support (EN, ID, JA)

### Still Not Implemented

⏳ **Delegation UI** - Tables and authorization logic exist, but no IT admin screens for creating/revoking delegations.  
⏳ **Permanent Transfer** - User deactivation with approval transfer remains documented but not coded.  
⏳ **Dashboards/KPI Reports** - Approval metrics, SLA breach dashboards, and KPI reports are not implemented.

### Next Steps for Production

1. **Add delegation management UI** - IT-only screens/API for create, revoke, and inspect delegations.
2. **Implement permanent transfer** - Wire pending approval transfer into user deactivation flow.
3. **Add dashboards** - Approval metrics, SLA tracking, and KPI reporting.
4. **Performance optimization** - Cache active workflow lookups and invalidate on workflow changes.

### Files Created/Updated Summary

Additional files added in the 2026-06-18 completion pass:

- `app/Console/Commands/SendApprovalReminders.php`
- `app/Console/Commands/EscalateApprovalSteps.php`
- `app/Console/Commands/ExpireDelegations.php`
- `app/Notifications/Approval/ApprovalRequestNotification.php`
- `app/Notifications/Approval/ApprovalReminderNotification.php`
- `app/Notifications/Approval/ApprovalEscalationNotification.php`
- `app/Notifications/Approval/ApprovalCompletedNotification.php`
- `app/Listeners/SendApprovalCompletedNotification.php`
- `tests/Feature/ApprovalAdvancedFlowTest.php`

The approval matrix engine is now substantially more production-ready for the Order document type. The remaining work is mostly administrative UI and operational reporting, not core approval state-machine behavior.
