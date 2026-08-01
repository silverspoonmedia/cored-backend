<?php

namespace Tests\Domain\Microstock\Unit\Services;

use App\Domain\FileHosting\Models\HostedFile;
use App\Domain\Identity\Models\User;
use App\Domain\Microstock\Enums\AnalysisStageStatus;
use App\Domain\Microstock\Enums\MetadataStatus;
use App\Domain\Microstock\Jobs\AnalyzeAssetVision;
use App\Domain\Microstock\Models\AiUsageEvent;
use App\Domain\Microstock\Models\AssetMetadata;
use App\Domain\Microstock\Services\AiBillingService;
use App\Domain\Microstock\Services\AiCreditService;
use App\Domain\Microstock\Services\AnalysisStageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\Concerns\GrantsMicrostockAiCredits;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class MicrostockBillingHardeningTest extends TestCase
{
    use GrantsMicrostockAiCredits;
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
    }

    public function test_compose_result_settles_with_negative_balance_when_prepaid_insufficient(): void
    {
        $user = $this->userWithRole('User', 'settle-fail@example.com');
        $this->grantMicrostockAiCredits($user, 100);
        [$metadata] = $this->metadataWithFile($user, 'fail.jpg', billingUserId: (string) $user->id);

        AiUsageEvent::query()->create([
            'user_id' => $user->id,
            'asset_metadata_id' => $metadata->id,
            'analysis_run_id' => $metadata->analysis_run_id,
            'stage' => 'vision',
            'alias_model' => 'internal.vision',
            'resolved_model' => 'claude-sonnet-5',
            'prompt_tokens' => 1000,
            'completion_tokens' => 500,
            'total_tokens' => 1500,
            'provider_cost_micro_usd' => 10_000,
            'billable_micro_usd' => 13_500,
        ]);

        $curated = [
            'title' => 'Title',
            'keywords' => ['a'],
            'description' => 'desc',
            'category' => 'People',
            'raw' => ['ok' => true],
            'stage_metrics' => [],
        ];

        app(AnalysisStageService::class)->composeResultFromThinking($metadata->refresh(), $curated);

        $metadata->refresh();
        $this->assertSame(MetadataStatus::Generated, $metadata->status);
        $this->assertIsArray($metadata->ai_billing);
        $this->assertNotNull(AiUsageEvent::query()->first()?->billed_at);
        $this->assertLessThan(0, app(AiCreditService::class)->balance((string) $user->id));
    }

    public function test_partial_vision_settle_on_thinking_failure(): void
    {
        $user = $this->userWithRole('User', 'partial@example.com');
        $this->grantMicrostockAiCredits($user, 5_000_000);
        [$metadata] = $this->metadataWithFile($user, 'partial.jpg', billingUserId: (string) $user->id);

        $metadata->forceFill([
            'ai_vision' => [
                'status' => AnalysisStageStatus::Ok->value,
                'output' => ['title' => 't', 'keywords' => [], 'description' => '', 'category' => '', 'raw' => null],
            ],
        ])->save();

        AiUsageEvent::query()->create([
            'user_id' => $user->id,
            'asset_metadata_id' => $metadata->id,
            'analysis_run_id' => $metadata->analysis_run_id,
            'stage' => 'vision',
            'alias_model' => 'internal.vision',
            'resolved_model' => 'claude-sonnet-5',
            'prompt_tokens' => 100,
            'completion_tokens' => 50,
            'total_tokens' => 150,
            'provider_cost_micro_usd' => 1000,
            'billable_micro_usd' => 1350,
        ]);

        app(AnalysisStageService::class)->markThinkingFailed($metadata->refresh(), 'nope');

        $metadata->refresh();
        $this->assertSame(MetadataStatus::Failed, $metadata->status);
        $this->assertIsArray($metadata->ai_billing);
        $this->assertTrue($metadata->ai_billing['partial']);
        $this->assertSame(1350, $metadata->ai_billing['billable_micro_usd']);
        $this->assertSame(4_998_650, app(AiCreditService::class)->balance((string) $user->id));
    }

    public function test_settle_success_after_partial_includes_thinking_events(): void
    {
        $user = $this->userWithRole('User', 'partial-then-full@example.com');
        $this->grantMicrostockAiCredits($user, 5_000_000);
        [$metadata] = $this->metadataWithFile($user, 'both.jpg', billingUserId: (string) $user->id);

        $metadata->forceFill([
            'ai_vision' => [
                'status' => AnalysisStageStatus::Ok->value,
                'output' => ['title' => 't', 'keywords' => [], 'description' => '', 'category' => '', 'raw' => null],
                'stage_metrics' => [],
            ],
        ])->save();

        AiUsageEvent::query()->create([
            'user_id' => $user->id,
            'asset_metadata_id' => $metadata->id,
            'analysis_run_id' => $metadata->analysis_run_id,
            'stage' => 'vision',
            'alias_model' => 'internal.vision',
            'resolved_model' => 'claude-sonnet-5',
            'prompt_tokens' => 100,
            'completion_tokens' => 50,
            'total_tokens' => 150,
            'provider_cost_micro_usd' => 1000,
            'billable_micro_usd' => 1350,
        ]);

        app(AiBillingService::class)->settlePartialVisionUsage($metadata->refresh());

        AiUsageEvent::query()->create([
            'user_id' => $user->id,
            'asset_metadata_id' => $metadata->id,
            'analysis_run_id' => $metadata->analysis_run_id,
            'stage' => 'thinking',
            'alias_model' => 'internal.thinking',
            'resolved_model' => 'gpt-5.6-luna',
            'prompt_tokens' => 200,
            'completion_tokens' => 80,
            'total_tokens' => 280,
            'provider_cost_micro_usd' => 680,
            'billable_micro_usd' => 918,
        ]);

        $curated = [
            'title' => 'Title',
            'keywords' => ['a'],
            'description' => 'desc',
            'category' => 'People',
            'raw' => ['ok' => true],
            'stage_metrics' => [],
        ];

        app(AnalysisStageService::class)->composeResultFromThinking($metadata->refresh(), $curated);

        $metadata->refresh();
        $this->assertSame(MetadataStatus::Generated, $metadata->status);
        $this->assertFalse($metadata->ai_billing['partial']);
        $this->assertSame(2268, $metadata->ai_billing['billable_micro_usd']);
        $this->assertSame(4_997_732, app(AiCreditService::class)->balance((string) $user->id));
    }

    public function test_settle_filters_by_analysis_run_id(): void
    {
        $user = $this->userWithRole('User', 'run-filter@example.com');
        $this->grantMicrostockAiCredits($user, 5_000_000);
        [$metadata] = $this->metadataWithFile($user, 'run.jpg', billingUserId: (string) $user->id, analysisRunId: '11111111-1111-4111-8111-111111111111');

        AiUsageEvent::query()->create([
            'user_id' => $user->id,
            'asset_metadata_id' => $metadata->id,
            'analysis_run_id' => '00000000-0000-4000-8000-000000000000',
            'stage' => 'vision',
            'alias_model' => 'internal.vision',
            'resolved_model' => 'claude-sonnet-5',
            'prompt_tokens' => 100,
            'completion_tokens' => 50,
            'total_tokens' => 150,
            'provider_cost_micro_usd' => 1000,
            'billable_micro_usd' => 9999,
        ]);

        AiUsageEvent::query()->create([
            'user_id' => $user->id,
            'asset_metadata_id' => $metadata->id,
            'analysis_run_id' => $metadata->analysis_run_id,
            'stage' => 'vision',
            'alias_model' => 'internal.vision',
            'resolved_model' => 'claude-sonnet-5',
            'prompt_tokens' => 100,
            'completion_tokens' => 50,
            'total_tokens' => 150,
            'provider_cost_micro_usd' => 1000,
            'billable_micro_usd' => 1350,
        ]);

        $metadata->forceFill(['status' => MetadataStatus::Generated])->save();
        app(AiBillingService::class)->settleSuccessfulAnalysis($metadata->refresh());

        $metadata->refresh();
        $this->assertSame(1350, $metadata->ai_billing['billable_micro_usd']);
        $this->assertNull(
            AiUsageEvent::query()->where('analysis_run_id', '00000000-0000-4000-8000-000000000000')->first()?->billed_at
        );
    }

    public function test_vision_job_skips_http_when_vision_already_ok(): void
    {
        $user = $this->userWithRole('User', 'vision-idem@example.com');
        [$metadata] = $this->metadataWithFile($user, 'idem.jpg');

        $metadata->forceFill([
            'ai_vision' => [
                'status' => AnalysisStageStatus::Ok->value,
                'output' => ['title' => 't', 'keywords' => [], 'description' => '', 'category' => '', 'raw' => null],
            ],
        ])->save();

        $ai = Mockery::mock(\App\Domain\Microstock\Contracts\AiMetadataService::class);
        $ai->shouldNotReceive('analyzeVision');
        $this->app->instance(\App\Domain\Microstock\Contracts\AiMetadataService::class, $ai);

        $job = new AnalyzeAssetVision((string) $metadata->id, 'hint', null);
        $job->handle(
            $ai,
            app(\App\Domain\Microstock\Services\VisionImageResolver::class),
            app(AnalysisStageService::class),
        );

        $this->assertTrue(true);
    }

    public function test_try_claim_thinking_running_prevents_duplicate_claim(): void
    {
        $user = $this->userWithRole('User', 'claim@example.com');
        [$metadata] = $this->metadataWithFile($user, 'claim.jpg');
        $stages = app(AnalysisStageService::class);

        $this->assertTrue($stages->tryClaimThinkingRunning($metadata));
        $this->assertFalse($stages->tryClaimThinkingRunning($metadata->refresh()));
    }

    /**
     * @return array{0: AssetMetadata, 1: HostedFile}
     */
    private function metadataWithFile(
        User $user,
        string $name,
        ?string $billingUserId = null,
        ?string $analysisRunId = null,
    ): array {
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
            'billing_user_id' => $billingUserId ?? $user->id,
            'analysis_run_id' => $analysisRunId ?? (string) Str::uuid(),
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
