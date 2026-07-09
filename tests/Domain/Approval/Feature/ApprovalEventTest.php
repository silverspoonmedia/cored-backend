<?php

namespace Tests\Domain\Approval\Feature;

use App\Domain\Approval\Events\ApprovalCompleted;
use App\Domain\Approval\Models\ApprovalInstance;
use App\Domain\Approval\Models\ApprovalWorkflow;
use App\Domain\Playground\Models\Order;
use App\Domain\Identity\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ApprovalEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_approval_completed_event_updates_order_status_to_approved(): void
    {
        Notification::fake();
        // Setup
        $this->seed(RoleSeeder::class);

        // Create a user
        $user = User::factory()->create();
        $user->assignRole('Admin');

        // Create an order with pending status
        $order = Order::create([
            'order_number' => 'TEST-ORDER-001',
            'customer_name' => 'Test Customer',
            'customer_email' => 'test@example.com',
            'subtotal' => 1000.00,
            'discount' => 0.00,
            'tax' => 100.00,
            'grand_total' => 1100.00,
            'status' => 'pending',
        ]);

        $this->assertEquals('pending', $order->status);

        // Create workflow
        $workflow = ApprovalWorkflow::create([
            'document_type' => 'ORDER',
            'name' => 'Test Workflow',
            'version' => 1,
            'is_active' => true,
        ]);

        // Create approval instance
        $instance = ApprovalInstance::create([
            'workflow_id' => $workflow->id,
            'workflow_version' => 1,
            'document_type' => 'ORDER',
            'document_id' => $order->id,
            'status' => 'APPROVED',
            'submitted_by' => $user->id,
            'completed_at' => now(),
        ]);

        // Dispatch event
        ApprovalCompleted::dispatch($instance);

        // Assert order status changed
        $order->refresh();
        $this->assertEquals('completed', $order->status);
    }

    public function test_approval_completed_event_updates_order_status_to_rejected(): void
    {
        Notification::fake();
        // Setup
        $this->seed(RoleSeeder::class);

        // Create a user
        $user = User::factory()->create();
        $user->assignRole('Admin');

        // Create an order with pending status
        $order = Order::create([
            'order_number' => 'TEST-ORDER-002',
            'customer_name' => 'Test Customer',
            'customer_email' => 'test@example.com',
            'subtotal' => 1000.00,
            'discount' => 0.00,
            'tax' => 100.00,
            'grand_total' => 1100.00,
            'status' => 'pending',
        ]);

        $this->assertEquals('pending', $order->status);

        // Create workflow
        $workflow = ApprovalWorkflow::create([
            'document_type' => 'ORDER',
            'name' => 'Test Workflow',
            'version' => 1,
            'is_active' => true,
        ]);

        // Create approval instance with REJECTED status
        $instance = ApprovalInstance::create([
            'workflow_id' => $workflow->id,
            'workflow_version' => 1,
            'document_type' => 'ORDER',
            'document_id' => $order->id,
            'status' => 'REJECTED',
            'submitted_by' => $user->id,
            'completed_at' => now(),
        ]);

        // Dispatch event
        ApprovalCompleted::dispatch($instance);

        // Assert order status changed to rejected
        $order->refresh();
        $this->assertEquals('cancelled', $order->status);
    }
}
