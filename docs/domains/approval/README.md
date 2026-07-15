# Approval — matrix engine

Template + instance workflow engine for multi-step approvals (conditions, parallel modes, delegation, audit). Code: `app/Domain/Approval/`.

**Living doc** — design dumps / dual status narratives archived if needed; this file matches **current** product surface.

## Core concepts

| Concept | Role |
|---------|------|
| **Workflow template** | Rulebook per `document_type` |
| **Instance** | One run for one document |
| **Instance step** | Step state in that run |
| **Approver slot** | Person/role seat that must act |
| **Delegation** | Temporary reassignment |
| **Approval log** | Immutable workflow journal (`approval_logs`) |

Business users configure templates; engine evaluates conditions, transitions, SLA hooks.

## Schema (logical)

| Table group | Purpose |
|-------------|---------|
| `approval_workflows` + steps + step_approvers | Templates, conditions JSON, parallel_mode, reject_action |
| `approval_instances` + instance_steps + step_approvers | Runtime state, slots, SLA dates |
| `approval_logs` | Audit |
| `approval_delegations` (+ logs) | Delegation |

All domain PKs UUID. Models under `App\Domain\Approval\Models\*`.

## Parallel modes

| Mode | Meaning |
|------|---------|
| `UNANIMOUS` | All slots approve |
| `ANY_REJECT` | Single reject can fail step (per config) |
| `MAJORITY_VOTE` | Majority of slots |

## Conditions

Steps may carry JSON conditions (e.g. `grand_total >= N`) evaluated by `ConditionEvaluator` against the document model. Inactive steps skip until condition met.

## Rejection

| Action | Effect |
|--------|--------|
| `STOP` | Fail workflow |
| `BACK_TO_STEP` | Return to target step; optional reset of downstream steps |

## Service layer

| Class | Role |
|-------|------|
| `ApprovalService` | initiate, approve, reject, progression |
| `ConditionEvaluator` | Step activation |
| `ApprovalRepository` | Persistence |

Bound in `ApprovalServiceProvider`.

## API (`routes/api/v1/approval.php`)

Typical endpoints:

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/api/v1/approvals/my-pending` | Actor’s pending slots |
| GET | `/api/v1/approvals/instances/{id}` | Instance detail |
| POST | `/api/v1/approvals/slots/{id}/approve` | Approve |
| POST | `/api/v1/approvals/slots/{id}/reject` | Reject (notes required) |
| Admin | `/api/v1/admin/approval-workflows…` | Template admin (role-gated) |

JWT `auth:api`. Web controllers proxy via `ApiClient` — views under `resources/views/adminlte4/approvals/`.

## Integration

Playground orders can auto-initiate workflow on create (`OrderService` + `ApprovalService`). Document type example: `ORDER` seeded by `ApprovalWorkflowSeeder`.

## Capabilities (current)

- Conditional steps, parallel modes, SLA fields, immutable logs  
- Workflow versioning so in-flight instances stay on snapshot rules  
- Role-based slots (Spatie roles)  
- EN/ID/JA copy  
- Delegation tables/services where implemented  

Verify routes: `php artisan route:list --path=api/v1/approvals`.

## Tests

`tests/Domain/Approval/` — prefer `composer test -- --filter=Approval`.

## Related

- [playground](../playground/README.md)
- [activity-log](../activity-log/README.md) — general audit (separate from `approval_logs`)
- [platform/crud.md](../../platform/crud.md)
