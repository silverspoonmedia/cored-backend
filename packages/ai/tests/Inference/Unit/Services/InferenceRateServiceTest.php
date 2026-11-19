<?php

namespace Tests\Domain\Inference\Unit\Services;

use App\Domain\Inference\Database\Seeders\InferenceCatalogSeeder;
use App\Domain\Inference\Enums\InferenceRateSource;
use App\Domain\Inference\Services\InferenceRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InferenceRateServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InferenceCatalogSeeder::class);
    }

    public function test_rates_for_provider_model_resolves_seeded_entry(): void
    {
        $rates = app(InferenceRateService::class)->ratesForProviderModel('gpt-5.6-luna', 'openwebui-9router');

        $this->assertSame(1.0, $rates['input_per_million_usd']);
        $this->assertSame(6.0, $rates['output_per_million_usd']);
        $this->assertFalse($rates['from_default']);
        $this->assertSame(InferenceRateSource::Known->value, $rates['rate_source']);
        $this->assertNotNull($rates['model_card_id']);
        $this->assertNotNull($rates['inference_provider_id']);
        $this->assertSame('openwebui-9router', $rates['provider_slug']);
        $this->assertTrue(app(InferenceRateService::class)->isRegisteredOnProvider('gpt-5.6-luna', 'openwebui-9router'));
    }

    public function test_unknown_slug_uses_provisional_config_rates(): void
    {
        config([
            'inference.unknown_model_rates' => [
                'input_per_million_usd' => 12.0,
                'output_per_million_usd' => 48.0,
            ],
        ]);

        $rates = app(InferenceRateService::class)->ratesForProviderModel('totally-unknown-model');

        $this->assertSame(12.0, $rates['input_per_million_usd']);
        $this->assertSame(48.0, $rates['output_per_million_usd']);
        $this->assertTrue($rates['from_default']);
        $this->assertSame(InferenceRateSource::UnknownProvisional->value, $rates['rate_source']);
        $this->assertFalse(app(InferenceRateService::class)->isRegisteredOnProvider('totally-unknown-model'));
    }

    public function test_unknown_slug_falls_back_to_default_rates_when_provisional_missing(): void
    {
        config([
            'inference.unknown_model_rates' => [],
            'inference.default_rates' => [
                'input_per_million_usd' => 7.0,
                'output_per_million_usd' => 21.0,
            ],
        ]);

        $rates = app(InferenceRateService::class)->ratesForProviderModel('totally-unknown-model');

        $this->assertSame(7.0, $rates['input_per_million_usd']);
        $this->assertSame(21.0, $rates['output_per_million_usd']);
        $this->assertSame(InferenceRateSource::ConfigDefault->value, $rates['rate_source']);
    }

    public function test_ensure_model_card_creates_card_for_unknown_slug(): void
    {
        $card = app(InferenceRateService::class)->ensureModelCard('new-model-slug');

        $this->assertSame('new-model-slug', $card->slug);
        $this->assertDatabaseHas('ai_model_cards', ['slug' => 'new-model-slug']);
    }

    public function test_ensure_model_card_marks_needs_classification(): void
    {
        $card = app(InferenceRateService::class)->ensureModelCard('needs-classification-slug', markNeedsClassification: true);

        $this->assertTrue($card->needs_rate_classification);
        $this->assertDatabaseHas('ai_model_cards', [
            'slug' => 'needs-classification-slug',
            'needs_rate_classification' => true,
        ]);
    }
}
