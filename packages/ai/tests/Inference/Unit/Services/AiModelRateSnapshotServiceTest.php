<?php

namespace App\Domain\Inference\Tests\Unit\Services;

use App\Domain\Inference\Database\Seeders\InferenceCatalogSeeder;
use App\Domain\Inference\Models\AiModelRateSnapshot;
use App\Domain\Inference\Models\InferenceProvider;
use App\Domain\Inference\Models\ProviderModelRate;
use App\Domain\Inference\Services\AiModelRateSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiModelRateSnapshotServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InferenceCatalogSeeder::class);
    }

    public function test_capture_dedupes_identical_rates(): void
    {
        $service = app(AiModelRateSnapshotService::class);

        $first = $service->capture('gpt-5.6-luna');
        $second = $service->capture('gpt-5.6-luna');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, AiModelRateSnapshot::query()->where('model_slug', 'gpt-5.6-luna')->count());
    }

    public function test_capture_creates_new_row_when_provider_rates_change(): void
    {
        $service = app(AiModelRateSnapshotService::class);
        $first = $service->capture('gpt-5.4');

        $provider = InferenceProvider::query()->where('slug', 'openwebui-9router')->firstOrFail();
        $rate = ProviderModelRate::query()
            ->where('inference_provider_id', $provider->id)
            ->whereHas('modelCard', fn ($q) => $q->where('slug', 'gpt-5.4'))
            ->firstOrFail();
        $rate->forceFill(['input_per_million_usd' => 3.5])->save();

        $second = $service->capture('gpt-5.4');

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(2, AiModelRateSnapshot::query()->where('model_slug', 'gpt-5.4')->count());
    }
}
