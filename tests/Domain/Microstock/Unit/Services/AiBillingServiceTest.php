<?php

namespace Tests\Domain\Microstock\Unit\Services;

use App\Domain\FileHosting\Models\HostedFile;
use App\Domain\Identity\Models\User;
use App\Domain\Microstock\Enums\MetadataStatus;
use App\Domain\Microstock\Models\AiUsageEvent;
use App\Domain\Microstock\Models\AssetMetadata;
use App\Domain\Microstock\Services\AiBillingService;
use App\Domain\Microstock\Services\AiCreditService;
use App\Domain\Microstock\Support\AnalysisBillingContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\GrantsMicrostockAiCredits;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class AiBillingServiceTest extends TestCase
{
    use GrantsMicrostockAiCredits;
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
    }

    public function test_costs_for_tokens_applies_markup(): void
    {
        config(['microstock.ai_billing.markup_multiplier' => 1.35]);

        $billing = app(AiBillingService::class);
        $costs = $billing->costsForTokens('grok-grok-4.5-high', 1_000_000, 0);

        $this->assertSame(3_000_000, $costs['provider']);
        $this->assertSame(4_050_000, $costs['billable']);
    }

    public function test_costs_for_gpt_5_4_applies_markup(): void
    {
        config(['microstock.ai_billing.markup_multiplier' => 1.35]);

        $billing = app(AiBillingService::class);
        $costs = $billing->costsForTokens('gpt-5.4', 1_000_000, 0);

        $this->assertSame(2_500_000, $costs['provider']);
        $this->assertSame(3_375_000, $costs['billable']);
    }

    public function test_costs_for_kimi_k3_applies_markup(): void
    {
        config(['microstock.ai_billing.markup_multiplier' => 1.35]);

        $billing = app(AiBillingService::class);
        $costs = $billing->costsForTokens('kimi-k3', 1_000_000, 0);

        $this->assertSame(3_000_000, $costs['provider']);
        $this->assertSame(4_050_000, $costs['billable']);
    }

    public function test_costs_for_gpt_5_6_sol_applies_markup(): void
    {
        config(['microstock.ai_billing.markup_multiplier' => 1.35]);

        $billing = app(AiBillingService::class);
        $costs = $billing->costsForTokens('gpt-5.6-sol', 1_000_000, 0);

        $this->assertSame(5_000_000, $costs['provider']);
        $this->assertSame(6_750_000, $costs['billable']);
    }

    public function test_costs_for_gpt_5_6_terra_applies_markup(): void
    {
        config(['microstock.ai_billing.markup_multiplier' => 1.35]);

        $billing = app(AiBillingService::class);
        $costs = $billing->costsForTokens('gpt-5.6-terra', 1_000_000, 0);

        $this->assertSame(2_500_000, $costs['provider']);
        $this->assertSame(3_375_000, $costs['billable']);
    }

    public function test_costs_for_gpt_5_6_luna_applies_markup(): void
    {
        config(['microstock.ai_billing.markup_multiplier' => 1.35]);

        $billing = app(AiBillingService::class);
        $costs = $billing->costsForTokens('gpt-5.6-luna', 1_000_000, 0);

        $this->assertSame(1_000_000, $costs['provider']);
        $this->assertSame(1_350_000, $costs['billable']);
    }

    public function test_costs_for_claude_opus_5_applies_markup(): void
    {
        config(['microstock.ai_billing.markup_multiplier' => 1.35]);

        $billing = app(AiBillingService::class);
        $costs = $billing->costsForTokens('claude-opus-5', 1_000_000, 0);

        $this->assertSame(5_000_000, $costs['provider']);
        $this->assertSame(6_750_000, $costs['billable']);
    }

    public function test_costs_for_anthropic_claude_opus_5_applies_markup(): void
    {
        config(['microstock.ai_billing.markup_multiplier' => 1.35]);

        $billing = app(AiBillingService::class);
        $costs = $billing->costsForTokens('anthropic/claude-opus-5', 1_000_000, 0);

        $this->assertSame(5_000_000, $costs['provider']);
        $this->assertSame(6_750_000, $costs['billable']);
    }

    public function test_costs_for_claude_sonnet_5_applies_markup(): void
    {
        config(['microstock.ai_billing.markup_multiplier' => 1.35]);

        $billing = app(AiBillingService::class);
        $costs = $billing->costsForTokens('claude-sonnet-5', 1_000_000, 0);

        $this->assertSame(2_000_000, $costs['provider']);
        $this->assertSame(2_700_000, $costs['billable']);
    }

    public function test_costs_for_anthropic_claude_sonnet_5_applies_markup(): void
    {
        config(['microstock.ai_billing.markup_multiplier' => 1.35]);

        $billing = app(AiBillingService::class);
        $costs = $billing->costsForTokens('anthropic/claude-sonnet-5', 1_000_000, 0);

        $this->assertSame(2_000_000, $costs['provider']);
        $this->assertSame(2_700_000, $costs['billable']);
    }

    public function test_estimate_for_assets_uses_config_per_asset(): void
    {
        config([
            'microstock.ai_billing.estimated_billable_micro_usd_per_asset' => 150_000,
            'microstock.ai_billing.estimate_thinking_attempt_factor' => 1.0,
        ]);

        $this->assertSame(450_000, app(AiBillingService::class)->estimateForAssets(3));
    }

    public function test_estimate_for_assets_applies_thinking_factor(): void
    {
        config([
            'microstock.ai_billing.estimated_billable_micro_usd_per_asset' => 100_000,
            'microstock.ai_billing.estimate_thinking_attempt_factor' => 2.0,
        ]);

        $this->assertSame(600_000, app(AiBillingService::class)->estimateForAssets(3));
    }

    public function test_record_usage_splits_batch_tokens_evenly(): void
    {
        $user = $this->userWithRole('User', 'billing@example.com');
        $this->grantMicrostockAiCredits($user);
        [$metaA, $fileA] = $this->metadataWithFile($user, 'a.jpg');
        [$metaB, $fileB] = $this->metadataWithFile($user, 'b.jpg');

        AnalysisBillingContext::run(
            new AnalysisBillingContext(
                billingUserId: (string) $user->id,
                folderId: null,
                batchTargets: [
                    ['metadata_id' => (string) $metaA->id, 'file_id' => (string) $fileA->id],
                    ['metadata_id' => (string) $metaB->id, 'file_id' => (string) $fileB->id],
                ],
            ),
            function (): void {
                app(AiBillingService::class)->recordUsage(
                    'batch',
                    'internal.vision',
                    ['prompt_tokens' => 1000, 'completion_tokens' => 200, 'total_tokens' => 1200],
                    'grok-grok-4.5-high',
                );
            },
        );

        $events = AiUsageEvent::query()->orderBy('asset_metadata_id')->get();
        $this->assertCount(2, $events);
        $this->assertSame(500, $events[0]->prompt_tokens);
        $this->assertSame(100, $events[0]->completion_tokens);
        $this->assertSame(600, $events[0]->total_tokens);
        $this->assertSame(500, $events[1]->prompt_tokens);
    }

    public function test_settle_successful_analysis_debits_balance_and_snapshots_metadata(): void
    {
        $user = $this->userWithRole('User', 'settle@example.com');
        $this->grantMicrostockAiCredits($user, 5_000_000);
        [$metadata] = $this->metadataWithFile($user, 'settle.jpg');
        $metadata->forceFill(['status' => MetadataStatus::Generated])->save();

        AiUsageEvent::query()->create([
            'user_id' => $user->id,
            'asset_metadata_id' => $metadata->id,
            'stage' => 'vision',
            'alias_model' => 'internal.vision',
            'resolved_model' => 'grok-grok-4.5-high',
            'prompt_tokens' => 100,
            'completion_tokens' => 50,
            'total_tokens' => 150,
            'provider_cost_micro_usd' => 1000,
            'billable_micro_usd' => 1350,
        ]);

        app(AiBillingService::class)->settleSuccessfulAnalysis($metadata->refresh());

        $metadata->refresh();
        $this->assertIsArray($metadata->ai_billing);
        $this->assertSame(1350, $metadata->ai_billing['billable_micro_usd']);
        $this->assertSame(4_998_650, app(AiCreditService::class)->balance((string) $user->id));
        $this->assertNotNull(AiUsageEvent::query()->first()?->billed_at);
    }

  /**
     * @return array{0: AssetMetadata, 1: HostedFile}
     */
    private function metadataWithFile(User $user, string $name): array
    {
        $file = HostedFile::query()->create([
            'user_id' => $user->id,
            'workspace_id' => null,
            'folder_id' => null,
            'name' => $name,
            'mime' => 'image/jpeg',
            'size' => 1024,
            'disk' => 'local',
            'path' => strtolower(Str::random(40)),
            'visibility' => 'private',
        ]);

        $metadata = AssetMetadata::query()->create([
            'file_id' => $file->id,
            'user_id' => $user->id,
            'status' => MetadataStatus::Pending,
        ]);

        return [$metadata, $file];
    }

    private function userWithRole(string $role, string $email): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'name' => $email,
        ]);
        $user->assignRole($role);

        return $user;
    }
}
