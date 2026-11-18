<?php

namespace Tests\Domain\Microstock\Unit\Services;

use App\Domain\Archivd\Models\HostedFile;
use App\Domain\Identity\Models\User;
use App\Domain\Inference\Database\Seeders\InferenceCatalogSeeder;
use App\Domain\Microstock\Enums\AnalysisStageStatus;
use App\Domain\Microstock\Enums\BillingSource;
use App\Domain\Microstock\Enums\MetadataStatus;
use App\Domain\Microstock\Exceptions\MicrostockException;
use App\Domain\Microstock\Jobs\AnalyzeAssetVision;
use App\Domain\Microstock\Models\AssetMetadata;
use App\Domain\Microstock\Models\DailyFreeUsage;
use App\Domain\Microstock\Services\AiBillingService;
use App\Domain\Microstock\Services\AiCreditService;
use App\Domain\Microstock\Services\AiDailyFreeService;
use App\Domain\Microstock\Support\AnalysisBillingContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Concerns\GrantsMicrostockAiCredits;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class AiDailyFreeServiceTest extends TestCase
{
    use GrantsMicrostockAiCredits;
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seed(InferenceCatalogSeeder::class);
        $this->user = $this->userWithRole('User', 'daily-free@example.com');
        config([
            'microstock.daily_free.enabled' => true,
            'microstock.daily_free.limit_per_user' => 3,
            'microstock.daily_free.timezone' => 'UTC',
        ]);
    }

    public function test_remaining_returns_full_limit_when_no_usage_row(): void
    {
        $this->assertSame(3, app(AiDailyFreeService::class)->remaining((string) $this->user->id));
    }

    public function test_allocate_in_transaction_consumes_daily_first(): void
    {
        $split = app(AiDailyFreeService::class)->allocateInTransaction((string) $this->user->id, 2);

        $this->assertSame(2, $split['daily']);
        $this->assertSame(0, $split['prepaid']);
        $this->assertSame(1, app(AiDailyFreeService::class)->remaining((string) $this->user->id));
    }

    public function test_allocate_splits_daily_and_prepaid(): void
    {
        DailyFreeUsage::query()->create([
            'user_id' => $this->user->id,
            'usage_date' => app(AiDailyFreeService::class)->today()->toDateString(),
            'analyses_used' => 2,
        ]);

        $split = app(AiDailyFreeService::class)->allocateInTransaction((string) $this->user->id, 3);

        $this->assertSame(1, $split['daily']);
        $this->assertSame(2, $split['prepaid']);
    }

    public function test_plan_analyze_allows_daily_only_without_credits(): void
    {
        $plan = app(AiBillingService::class)->planAnalyze($this->user, 2);

        $this->assertSame(2, $plan->dailySlots);
        $this->assertSame(0, $plan->prepaidSlots);
        $this->assertSame(0, app(AiCreditService::class)->balance((string) $this->user->id));
    }

    public function test_plan_analyze_requires_prepaid_for_remainder(): void
    {
        config(['microstock.daily_free.limit_per_user' => 1]);

        $this->expectException(MicrostockException::class);

        app(AiBillingService::class)->planAnalyze($this->user, 2);
    }

    public function test_settle_skips_debit_for_daily_free_metadata(): void
    {
        $this->grantMicrostockAiCredits($this->user, 5_000_000);
        [$metadata] = $this->metadataWithFile($this->user, 'daily.jpg');

        $metadata->forceFill([
            'billing_user_id' => $this->user->id,
            'analysis_run_id' => (string) Str::uuid(),
            'billing_source' => BillingSource::DailyFree->value,
            'ai_vision' => ['status' => AnalysisStageStatus::Ok->value],
        ])->save();

        $context = new AnalysisBillingContext(
            billingUserId: (string) $this->user->id,
            metadataId: (string) $metadata->id,
            fileId: (string) $metadata->file_id,
            analysisRunId: $metadata->analysis_run_id,
            billingSource: BillingSource::DailyFree,
        );

        AnalysisBillingContext::run($context, function () {
            app(AiBillingService::class)->recordUsage('vision', 'internal.vision', [
                'prompt_tokens' => 1000,
                'completion_tokens' => 100,
                'total_tokens' => 1100,
            ], 'gpt-5.4');
        });

        app(AiBillingService::class)->settlePartialVisionUsage($metadata->refresh());

        $this->assertSame(5_000_000, app(AiCreditService::class)->balance((string) $this->user->id));
        $billing = $metadata->refresh()->ai_billing;
        $this->assertSame('daily_free', $billing['source'] ?? null);
        $this->assertSame(0, $billing['billable_micro_usd'] ?? -1);
    }

    public function test_dispatch_for_solo_uses_free_queue_when_daily_source(): void
    {
        Queue::fake();
        [$metadata] = $this->metadataWithFile($this->user, 'solo-free.jpg');
        $metadata->forceFill(['billing_source' => BillingSource::DailyFree->value])->save();

        AnalyzeAssetVision::dispatchForSolo((string) $metadata->id, 'hint', null);

        Queue::assertPushedOn(
            config('microstock.queues.solo_free', 'microstock-solo-free'),
            AnalyzeAssetVision::class,
        );
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
            'keywords' => [],
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
