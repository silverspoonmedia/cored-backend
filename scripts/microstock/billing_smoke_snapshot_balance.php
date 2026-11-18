<?php

use App\Domain\Microstock\Services\AiCreditService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';
$app = require_once __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$ownerId = '019fbbf6-560b-729e-84de-d921fa2729cf';
$balance = app(AiCreditService::class)->balance($ownerId);
file_put_contents(storage_path('app/probe_results/billing_smoke_balance_before.json'), json_encode([
    'captured_at' => now()->toIso8601String(),
    'owner_id' => $ownerId,
    'prepaid_balance_micro_usd' => $balance,
], JSON_PRETTY_PRINT));
echo "balance_before: $balance\n";
