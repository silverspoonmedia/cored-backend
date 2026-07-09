<?php

use App\Domain\Approval\Models\ApprovalInstance;
use App\Domain\Playground\Models\Order;
use App\Domain\Identity\Models\User;

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== DIAGNOSE: 2 PENDING APPROVALS BUG ===\n\n";

// Get latest order with approval
$order = Order::latest()->first();
if (! $order) {
    echo "No orders found\n";
    exit;
}

echo "Order: {$order->order_number}\n";
echo "Grand Total: {$order->grand_total}\n";
echo "Status: {$order->status}\n\n";

// Get approval instance for this order
$instance = ApprovalInstance::where('document_id', $order->id)
    ->where('document_type', 'ORDER')
    ->with(['steps.approvers.assignedUser', 'steps.workflowStep'])
    ->first();

if (! $instance) {
    echo "No approval instance found for this order\n";
    exit;
}

echo "Instance Status: {$instance->status}\n\n";

echo "=== STEPS BREAKDOWN ===\n";
foreach ($instance->steps as $step) {
    echo "\nStep {$step->step_order}: {$step->step_name}\n";
    echo "  Status: {$step->status}\n";
    echo '  Condition: '.json_encode($step->workflowStep->condition)."\n";

    if ($step->condition_evaluated) {
        echo '  Condition Evaluated: '.json_encode($step->condition_evaluated)."\n";
    }

    echo "  Approvers: {$step->approvers->count()}\n";
    foreach ($step->approvers as $approver) {
        echo "    - ID: {$approver->id}\n";
        echo "      Slot: {$approver->slot_label}\n";
        echo "      Role: {$approver->required_role}\n";
        echo '      Assigned: '.($approver->assignedUser ? $approver->assignedUser->email : 'NULL')."\n";
        echo "      Status: {$approver->status}\n";
        if ($approver->acted_at) {
            echo "      Acted at: {$approver->acted_at}\n";
        }
    }
}

echo "\n=== ANALYSIS ===\n";

// Count pending approvals for Admin users
$adminUsers = User::role('Admin')->get();
echo "Admin users in DB: {$adminUsers->count()}\n";
foreach ($adminUsers as $admin) {
    echo "  - {$admin->email} (ID: {$admin->id})\n";
}

echo "\n";

// Get all WAITING approvals
$waitingApprovals = $instance->steps()
    ->where('status', 'IN_PROGRESS')
    ->with('approvers')
    ->get()
    ->flatMap(fn ($step) => $step->approvers->where('status', 'WAITING'));

echo "Total WAITING approvals: {$waitingApprovals->count()}\n";
foreach ($waitingApprovals as $approval) {
    echo "  - Step {$approval->instanceStep->step_order}: {$approval->instanceStep->step_name}\n";
    echo "    Slot: {$approval->slot_label}\n";
    echo '    Assigned to: '.($approval->assignedUser ? $approval->assignedUser->email : 'NULL')."\n";
}

echo "\n=== ROOT CAUSE ===\n";
if ($waitingApprovals->count() > 1) {
    echo "BUG CONFIRMED: {$waitingApprovals->count()} pending approvals exist!\n";
    echo "This should be exactly 1 for admin.\n\n";

    echo "Investigating...\n";
    $stepsWithWaiting = $instance->steps->filter(fn ($s) => $s->status === 'IN_PROGRESS' && $s->approvers->where('status', 'WAITING')->count() > 0
    );

    echo "Steps with WAITING approvals: {$stepsWithWaiting->count()}\n";
    foreach ($stepsWithWaiting as $step) {
        echo "  - Step {$step->step_order}: {$step->step_name} (status: {$step->status})\n";
        echo '    Should it be IN_PROGRESS? '.($step->condition_evaluated['result'] ?? 'N/A')."\n";
    }
} else {
    echo "No bug detected - only {$waitingApprovals->count()} pending approval(s)\n";
}
