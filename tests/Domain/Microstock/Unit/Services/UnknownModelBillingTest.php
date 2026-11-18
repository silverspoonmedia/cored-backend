<?php

namespace Tests\Domain\Microstock\Unit\Services;

use App\Domain\Inference\Enums\InferenceRateSource;
use App\Domain\Inference\Models\AiModelCard;
use App\Domain\Inference\Models\AiModelRateSnapshot;
use App\Domain\Inference\Models\InferenceProvider;
use App\Domain\Inference\Models\ProviderModelRate;
use App\Domain\Inference\Services\InferenceCatalogService;
use App\Domain\Microstock\Enums\AiCreditTransactionType;
use App\Domain\Microstock\Enums\BillingSource;
use App\Domain\Microstock\Models\AiUsageEvent;
use App\Domain\Microstock\Services\AiBillingService;
use App\Domain\Microstock\Services\AiCreditService;
use App\Domain\Microstock\Services\UnknownModelBillingConsolidationService;
use App\Domain\Microstock\Support\AnalysisBillingContext;
use App\Domain\Notification\Models\ActivityNotification;
use App\Domain\Notification\Notifications\NotificationType;
use App\Domain\Inference\Database\Seeders\InferenceCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class UnknownModelBillingTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seed(InferenceCatalogSeeder::class);
    }

    public function test_record_usage_notifies_once_for_unregistered_model(): void
    {
        config([
            'inference.unknown_model_rates' => [
                'input_per_million_usd' => 10.0,
                'output_per_million_usd' => 40.0,
            ],
        ]);

        Cache::flush();

        $user = $this->userWithRole('User', 'notify@example.com');
        $billing = app(AiBillingService::class);

        AnalysisBillingContext::run(
            new AnalysisBillingContext(
                billingUserId: (string) $user->id,
                billingSource: BillingSource::Prepaid,
            ),
            function () use ($billing): void {
                $billing->recordUsage('vision', 'alias', ['prompt_tokens' => 1000, 'completion_tokens' => 500], 'unregistered-notify-model');
                $billing->recordUsage('vision', 'alias', ['prompt_tokens' => 1000, 'completion_tokens' => 500], 'unregistered-notify-model');
            },
        );

        $this->assertSame(
            1,
            ActivityNotification::query()
                ->where('type', NotificationType::InferenceUnregisteredModel->value)
                ->count(),
        );

        $card = AiModelCard::query()->where('slug', 'unregistered-notify-model')->first();
        $this->assertNotNull($card);
        $this->assertTrue($card->needs_rate_classification);
    }

    public function test_consolidation_refunds_when_curated_rate_is_cheaper(): void
    {
        $user = $this->userWithRole('User', 'refund@example.com');
        $provider = InferenceProvider::query()->where('slug', 'openwebui-9router')->firstOrFail();

        $card = AiModelCard::query()->create([
            'slug' => 'consolidate-cheaper',
            'display_name' => 'consolidate-cheaper',
            'is_active' => true,
            'needs_rate_classification' => true,
        ]);

        $snapshot = AiModelRateSnapshot::query()->create([
            'model_card_id' => $card->id,
            'model_slug' => $card->slug,
            'inference_provider_id' => $provider->id,
            'input_per_million_usd' => 15.0,
            'output_per_million_usd' => 60.0,
            'rate_source' => InferenceRateSource::UnknownProvisional->value,
            'captured_at' => now(),
        ]);

        AiUsageEvent::query()->create([
            'user_id' => $user->id,
            'asset_metadata_id' => null,
            'billing_source' => BillingSource::Prepaid->value,
            'stage' => 'vision',
            'alias_model' => 'alias',
            'resolved_model' => $card->slug,
            'ai_model_rate_snapshot_id' => $snapshot->id,
            'inference_provider_id' => $provider->id,
            'prompt_tokens' => 1_000_000,
            'completion_tokens' => 0,
            'total_tokens' => 1_000_000,
            'provider_cost_micro_usd' => 15_000_000,
            'billable_micro_usd' => 20_250_000,
            'billed_at' => now(),
            'created_at' => now(),
        ]);

        app(AiCreditService::class)->addCredit(
            (string) $user->id,
            50_000_000,
            AiCreditTransactionType::Purchase,
        );
        app(AiCreditService::class)->debit((string) $user->id, 20_250_000, 'asset_metadata', 'meta-refund');

        ProviderModelRate::query()->create([
            'inference_provider_id' => $provider->id,
            'model_card_id' => $card->id,
            'input_per_million_usd' => 1.0,
            'output_per_million_usd' => 2.0,
        ]);

        app(UnknownModelBillingConsolidationService::class)->consolidate((string) $provider->id, (string) $card->id);

        $this->assertSame(48_650_000, app(AiCreditService::class)->balance((string) $user->id));
        $this->assertDatabaseHas('microstock_ai_usage_events', [
            'resolved_model' => $card->slug,
            'billable_micro_usd' => 1_350_000,
            'consolidation_adjustment_micro_usd' => -18_900_000,
        ]);
    }

    public function test_consolidation_debits_negative_balance_when_curated_rate_is_more_expensive(): void
    {
        $user = $this->userWithRole('User', 'debit@example.com');
        $provider = InferenceProvider::query()->where('slug', 'openwebui-9router')->firstOrFail();

        $card = AiModelCard::query()->create([
            'slug' => 'consolidate-dearer',
            'display_name' => 'consolidate-dearer',
            'is_active' => true,
            'needs_rate_classification' => true,
        ]);

        $snapshot = AiModelRateSnapshot::query()->create([
            'model_card_id' => $card->id,
            'model_slug' => $card->slug,
            'inference_provider_id' => $provider->id,
            'input_per_million_usd' => 1.0,
            'output_per_million_usd' => 2.0,
            'rate_source' => InferenceRateSource::UnknownProvisional->value,
            'captured_at' => now(),
        ]);

        AiUsageEvent::query()->create([
            'user_id' => $user->id,
            'billing_source' => BillingSource::Prepaid->value,
            'stage' => 'vision',
            'alias_model' => 'alias',
            'resolved_model' => $card->slug,
            'ai_model_rate_snapshot_id' => $snapshot->id,
            'inference_provider_id' => $provider->id,
            'prompt_tokens' => 1_000_000,
            'completion_tokens' => 0,
            'total_tokens' => 1_000_000,
            'provider_cost_micro_usd' => 1_000_000,
            'billable_micro_usd' => 1_350_000,
            'billed_at' => now(),
            'created_at' => now(),
        ]);

        app(AiCreditService::class)->addCredit((string) $user->id, 1_350_000, AiCreditTransactionType::Purchase);
        app(AiCreditService::class)->debit((string) $user->id, 1_350_000, 'asset_metadata', 'meta-debit');

        ProviderModelRate::query()->create([
            'inference_provider_id' => $provider->id,
            'model_card_id' => $card->id,
            'input_per_million_usd' => 15.0,
            'output_per_million_usd' => 60.0,
        ]);

        app(UnknownModelBillingConsolidationService::class)->consolidate((string) $provider->id, (string) $card->id);

        $this->assertSame(-18_900_000, app(AiCreditService::class)->balance((string) $user->id));
    }

    public function test_sync_provider_rates_clears_classification_flag_and_consolidates(): void
    {
        $provider = InferenceProvider::query()->where('slug', 'openwebui-9router')->firstOrFail();

        $card = AiModelCard::query()->create([
            'slug' => 'sync-flag-clear',
            'display_name' => 'sync-flag-clear',
            'is_active' => true,
            'needs_rate_classification' => true,
        ]);

        app(InferenceCatalogService::class)->syncProviderRates((string) $provider->id, [
            [
                'model_card_id' => $card->id,
                'input_per_million_usd' => 2.0,
                'output_per_million_usd' => 4.0,
            ],
        ]);

        $card->refresh();
        $this->assertFalse($card->needs_rate_classification);
    }

    public function test_plan_analyze_blocks_when_balance_negative(): void
    {
        $user = $this->userWithRole('User', 'negative@example.com');
        app(AiCreditService::class)->addCredit((string) $user->id, 100_000, AiCreditTransactionType::Purchase);
        app(AiCreditService::class)->debit((string) $user->id, 200_000, 'asset_metadata', 'meta-neg', AiCreditTransactionType::Adjustment);

        $this->expectException(\App\Domain\Microstock\Exceptions\MicrostockException::class);

        app(AiBillingService::class)->planAnalyze($user, 1, 'paid');
    }

    private function userWithRole(string $role, string $email): \App\Domain\Identity\Models\User
    {
        $user = \App\Domain\Identity\Models\User::factory()->create([
            'email' => $email,
            'name' => $email,
        ]);
        $user->assignRole($role);

        return $user;
    }
}
