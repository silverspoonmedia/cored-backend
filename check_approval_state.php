<?php

use App\Models\ApprovalInstance;
use App\Models\ApprovalInstanceStepApprover;
use App\Models\ApprovalWorkflow;
use App\Models\Order;
use App\Models\User;

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== 1. CEK WORKFLOW ===\n";
$workflow = ApprovalWorkflow::where('document_type', 'ORDER')->where('is_active', true)->first();
if ($workflow) {
    echo "✓ Workflow ID: {$workflow->id}\n";
    echo "  Name: {$workflow->name}\n";
    echo "  Steps: {$workflow->steps->count()}\n";
} else {
    echo "✗ PROBLEM: No active workflow for ORDER\n";
}

echo "\n=== 2. CEK USER MANAGER ===\n";
$manager = User::where('email', 'manager@example.com')->first();
if ($manager) {
    echo "✓ User ID: {$manager->id}\n";
    echo "  Name: {$manager->name}\n";
    echo '  Roles: '.$manager->getRoleNames()->implode(', ')."\n";
} else {
    echo "✗ PROBLEM: User manager@example.com not found\n";
}

echo "\n=== 3. CEK ORDERS ===\n";
$orders = Order::latest()->take(5)->get();
echo 'Total orders: '.Order::count()."\n";
foreach ($orders as $order) {
    echo "  Order #{$order->order_number} - Total: {$order->grand_total} - Status: {$order->status}\n";
}

echo "\n=== 4. CEK APPROVAL INSTANCES ===\n";
$instances = ApprovalInstance::with('steps.approvers')->get();
echo "Total approval instances: {$instances->count()}\n";
foreach ($instances as $inst) {
    echo "  Instance {$inst->id} - {$inst->document_type} - Status: {$inst->status}\n";
    foreach ($inst->steps as $step) {
        echo "    Step {$step->step_order}: {$step->step_name} ({$step->status})\n";
        foreach ($step->approvers as $approver) {
            echo "      - {$approver->slot_label} assigned to user_id: {$approver->assigned_user_id} (status: {$approver->status})\n";
        }
    }
}

echo "\n=== 5. CEK PENDING FOR MANAGER ===\n";
if ($manager) {
    $pending = ApprovalInstanceStepApprover::where('assigned_user_id', $manager->id)
        ->where('status', 'WAITING')
        ->whereHas('instanceStep', function ($q) {
            $q->where('status', 'IN_PROGRESS');
        })
        ->get();
    echo "Pending approvals for manager: {$pending->count()}\n";
    foreach ($pending as $p) {
        echo "  - Slot {$p->id}: {$p->slot_label} for {$p->instanceStep->instance->document_type}\n";
    }
}

echo "\n=== 6. CEK LARAVEL LOGS (Last 20 lines) ===\n";
$logPath = storage_path('logs/laravel.log');
if (file_exists($logPath)) {
    $lines = file($logPath);
    $lastLines = array_slice($lines, -20);
    foreach ($lastLines as $line) {
        if (stripos($line, 'approval') !== false || stripos($line, 'error') !== false || stripos($line, 'warning') !== false) {
            echo $line;
        }
    }
} else {
    echo "No log file found\n";
}
