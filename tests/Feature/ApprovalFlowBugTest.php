<?php

namespace Tests\Feature;

use App\Models\ApprovalInstance;
use App\Models\ApprovalWorkflow;
use App\Models\Order;
use App\Models\User;
use App\Services\ApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ApprovalFlowBugTest extends TestCase
{
    use RefreshDatabase;

    protected ApprovalService $approvalService;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\ApprovalWorkflowSeeder::class);
        $this->approvalService = app(ApprovalService::class);
    }

    public function test_small_order_should_have_only_one_final_approval_not_two(): void
    {
        // Create users
        $submitter = User::factory()->create();
        $submitter->assignRole('User');
        
        $manager = User::factory()->create(['email' => 'manager@test.com']);
        $manager->assignRole('Management');
        
        $moderator = User::factory()->create(['email' => 'moderator@test.com']);
        $moderator->assignRole('Moderator');
        
        $admin = User::factory()->create(['email' => 'admin@test.com']);
        $admin->assignRole('Admin');

        // Create order with small amount (< 10000) to skip Finance & Executive steps
        $order = Order::create([
            'order_number' => 'TEST-FLOW-001',
            'customer_name' => 'Test Customer',
            'customer_email' => 'test@example.com',
            'subtotal' => 5000.00,
            'discount' => 0.00,
            'tax' => 0.00,
            'grand_total' => 5000.00,
            'status' => 'pending',
        ]);

        // Login as submitter and initiate approval
        $this->actingAs($submitter, 'api');
        $instance = $this->approvalService->initiate($order, 'ORDER');

        // Step 1: Manager Review should be IN_PROGRESS
        $step1 = $instance->steps->where('step_order', 1)->first();
        $this->assertEquals('IN_PROGRESS', $step1->status);
        $this->assertEquals(1, $step1->approvers->count(), 'Step 1 should have exactly 1 approver');

        // Step 2 & 3 should be SKIPPED (amount < 10000)
        $step2 = $instance->steps->where('step_order', 2)->first();
        $step3 = $instance->steps->where('step_order', 3)->first();
        $this->assertEquals('SKIPPED', $step2->status);
        $this->assertEquals('SKIPPED', $step3->status);

        // Step 4 should be WAITING
        $step4 = $instance->steps->where('step_order', 4)->first();
        $this->assertEquals('WAITING', $step4->status);
        
        // Manager approves Step 1
        $this->actingAs($manager, 'api');
        $managerSlot = $step1->approvers->first();
        $this->approvalService->approve($managerSlot);

        // Refresh instance
        $instance = $instance->fresh();
        
        // Check: Only 1 pending approval should exist for admin (Step 4 Final)
        $pendingForAdmin = $instance->steps()
            ->where('status', 'IN_PROGRESS')
            ->with('approvers')
            ->get()
            ->flatMap(fn($step) => $step->approvers->where('status', 'WAITING'))
            ->filter(fn($approver) => $approver->assigned_user_id === $admin->id);
        
        $this->assertEquals(
            1, 
            $pendingForAdmin->count(), 
            'Admin should have EXACTLY 1 pending approval (Step 4 Final), but found: ' . $pendingForAdmin->count()
        );

        // Verify Step 4 is now IN_PROGRESS
        $step4 = $step4->fresh();
        $this->assertEquals('IN_PROGRESS', $step4->status);
        
        // Verify Step 4 has exactly 1 approver
        $this->assertEquals(
            1,
            $step4->approvers->count(),
            'Step 4 should have exactly 1 approver, but found: ' . $step4->approvers->count()
        );
    }
}
