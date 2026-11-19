<?php

namespace App\Domain\Inference\Database\Seeders;

use App\Domain\Inference\Models\AiModelCard;
use App\Domain\Inference\Models\InferenceProvider;
use App\Domain\Inference\Models\ProviderModelRate;
use Illuminate\Database\Seeder;

class InferenceCatalogSeeder extends Seeder
{
    /**
     * Public list-price models (USD per 1M tokens). Mirrors legacy microstock rate card.
     *
     * @var array<string, array{input_per_million_usd: float, output_per_million_usd: float}>
     */
    private const MODEL_RATES = [
        'grok-grok-4.5-high' => ['input_per_million_usd' => 3.0, 'output_per_million_usd' => 15.0],
        'gpt-5.4' => ['input_per_million_usd' => 2.5, 'output_per_million_usd' => 15.0],
        'kimi-k3' => ['input_per_million_usd' => 3.0, 'output_per_million_usd' => 15.0],
        'gpt-5.6' => ['input_per_million_usd' => 5.0, 'output_per_million_usd' => 30.0],
        'gpt-5.6-sol' => ['input_per_million_usd' => 5.0, 'output_per_million_usd' => 30.0],
        'gpt-5.6-terra' => ['input_per_million_usd' => 2.5, 'output_per_million_usd' => 15.0],
        'gpt-5.6-luna' => ['input_per_million_usd' => 1.0, 'output_per_million_usd' => 6.0],
        'claude-opus-5' => ['input_per_million_usd' => 5.0, 'output_per_million_usd' => 25.0],
        'anthropic/claude-opus-5' => ['input_per_million_usd' => 5.0, 'output_per_million_usd' => 25.0],
        'claude-sonnet-5' => ['input_per_million_usd' => 2.0, 'output_per_million_usd' => 10.0],
        'anthropic/claude-sonnet-5' => ['input_per_million_usd' => 2.0, 'output_per_million_usd' => 10.0],
    ];

    /** @var list<array{slug: string, name: string}> */
    private const PROVIDERS = [
        ['slug' => 'openwebui-9router', 'name' => 'Open WebUI / 9Router'],
        ['slug' => 'codex', 'name' => 'Codex upstream'],
        ['slug' => 'anthropic-api', 'name' => 'Anthropic API'],
    ];

    public function run(): void
    {
        $cards = [];

        foreach (self::MODEL_RATES as $slug => $rates) {
            $cards[$slug] = AiModelCard::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'display_name' => $slug,
                    'is_active' => true,
                ],
            );
        }

        foreach (self::PROVIDERS as $providerDef) {
            $provider = InferenceProvider::query()->updateOrCreate(
                ['slug' => $providerDef['slug']],
                [
                    'name' => $providerDef['name'],
                    'is_active' => true,
                ],
            );

            $this->syncProviderRates($provider, $cards);
        }
    }

    /**
     * @param  array<string, AiModelCard>  $cards
     */
    private function syncProviderRates(InferenceProvider $provider, array $cards): void
    {
        foreach (self::MODEL_RATES as $slug => $rates) {
            $card = $cards[$slug] ?? null;
            if ($card === null) {
                continue;
            }

            ProviderModelRate::query()->updateOrCreate(
                [
                    'inference_provider_id' => $provider->id,
                    'model_card_id' => $card->id,
                ],
                [
                    'input_per_million_usd' => $rates['input_per_million_usd'],
                    'output_per_million_usd' => $rates['output_per_million_usd'],
                ],
            );
        }
    }
}
