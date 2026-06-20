<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== DATA INTEGRITY CHECK ===\n\n";

// Check approval instance
$instance = \App\Models\ApprovalInstance::first();
echo "Approval Instance:\n";
echo "  ID: {$instance->id}\n";
echo "  Document Type: {$instance->document_type}\n";
echo "  Document ID: {$instance->document_id}\n";
echo "  Status: {$instance->status}\n\n";

// Check if order exists
echo "Checking if Order exists...\n";
$order = \App\Models\Order::find($instance->document_id);
if ($order) {
    echo "  ✓ Order FOUND\n";
    echo "  Order Number: {$order->order_number}\n";
    echo "  Order Status: {$order->status}\n";
} else {
    echo "  ✗ Order NOT FOUND\n\n";
    
    echo "All Orders in database:\n";
    $allOrders = \App\Models\Order::all();
    foreach ($allOrders as $o) {
        echo "  - ID: {$o->id}\n";
        echo "    Number: {$o->order_number}\n";
        echo "    Status: {$o->status}\n";
        echo "    Grand Total: {$o->grand_total}\n\n";
    }
}

echo "\n=== SOLUTION ===\n";
echo "Need to update approval instance document_id to point to existing Order\n";
echo "OR create new order and re-initiate approval\n";
