<?php

namespace Database\Seeders;

use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStep;
use App\Models\ApprovalWorkflowStepApprover;
use App\Models\User;
use Illuminate\Database\Seeder;

class ApprovalWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        // Create workflow for Order document type
        $workflow = ApprovalWorkflow::create([
            'document_type' => 'ORDER',
            'name' => 'Standard Order Approval',
            'description' => 'Multi-step approval workflow for orders based on amount thresholds',
            'version' => 1,
            'is_active' => true,
            'created_by' => User::first()?->id,
        ]);

        // Step 1: Manager Review (always active, any order)
        $step1 = ApprovalWorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_order' => 1,
            'step_name' => 'Manager Review',
            'condition' => null, // Always active
            'parallel_mode' => 'UNANIMOUS',
            'reject_action' => 'STOP',
            'reject_set_doc_status' => 'REJECTED',
            'approve_set_doc_status' => null, // Don't change status until final step
            'reminder_after_days' => 2,
            'escalate_after_days' => 5,
            'escalate_to_role' => 'Admin',
            'allow_reassignment' => false,
        ]);

        ApprovalWorkflowStepApprover::create([
            'workflow_step_id' => $step1->id,
            'required_role' => 'Management',
            'slot_label' => 'Department Manager',
        ]);

        // Step 2: Finance Approval (conditional - orders >= 10000)
        $step2 = ApprovalWorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_order' => 2,
            'step_name' => 'Finance Approval',
            'condition' => ['field' => 'grand_total', 'op' => '>=', 'value' => 10000],
            'parallel_mode' => 'UNANIMOUS',
            'reject_action' => 'BACK_TO_STEP',
            'reject_target_step_order' => 1,
            'reject_reset_downstream' => true,
            'reject_set_doc_status' => null,
            'approve_set_doc_status' => null,
            'reminder_after_days' => 2,
            'escalate_after_days' => 3,
            'escalate_to_role' => 'Admin',
            'allow_reassignment' => true,
        ]);

        ApprovalWorkflowStepApprover::create([
            'workflow_step_id' => $step2->id,
            'required_role' => 'Moderator',
            'slot_label' => 'Finance Controller',
        ]);

        // Step 3: Executive Approval (conditional - orders >= 50000)
        $step3 = ApprovalWorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_order' => 3,
            'step_name' => 'Executive Approval',
            'condition' => ['field' => 'grand_total', 'op' => '>=', 'value' => 50000],
            'parallel_mode' => 'UNANIMOUS',
            'reject_action' => 'BACK_TO_STEP',
            'reject_target_step_order' => 1,
            'reject_reset_downstream' => true,
            'reject_set_doc_status' => null,
            'approve_set_doc_status' => null,
            'reminder_after_days' => 1,
            'escalate_after_days' => 2,
            'escalate_to_role' => 'Root',
            'allow_reassignment' => false,
        ]);

        ApprovalWorkflowStepApprover::create([
            'workflow_step_id' => $step3->id,
            'required_role' => 'Admin',
            'slot_label' => 'Executive Director',
        ]);

        // Step 4: Final Approval (always active, marks order as approved)
        $step4 = ApprovalWorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_order' => 4,
            'step_name' => 'Final Approval',
            'condition' => null,
            'parallel_mode' => 'UNANIMOUS',
            'reject_action' => 'BACK_TO_STEP',
            'reject_target_step_order' => 1,
            'reject_reset_downstream' => true,
            'reject_set_doc_status' => 'REJECTED',
            'approve_set_doc_status' => 'APPROVED',
            'reminder_after_days' => 1,
            'escalate_after_days' => 3,
            'escalate_to_role' => 'Root',
            'allow_reassignment' => false,
        ]);

        ApprovalWorkflowStepApprover::create([
            'workflow_step_id' => $step4->id,
            'required_role' => 'Admin',
            'slot_label' => 'Final Approver',
        ]);

        $this->command->info("Created approval workflow '{$workflow->name}' with {$workflow->steps->count()} steps");
    }
}
