<?php

use App\Domain\Approval\Events\ApprovalCompleted;
use App\Domain\Approval\Models\ApprovalInstance;
use App\Domain\Playground\Models\Order;

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== MANUAL EVENT TEST ===\n\n";

$instance = ApprovalInstance::first();
echo "Instance ID: {$instance->id}\n";
echo "Instance Status: {$instance->status}\n";
echo "Document ID: {$instance->document_id}\n\n";

$order = Order::find($instance->document_id);
echo "Order ID: {$order->id}\n";
echo "Order Status BEFORE: {$order->status}\n\n";

echo "Dispatching ApprovalCompleted event...\n";
ApprovalCompleted::dispatch($instance);
echo "Event dispatched!\n\n";

// Refresh order from DB
$order = $order->fresh();
echo "Order Status AFTER: {$order->status}\n\n";

// Check if Order model has 'status' in fillable
echo "=== Order Model Check ===\n";
$orderModel = new Order;
echo 'Fillable attributes: '.json_encode($orderModel->getFillable())."\n";
