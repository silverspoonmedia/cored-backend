<?php

use App\Models\ApprovalInstance;
use App\Models\Order;

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== CHECK PENDING INSTANCE ===\n\n";

$instance = ApprovalInstance::where('status', 'PENDING')
    ->with(['steps.approvers.assignedUser', 'steps.workflowStep'])
    ->first();

if (! $instance) {
    echo "No PENDING instance found\n";
    exit;
}

$order = Order::find($instance->document_id);

echo 'Order: '.($order ? $order->order_number : 'NOT FOUND')."\n";
echo 'Grand Total: '.($order ? $order->grand_total : 'N/A')."\n";
echo "Instance Status: {$instance->status}\n\n";

echo "=== STEPS ===\n";
foreach ($instance->steps->sortBy('step_order') as $step) {
    echo "Step {$step->step_order}: {$step->step_name} - {$step->status}\n";
    echo "  Approvers: {$step->approvers->count()}\n";
    foreach ($step->approvers as $approver) {
        echo "    - {$approver->slot_label} ({$approver->status})\n";
        echo '      Assigned: '.($approver->assignedUser ? $approver->assignedUser->email : 'NULL')."\n";
    }
}

echo "\n=== WAITING APPROVALS ===\n";
$waiting = $instance->steps()
    ->where('status', 'IN_PROGRESS')
    ->with('approvers')
    ->get()
    ->flatMap(fn ($s) => $s->approvers->where('status', 'WAITING'));

echo "Count: {$waiting->count()}\n";
foreach ($waiting as $w) {
    echo "  - Step {$w->instanceStep->step_order}: {$w->instanceStep->step_name}\n";
    echo "    Slot: {$w->slot_label}\n";
    echo '    User: '.($w->assignedUser ? $w->assignedUser->email : 'NULL')."\n";
}

if ($waiting->count() > 1) {
    echo "\n⚠️  BUG DETECTED: {$waiting->count()} waiting approvals (should be 1)\n";
} else {
    echo "\n✓ No bug: {$waiting->count()} waiting approval\n";
}
