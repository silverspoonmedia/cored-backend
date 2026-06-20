<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Services\ApprovalService;
use Database\Seeders\ApprovalWorkflowSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ApprovalWorkflowThresholdTest extends TestCase
{
    use RefreshDatabase;

    protected ApprovalService $approvalService;

    protected User $submitter;

    protected User $manager;

    protected User $moderator;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        $this->seed(RoleSeeder::class);
        $this->seed(ApprovalWorkflowSeeder::class);
        $this->approvalService = app(ApprovalService::class);

        // Create users
        $this->submitter = User::factory()->create();
        $this->submitter->assignRole('User');

        $this->manager = User::factory()->create(['email' => 'manager@test.com']);
        $this->manager->assignRole('Management');

        $this->moderator = User::factory()->create(['email' => 'moderator@test.com']);
        $this->moderator->assignRole('Moderator');

        $this->admin = User::factory()->create(['email' => 'admin@test.com']);
        $this->admin->assignRole('Admin');
    }

    /**
     * Test: grand_total < 10000
     * Expected: Step 1 (Manager) → Step 4 (Final)
     * Skipped: Step 2 (Finance - requires >= 10000), Step 3 (Executive - requires >= 50000)
     */
    public function test_order_below_10k_skips_finance_and_executive(): void
    {
        // Create order with grand_total = 5000 (< 10000)
        $order = $this->createOrder(5000.00);

        $this->actingAs($this->submitter, 'api');
        $instance = $this->approvalService->initiate($order, 'ORDER');

        // Assert Step 1: Manager Review - IN_PROGRESS
        $step1 = $instance->steps->where('step_order', 1)->first();
        $this->assertEquals('IN_PROGRESS', $step1->status);
        $this->assertEquals(1, $step1->approvers->count());

        // Assert Step 2: Finance Approval - SKIPPED
        $step2 = $instance->steps->where('step_order', 2)->first();
        $this->assertEquals('SKIPPED', $step2->status);
        $this->assertEquals(0, $step2->approvers->count());

        // Assert Step 3: Executive Approval - SKIPPED
        $step3 = $instance->steps->where('step_order', 3)->first();
        $this->assertEquals('SKIPPED', $step3->status);
        $this->assertEquals(0, $step3->approvers->count());

        // Assert Step 4: Final Approval - WAITING
        $step4 = $instance->steps->where('step_order', 4)->first();
        $this->assertEquals('WAITING', $step4->status);
        $this->assertEquals(1, $step4->approvers->count());

        // Manager approves Step 1
        $this->actingAs($this->manager, 'api');
        $this->approvalService->approve($step1->approvers->first());

        // Refresh
        $instance = $instance->fresh();
        $step4 = $step4->fresh();

        // Assert Step 4 now IN_PROGRESS
        $this->assertEquals('IN_PROGRESS', $step4->status);

        // Assert only 1 WAITING approval for admin
        $waitingForAdmin = $instance->steps()
            ->where('status', 'IN_PROGRESS')
            ->with('approvers')
            ->get()
            ->flatMap(fn ($s) => $s->approvers->where('status', 'WAITING'))
            ->filter(fn ($a) => $a->assigned_user_id === $this->admin->id);

        $this->assertEquals(1, $waitingForAdmin->count(),
            'Should have exactly 1 WAITING approval for admin, found: '.$waitingForAdmin->count());
    }

    /**
     * Test: grand_total = 10000 (boundary - equal)
     * Expected: Step 1 (Manager) → Step 2 (Finance) → Step 4 (Final)
     * Skipped: Step 3 (Executive - requires >= 50000)
     */
    public function test_order_exactly_10k_includes_finance(): void
    {
        // Create order with grand_total = 10000 (>= 10000 condition met)
        $order = $this->createOrder(10000.00);

        $this->actingAs($this->submitter, 'api');
        $instance = $this->approvalService->initiate($order, 'ORDER');

        // Assert Step 1: IN_PROGRESS
        $step1 = $instance->steps->where('step_order', 1)->first();
        $this->assertEquals('IN_PROGRESS', $step1->status);

        // Assert Step 2: WAITING (not SKIPPED)
        $step2 = $instance->steps->where('step_order', 2)->first();
        $this->assertEquals('WAITING', $step2->status);
        $this->assertEquals(1, $step2->approvers->count());

        // Assert Step 3: SKIPPED
        $step3 = $instance->steps->where('step_order', 3)->first();
        $this->assertEquals('SKIPPED', $step3->status);

        // Assert Step 4: WAITING
        $step4 = $instance->steps->where('step_order', 4)->first();
        $this->assertEquals('WAITING', $step4->status);
    }

    /**
     * Test: grand_total > 10000 but < 50000
     * Expected: Step 1 (Manager) → Step 2 (Finance) → Step 4 (Final)
     * Skipped: Step 3 (Executive - requires >= 50000)
     */
    public function test_order_between_10k_and_50k_includes_finance_skips_executive(): void
    {
        // Create order with grand_total = 25000 (>= 10000 but < 50000)
        $order = $this->createOrder(25000.00);

        $this->actingAs($this->submitter, 'api');
        $instance = $this->approvalService->initiate($order, 'ORDER');

        // Assert Step 2: WAITING (Finance should be active)
        $step2 = $instance->steps->where('step_order', 2)->first();
        $this->assertEquals('WAITING', $step2->status);
        $this->assertEquals(1, $step2->approvers->count());

        // Assert Step 3: SKIPPED (Executive should be skipped)
        $step3 = $instance->steps->where('step_order', 3)->first();
        $this->assertEquals('SKIPPED', $step3->status);
        $this->assertEquals(0, $step3->approvers->count());
    }

    /**
     * Test: grand_total = 50000 (boundary - equal)
     * Expected: Step 1 (Manager) → Step 2 (Finance) → Step 3 (Executive) → Step 4 (Final)
     * All steps active
     */
    public function test_order_exactly_50k_includes_all_steps(): void
    {
        // Create order with grand_total = 50000 (>= 50000 condition met)
        $order = $this->createOrder(50000.00);

        $this->actingAs($this->submitter, 'api');
        $instance = $this->approvalService->initiate($order, 'ORDER');

        // Assert ALL steps are WAITING or IN_PROGRESS (none SKIPPED)
        $step1 = $instance->steps->where('step_order', 1)->first();
        $step2 = $instance->steps->where('step_order', 2)->first();
        $step3 = $instance->steps->where('step_order', 3)->first();
        $step4 = $instance->steps->where('step_order', 4)->first();

        $this->assertEquals('IN_PROGRESS', $step1->status);
        $this->assertEquals('WAITING', $step2->status);
        $this->assertEquals('WAITING', $step3->status);
        $this->assertEquals('WAITING', $step4->status);

        // Assert Step 2 has approvers
        $this->assertEquals(1, $step2->approvers->count());

        // Assert Step 3 has approvers
        $this->assertEquals(1, $step3->approvers->count());

        // Assert Step 4 has approvers
        $this->assertEquals(1, $step4->approvers->count());
    }

    /**
     * Test: grand_total > 50000
     * Expected: All 4 steps active
     */
    public function test_order_above_50k_includes_all_steps(): void
    {
        // Create order with grand_total = 100000 (> 50000)
        $order = $this->createOrder(100000.00);

        $this->actingAs($this->submitter, 'api');
        $instance = $this->approvalService->initiate($order, 'ORDER');

        // Assert NO steps are SKIPPED
        foreach ($instance->steps as $step) {
            $this->assertNotEquals('SKIPPED', $step->status,
                "Step {$step->step_order} should not be SKIPPED for order amount 100000");
        }

        // Assert Step 3 (Executive) is WAITING
        $step3 = $instance->steps->where('step_order', 3)->first();
        $this->assertEquals('WAITING', $step3->status);
        $this->assertEquals(1, $step3->approvers->count());
    }

    /**
     * Test full approval flow for small order
     * Verify NO duplicate/stuck approvals after each step
     */
    public function test_full_flow_small_order_no_duplicate_approvals(): void
    {
        $order = $this->createOrder(5000.00);

        $this->actingAs($this->submitter, 'api');
        $instance = $this->approvalService->initiate($order, 'ORDER');

        // Step 1: Manager approves
        $this->actingAs($this->manager, 'api');
        $step1 = $instance->steps->where('step_order', 1)->first();
        $this->approvalService->approve($step1->approvers->first());

        // Check: Only 1 WAITING approval should exist
        $instance = $instance->fresh();
        $waiting = $instance->steps()
            ->where('status', 'IN_PROGRESS')
            ->with('approvers')
            ->get()
            ->flatMap(fn ($s) => $s->approvers->where('status', 'WAITING'));

        $this->assertEquals(1, $waiting->count(),
            'After manager approval, should have exactly 1 WAITING approval, found: '.$waiting->count());

        // Step 4: Admin approves
        $this->actingAs($this->admin, 'api');
        $step4 = $instance->steps->where('step_order', 4)->first();
        $this->approvalService->approve($step4->approvers->first());

        // Check: No more WAITING approvals
        $instance = $instance->fresh();
        $waitingAfterComplete = $instance->steps()
            ->whereIn('status', ['IN_PROGRESS', 'WAITING'])
            ->with('approvers')
            ->get()
            ->flatMap(fn ($s) => $s->approvers->where('status', 'WAITING'));

        $this->assertEquals(0, $waitingAfterComplete->count(),
            'After completing all approvals, should have 0 WAITING approvals, found: '.$waitingAfterComplete->count());

        // Verify order status updated
        $order->refresh();
        $this->assertEquals('completed', $order->status);
    }

    private function createOrder(float $grandTotal): Order
    {
        return Order::create([
            'order_number' => 'TEST-'.uniqid(),
            'customer_name' => 'Test Customer',
            'customer_email' => 'test@example.com',
            'subtotal' => $grandTotal,
            'discount' => 0.00,
            'tax' => 0.00,
            'grand_total' => $grandTotal,
            'status' => 'pending',
        ]);
    }
}
