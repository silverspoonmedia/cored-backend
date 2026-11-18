<?php

namespace Tests\Domain\Microstock\Unit\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Microstock\Enums\BillingSource;
use App\Domain\Microstock\Models\AiUsageEvent;
use App\Domain\Microstock\Services\HttpAiMetadataService;
use App\Domain\Microstock\Support\AnalysisBillingContext;
use App\Domain\Inference\Database\Seeders\InferenceCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BindsAiConnection;
use Tests\TestCase;

class HttpAiMetadataServiceTierModelTest extends TestCase
{
    use BindsAiConnection;
    use RefreshDatabase;

    private const ENDPOINT = 'https://ai.test/v1/chat/completions';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(InferenceCatalogSeeder::class);

        $this->bindAiConnection();

        config([
            'microstock.ai.http_retries' => 0,
            'microstock.ai.vision' => [
                'endpoint' => self::ENDPOINT,
                'model' => 'paid.vision',
                'free_model' => 'free.vision',
                'api_key' => 'k',
                'timeout' => 30,
            ],
            'microstock.ai.thinking' => [
                'endpoint' => self::ENDPOINT,
                'model' => 'paid.thinking',
                'free_model' => 'free.thinking',
                'api_key' => 'k',
                'timeout' => 30,
            ],
        ]);
    }

    public function test_without_billing_context_uses_paid_default_models(): void
    {
        Http::fake([self::ENDPOINT => Http::sequence()
            ->push($this->response('Vision title', 'resolved.vision'), 200)
            ->push($this->response('Curated title', 'resolved.thinking'), 200),
        ]);

        (app(HttpAiMetadataService::class))->generate('data:image/jpeg;base64,xx');

        $this->assertSentModels(['paid.vision', 'paid.thinking']);
        $this->assertDatabaseCount('microstock_ai_usage_events', 0);
    }

    public function test_prepaid_context_uses_paid_models(): void
    {
        $user = User::factory()->create();
        Http::fake([self::ENDPOINT => Http::sequence()
            ->push($this->response('Vision title', 'resolved.vision'), 200)
            ->push($this->response('Curated title', 'resolved.thinking'), 200),
        ]);

        AnalysisBillingContext::run(
            new AnalysisBillingContext(
                billingUserId: (string) $user->id,
                billingSource: BillingSource::Prepaid,
            ),
            fn (): array => (app(HttpAiMetadataService::class))->generate('data:image/jpeg;base64,xx'),
        );

        $this->assertSentModels(['paid.vision', 'paid.thinking']);
        $events = AiUsageEvent::query()->get()->keyBy('stage');
        $this->assertSame('paid.vision', $events->get('vision')?->alias_model);
        $this->assertSame('paid.thinking', $events->get('thinking')?->alias_model);
    }

    public function test_daily_free_context_uses_free_models_and_records_aliases(): void
    {
        $user = User::factory()->create();
        Http::fake([self::ENDPOINT => Http::sequence()
            ->push($this->response('Vision title', 'resolved.free.vision'), 200)
            ->push($this->response('Curated title', 'resolved.free.thinking'), 200),
        ]);

        AnalysisBillingContext::run(
            new AnalysisBillingContext(
                billingUserId: (string) $user->id,
                billingSource: BillingSource::DailyFree,
            ),
            fn (): array => (app(HttpAiMetadataService::class))->generate('data:image/jpeg;base64,xx'),
        );

        $this->assertSentModels(['free.vision', 'free.thinking']);
        $events = AiUsageEvent::query()->get()->keyBy('stage');
        $this->assertSame('free.vision', $events->get('vision')?->alias_model);
        $this->assertSame('free.thinking', $events->get('thinking')?->alias_model);
        $this->assertSame('daily_free', $events->get('vision')?->billing_source);
        $this->assertSame('daily_free', $events->get('thinking')?->billing_source);
    }

    public function test_empty_free_overrides_inherit_paid_models(): void
    {
        config([
            'microstock.ai.vision.free_model' => '',
            'microstock.ai.thinking.free_model' => '   ',
        ]);
        $user = User::factory()->create();
        Http::fake([self::ENDPOINT => Http::sequence()
            ->push($this->response('Vision title', 'resolved.vision'), 200)
            ->push($this->response('Curated title', 'resolved.thinking'), 200),
        ]);

        AnalysisBillingContext::run(
            new AnalysisBillingContext(
                billingUserId: (string) $user->id,
                billingSource: BillingSource::DailyFree,
            ),
            fn (): array => (app(HttpAiMetadataService::class))->generate('data:image/jpeg;base64,xx'),
        );

        $this->assertSentModels(['paid.vision', 'paid.thinking']);
    }

    /** @return array<string, mixed> */
    private function response(string $title, string $resolvedModel): array
    {
        return [
            'id' => 'request-'.$resolvedModel,
            'model' => $resolvedModel,
            'usage' => [
                'prompt_tokens' => 100,
                'completion_tokens' => 20,
                'total_tokens' => 120,
            ],
            'choices' => [[
                'message' => [
                    'role' => 'assistant',
                    'content' => json_encode([
                        'title' => $title,
                        'description' => 'Description.',
                        'category' => 'Objects',
                        'keywords' => ['one', 'two'],
                    ]),
                ],
                'finish_reason' => 'stop',
            ]],
        ];
    }

    /** @param list<string> $expected */
    private function assertSentModels(array $expected): void
    {
        $models = Http::recorded()
            ->map(fn (array $pair): mixed => $pair[0] instanceof Request ? $pair[0]->data()['model'] ?? null : null)
            ->all();

        $this->assertSame($expected, $models);
    }
}
