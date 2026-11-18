<?php

require __DIR__.'/../../vendor/autoload.php';

$app = require_once __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Domain\FileHosting\Models\FileVariant;
use App\Domain\FileHosting\Models\Folder;
use App\Domain\FileHosting\Models\HostedFile;
use App\Domain\Microstock\Services\AiBillingService;
use App\Domain\Microstock\Services\AiCreditService;
use App\Domain\Microstock\Services\AiDailyFreeService;
use Illuminate\Contracts\Console\Kernel;

$freeId = '019fbc21-eff1-72af-b14c-494ecbe14328';
$paidId = '019fbc22-1585-72f4-86d5-ac33faf984d5';

$freeFiles = [];
$paidFiles = [];

foreach (['free' => $freeId, 'paid' => $paidId] as $label => $fid) {
    $f = Folder::query()->find($fid);
    if ($f === null) {
        echo "{$label}: NOT FOUND\n";

        continue;
    }

    $names = HostedFile::query()
        ->where('folder_id', $fid)
        ->orderBy('name')
        ->pluck('name')
        ->all();

    if ($label === 'free') {
        $freeFiles = $names;
    } else {
        $paidFiles = $names;
    }

    $fileIds = HostedFile::query()->where('folder_id', $fid)->pluck('id');
    $variantMissing = 0;
    foreach ($fileIds as $fileId) {
        $has = FileVariant::query()
            ->where('file_id', $fileId)
            ->where('kind', setting_config('microstock.ai.vision_image.variant_kind', 'ai_1024'))
            ->exists();
        if (! $has) {
            $variantMissing++;
        }
    }

    echo "=== {$label} ({$fid}) ===\n";
    echo 'status: '.($f->collection_status?->value ?? 'null')."\n";
    echo 'billing_mode: '.($f->collection_billing_mode ?? 'null')."\n";
    echo 'hint_len: '.strlen((string) ($f->collection_hint ?? ''))."\n";
    echo 'owner: '.$f->user_id."\n";
    echo 'files: '.count($names)."\n";
    echo 'variants_missing: '.$variantMissing."\n";
    echo 'names: '.implode(', ', $names)."\n\n";
}

echo 'parity_names_match: '.($freeFiles === $paidFiles ? 'yes' : 'no')."\n";
echo 'parity_count: free='.count($freeFiles).' paid='.count($paidFiles)."\n\n";

$ownerId = Folder::query()->find($freeId)?->user_id;
if ($ownerId) {
    $daily = app(AiDailyFreeService::class);
    $credits = app(AiCreditService::class);
    $billing = app(AiBillingService::class);
    $n = count($freeFiles);

    echo "=== billing gates (owner {$ownerId}) ===\n";
    echo 'daily_free_enabled: '.($daily->isEnabled() ? 'yes' : 'no')."\n";
    echo json_encode($daily->snapshot((string) $ownerId), JSON_PRETTY_PRINT)."\n";
    echo 'prepaid_balance_micro_usd: '.$credits->balance((string) $ownerId)."\n";
    echo 'estimate_for_'.$n.'_assets: '.$billing->estimateForAssets($n)."\n";
}

echo "\n=== ai config ===\n";
echo 'vision_model: '.setting_config('microstock.ai.vision.model')."\n";
echo 'vision_free: '.setting_config('microstock.ai.vision.free_model')."\n";
echo 'thinking_model: '.setting_config('microstock.ai.thinking.model')."\n";
echo 'thinking_free: '.setting_config('microstock.ai.thinking.free_model')."\n";
echo 'inference_provider: '.setting_config('microstock.ai.inference_provider_slug')."\n";
