<?php

namespace Tests\Unit;

use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStep;
use App\Models\ApprovalWorkflowStepApprover;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_can_create_approval_workflow(): void
    {
        $user = User::factory()->create();

        $workflow = ApprovalWorkflow::create([
            'document_type' => 'ORDER',
            'name' => 'Test Workflow',
            'description' => 'Test Description',
            'version' => 1,
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $this->assertDatabaseHas('approval_workflows', [
            'id' => $workflow->id,
            'document_type' => 'ORDER',
            'name' => 'Test Workflow',
        ]);
    }

    public function test_workflow_has_steps_relationship(): void
    {
        $user = User::factory()->create();
        $workflow = ApprovalWorkflow::create([
            'document_type' => 'ORDER',
            'name' => 'Test Workflow',
            'version' => 1,
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $step = ApprovalWorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_order' => 1,
            'step_name' => 'Test Step',
            'parallel_mode' => 'UNANIMOUS',
            'reject_action' => 'STOP',
        ]);

        $this->assertEquals(1, $workflow->steps()->count());
        $this->assertEquals('Test Step', $workflow->steps->first()->step_name);
    }

    public function test_step_has_approvers_relationship(): void
    {
        $user = User::factory()->create();
        $workflow = ApprovalWorkflow::create([
            'document_type' => 'ORDER',
            'name' => 'Test Workflow',
            'version' => 1,
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $step = ApprovalWorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_order' => 1,
            'step_name' => 'Test Step',
            'parallel_mode' => 'UNANIMOUS',
            'reject_action' => 'STOP',
        ]);

        $approver = ApprovalWorkflowStepApprover::create([
            'workflow_step_id' => $step->id,
            'required_role' => 'Admin',
            'slot_label' => 'Test Approver',
        ]);

        $this->assertEquals(1, $step->approvers()->count());
        $this->assertEquals('Admin', $step->approvers->first()->required_role);
    }

    public function test_workflow_step_ordering(): void
    {
        $user = User::factory()->create();
        $workflow = ApprovalWorkflow::create([
            'document_type' => 'ORDER',
            'name' => 'Test Workflow',
            'version' => 1,
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        ApprovalWorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_order' => 3,
            'step_name' => 'Third Step',
            'parallel_mode' => 'UNANIMOUS',
            'reject_action' => 'STOP',
        ]);

        ApprovalWorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_order' => 1,
            'step_name' => 'First Step',
            'parallel_mode' => 'UNANIMOUS',
            'reject_action' => 'STOP',
        ]);

        ApprovalWorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_order' => 2,
            'step_name' => 'Second Step',
            'parallel_mode' => 'UNANIMOUS',
            'reject_action' => 'STOP',
        ]);

        $steps = $workflow->steps()->orderBy('step_order')->get();

        $this->assertEquals('First Step', $steps[0]->step_name);
        $this->assertEquals('Second Step', $steps[1]->step_name);
        $this->assertEquals('Third Step', $steps[2]->step_name);
    }

    public function test_conditional_step_json_storage(): void
    {
        $user = User::factory()->create();
        $workflow = ApprovalWorkflow::create([
            'document_type' => 'ORDER',
            'name' => 'Test Workflow',
            'version' => 1,
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $condition = ['field' => 'grand_total', 'op' => '>=', 'value' => 10000];

        $step = ApprovalWorkflowStep::create([
            'workflow_id' => $workflow->id,
            'step_order' => 1,
            'step_name' => 'Conditional Step',
            'condition' => $condition,
            'parallel_mode' => 'UNANIMOUS',
            'reject_action' => 'STOP',
        ]);

        $this->assertEquals($condition, $step->condition);
        $this->assertEquals('grand_total', $step->condition['field']);
        $this->assertEquals(10000, $step->condition['value']);
    }
}
