<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== CEK STATUS APPROVAL INSTANCE ===\n\n";

$instances = \App\Models\ApprovalInstance::with(['steps.approvers', 'steps.workflowStep'])->get();

foreach ($instances as $instance) {
    echo "Instance ID: {$instance->id}\n";
    echo "Document: {$instance->document_type} - {$instance->document_id}\n";
    echo "Status: {$instance->status}\n";
    echo "Completed At: " . ($instance->completed_at ?? 'NULL') . "\n\n";
    
    echo "STEPS:\n";
    foreach ($instance->steps as $step) {
        echo "  Step {$step->step_order}: {$step->step_name}\n";
        echo "    Status: {$step->status}\n";
        echo "    Parallel Mode: {$step->workflowStep->parallel_mode}\n";
        echo "    Activated At: " . ($step->activated_at ?? 'NULL') . "\n";
        echo "    Completed At: " . ($step->completed_at ?? 'NULL') . "\n";
        
        echo "    APPROVERS:\n";
        foreach ($step->approvers as $approver) {
            echo "      - {$approver->slot_label}: {$approver->status}";
            if ($approver->acted_at) {
                echo " (acted at {$approver->acted_at})";
            }
            echo "\n";
        }
        echo "\n";
    }
    
    echo "---\n\n";
}

echo "=== DIAGNOSA ===\n";
$instance = $instances->first();
if ($instance) {
    echo "Checking if instance should be completed...\n\n";
    
    $allStepsCompleted = true;
    $hasRejected = false;
    
    foreach ($instance->steps as $step) {
        echo "Step {$step->step_order} ({$step->step_name}): {$step->status}\n";
        
        if ($step->status === 'SKIPPED') {
            echo "  → SKIPPED (doesn't block completion)\n";
            continue;
        }
        
        if ($step->status !== 'APPROVED') {
            $allStepsCompleted = false;
            echo "  → NOT APPROVED (blocks completion)\n";
            
            // Check approver status
            $totalApprovers = $step->approvers->count();
            $approvedCount = $step->approvers->where('status', 'APPROVED')->count();
            $waitingCount = $step->approvers->where('status', 'WAITING')->count();
            
            echo "    Approvers: {$approvedCount}/{$totalApprovers} approved, {$waitingCount} waiting\n";
        } else {
            echo "  → APPROVED ✓\n";
        }
        
        if ($step->status === 'REJECTED') {
            $hasRejected = true;
        }
    }
    
    echo "\n";
    echo "All steps completed: " . ($allStepsCompleted ? 'YES' : 'NO') . "\n";
    echo "Has rejected step: " . ($hasRejected ? 'YES' : 'NO') . "\n";
    echo "Expected instance status: " . ($hasRejected ? 'REJECTED' : ($allStepsCompleted ? 'APPROVED' : 'PENDING')) . "\n";
    echo "Actual instance status: {$instance->status}\n";
}
