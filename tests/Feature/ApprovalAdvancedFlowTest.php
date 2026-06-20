<?php

namespace Tests\Feature;

use App\Models\ApprovalDelegation;
use App\Models\ApprovalInstanceStep;
use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStep;
use App\Models\ApprovalWorkflowStepApprover;
use App\Models\Order;
use App\Models\User;
use App\Notifications\Approval\ApprovalReminderNotification;
use App\Notifications\Approval\ApprovalRequestNotification;
use App\Services\ApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ApprovalAdvancedFlowTest extends TestCase
{
    use RefreshDatabase;

    private ApprovalService $approvalService;
    private User $submitter;
    private User $manager;
    private User $moderator;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->approvalService = app(ApprovalService::class);

        $this->submitter = User::factory()->create(['email' => 'submitter@example.test']);
        $this->submitter->assignRole('User');

        $this->manager = User::factory()->create(['email' => 'manager@example.test']);
        $this->manager->assignRole('Management');

        $this->moderator = User::factory()->create(['email' => 'moderator@example.test']);
        $this->moderator->assignRole('Moderator');

        $this->admin = User::factory()->create(['email' => 'admin@example.test']);
        $this->admin->assignRole('Admin');
    }

    public function test_activation_sends_approval_request_notification(): void
    {
        Notification::fake();
        $this->seed(\Database\Seeders\ApprovalWorkflowSeeder::class);

        $this->actingAs($this->submitter, 'api');
        $this->approvalService->initiate($this->createOrder(5_000), 'ORDER');

        Notification::assertSentTo($this->manager, ApprovalRequestNotification::class);
    }

    public function test_back_to_step_rejection_reactivates_target_and_resets_downstream(): void
    {
        Notification::fake();
        $this->seed(\Database\Seeders\ApprovalWorkflowSeeder::class);

        $this->actingAs($this->submitter, 'api');
        $instance = $this->approvalService->initiate($this->createOrder(60_000), 'ORDER');

        $this->actingAs($this->manager, 'api');
        $this->approvalService->approve($instance->steps->where('step_order', 1)->first()->approvers->first());

        $step2 = $instance->steps()->where('step_order', 2)->with('approvers')->first();
        $this->actingAs($this->moderator, 'api');
        $this->approvalService->approve($step2->approvers->first());

        $step3 = $instance->steps()->where('step_order', 3)->with('approvers')->first();
        $this->actingAs($this->admin, 'api');
        $this->approvalService->reject($step3->approvers->first(), 'Needs rework');

        $this->assertSame('IN_PROGRESS', $instance->steps()->where('step_order', 1)->first()->status);
        $this->assertSame('WAITING', $instance->steps()->where('step_order', 2)->first()->status);
        $this->assertSame('REJECTED', $instance->steps()->where('step_order', 3)->first()->status);

        $this->assertDatabaseHas('approval_logs', [
            'instance_id' => $instance->id,
            'instance_step_id' => $instance->steps()->where('step_order', 1)->first()->id,
            'action' => 'REJECTED',
        ]);
    }

    public function test_active_delegation_allows_delegate_to_approve_slot(): void
    {
        Notification::fake();
        $this->seed(\Database\Seeders\ApprovalWorkflowSeeder::class);

        $delegate = User::factory()->create(['email' => 'delegate@example.test']);
        $delegate->assignRole('User');

        $this->actingAs($this->admin, 'api');
        ApprovalDelegation::create([
            'delegator_id' => $this->manager->id,
            'delegate_id' => $delegate->id,
            'scope' => 'ALL',
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addDay(),
            'reason' => 'Manager out of office',
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->submitter, 'api');
        $instance = $this->approvalService->initiate($this->createOrder(5_000), 'ORDER');
        $slot = $instance->steps->where('step_order', 1)->first()->approvers->first();

        $this->actingAs($delegate, 'api');
        $this->approvalService->approve($slot, 'Approved by delegate');

        $this->assertDatabaseHas('approval_instance_step_approvers', [
            'id' => $slot->id,
            'status' => 'APPROVED',
            'actor_id' => $delegate->id,
        ]);
    }

    public function test_reminder_command_marks_overdue_step_and_sends_notification(): void
    {
        Notification::fake();
        $this->seed(\Database\Seeders\ApprovalWorkflowSeeder::class);

        $this->actingAs($this->submitter, 'api');
        $instance = $this->approvalService->initiate($this->createOrder(5_000), 'ORDER');

        $step = $instance->steps()->where('step_order', 1)->first();
        $step->update([
            'activated_at' => now()->subDays(3),
            'sla_target_date' => now()->subDay(),
            'first_reminder_sent_at' => null,
        ]);

        $this->artisan('approval:send-reminders')->assertSuccessful();

        $this->assertNotNull($step->fresh()->first_reminder_sent_at);
        Notification::assertSentTo($this->manager, ApprovalReminderNotification::class);
    }

    public function test_waiting_slot_can_be_reassigned_to_same_role_user(): void
    {
        Notification::fake();
        $this->seed(\Database\Seeders\ApprovalWorkflowSeeder::class);

        $newModerator = User::factory()->create(['email' => 'new-moderator@example.test']);
        $newModerator->assignRole('Moderator');

        $this->actingAs($this->submitter, 'api');
        $instance = $this->approvalService->initiate($this->createOrder(15_000), 'ORDER');

        $this->actingAs($this->manager, 'api');
        $this->approvalService->approve($instance->steps->where('step_order', 1)->first()->approvers->first());

        $slot = $instance->fresh()->steps()->where('step_order', 2)->with('approvers')->first()->approvers->first();

        $this->actingAs($this->moderator, 'api');
        $this->approvalService->reassign($slot, $newModerator, 'Moderator is unavailable');

        $this->assertDatabaseHas('approval_instance_step_approvers', [
            'id' => $slot->id,
            'status' => 'WAITING',
            'assigned_user_id' => $newModerator->id,
            'reassigned_to' => $newModerator->id,
            'actor_id' => $this->moderator->id,
            'reassignment_reason' => 'Moderator is unavailable',
        ]);

        $this->assertDatabaseHas('approval_logs', [
            'instance_id' => $instance->id,
            'instance_step_id' => $slot->instance_step_id,
            'action' => 'REASSIGNED',
            'actor_id' => $this->moderator->id,
            'target_user_id' => $newModerator->id,
            'notes' => 'Moderator is unavailable',
        ]);

        Notification::assertSentTo($newModerator, ApprovalRequestNotification::class);
    }

    public function test_reassignment_rejects_user_without_required_role(): void
    {
        Notification::fake();
        $this->seed(\Database\Seeders\ApprovalWorkflowSeeder::class);

        $wrongRoleUser = User::factory()->create(['email' => 'wrong-role@example.test']);
        $wrongRoleUser->assignRole('User');

        $this->actingAs($this->submitter, 'api');
        $instance = $this->approvalService->initiate($this->createOrder(15_000), 'ORDER');

        $this->actingAs($this->manager, 'api');
        $this->approvalService->approve($instance->steps->where('step_order', 1)->first()->approvers->first());

        $slot = $instance->fresh()->steps()->where('step_order', 2)->with('approvers')->first()->approvers->first();

        $this->actingAs($this->moderator, 'api');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(__('approval.reassignment_same_role_required', ['role' => 'Moderator']));

        $this->approvalService->reassign($slot, $wrongRoleUser, 'Bad replacement');
    }

    public function test_batch_approve_processes_multiple_slots(): void
    {
        Notification::fake();
        $this->seed(\Database\Seeders\ApprovalWorkflowSeeder::class);

        $extraManager = User::factory()->create(['email' => 'extra-manager@example.test']);
        $extraManager->assignRole('Management');

        $order1 = $this->createOrder(5_000);
        $order2 = $this->createOrder(7_000);

        $this->actingAs($this->submitter, 'api');
        $instance1 = $this->approvalService->initiate($order1, 'ORDER');
        $instance2 = $this->approvalService->initiate($order2, 'ORDER');

        $slot1 = $instance1->steps->where('step_order', 1)->first()->approvers->first();
        $slot2 = $instance2->steps->where('step_order', 1)->first()->approvers->first();

        $this->actingAs($this->manager, 'api');

        $response = $this->postJson('/api/v1/approvals/slots/batch-approve', [
            'slot_ids' => [$slot1->id, $slot2->id],
            'notes' => 'Bulk approval via API',
        ]);

        $response->assertOk()
            ->assertJson([
                'message' => __('approval.batch_approved', ['count' => 2]),
                'data' => [
                    'total' => 2,
                    'success' => 2,
                    'failed' => 0,
                ],
            ]);

        $this->assertDatabaseHas('approval_instance_step_approvers', [
            'id' => $slot1->id,
            'status' => 'APPROVED',
        ]);

        $this->assertDatabaseHas('approval_instance_step_approvers', [
            'id' => $slot2->id,
            'status' => 'APPROVED',
        ]);
    }

    public function test_batch_approve_partial_success(): void
    {
        Notification::fake();
        $this->seed(\Database\Seeders\ApprovalWorkflowSeeder::class);

        $order1 = $this->createOrder(5_000);
        $order2 = $this->createOrder(7_000);

        $this->actingAs($this->submitter, 'api');
        $instance1 = $this->approvalService->initiate($order1, 'ORDER');
        $instance2 = $this->approvalService->initiate($order2, 'ORDER');

        $slot1 = $instance1->steps->where('step_order', 1)->first()->approvers->first();
        $slot2 = $instance2->steps->where('step_order', 1)->first()->approvers->first();

        $slot1->update(['status' => 'APPROVED']);

        $this->actingAs($this->manager, 'api');

        $response = $this->postJson('/api/v1/approvals/slots/batch-approve', [
            'slot_ids' => [$slot1->id, $slot2->id],
            'notes' => 'Bulk approval attempt',
        ]);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'total' => 2,
                    'success' => 1,
                    'failed' => 1,
                ],
            ]);
    }

    private function createOrder(float $grandTotal): Order
    {
        return Order::create([
            'order_number' => 'ADV-' . uniqid(),
            'customer_name' => 'Advanced Flow Customer',
            'customer_email' => 'customer@example.test',
            'subtotal' => $grandTotal,
            'discount' => 0.00,
            'tax' => 0.00,
            'grand_total' => $grandTotal,
            'status' => 'pending',
        ]);
    }
}
