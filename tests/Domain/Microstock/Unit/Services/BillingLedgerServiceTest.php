<?php

namespace Tests\Domain\Microstock\Unit\Services;

use App\Domain\Archivd\Models\Folder;
use App\Domain\Archivd\Models\HostedFile;
use App\Domain\Identity\Models\User;
use App\Domain\Microstock\Enums\AiCreditTransactionType;
use App\Domain\Microstock\Enums\MetadataStatus;
use App\Domain\Microstock\Models\AiUsageEvent;
use App\Domain\Microstock\Models\AssetMetadata;
use App\Domain\Microstock\Services\AiCreditService;
use App\Domain\Microstock\Services\BillingLedgerService;
use App\Domain\Microstock\Services\MicrostockService;
use App\Domain\Microstock\Support\MicrostockCatalog;
use App\Domain\Service\Enums\EntitlementStatus;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServiceEntitlement;
use Database\Seeders\ArchivdRoleSeeder;
use Database\Seeders\MicrostockRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\GrantsMicrostockAiCredits;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class BillingLedgerServiceTest extends TestCase
{
    use GrantsMicrostockAiCredits;
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private User $user;

    private BillingLedgerService $ledger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->seed(ArchivdRoleSeeder::class);
        $this->seed(MicrostockRoleSeeder::class);

        $this->user = User::factory()->create();
        $this->grantMicrostockFree($this->user);
        $this->ledger = app(BillingLedgerService::class);
    }

    public function test_ledger_returns_newest_first_with_enriched_descriptions(): void
    {
        $folder = $this->collection();
        $file = HostedFile::query()->create([
            'user_id' => $this->user->id,
            'workspace_id' => $folder->workspace_id,
            'folder_id' => $folder->id,
            'name' => 'sunset.jpg',
            'mime' => 'image/jpeg',
            'size' => 1024,
            'disk' => 'local',
            'path' => strtolower(Str::random(40)),
            'visibility' => 'private',
        ]);

        $metadata = AssetMetadata::query()->create([
            'file_id' => $file->id,
            'user_id' => $this->user->id,
            'status' => MetadataStatus::Pending,
        ]);

        app(AiCreditService::class)->addCredit(
            (string) $this->user->id,
            5_000_000,
            AiCreditTransactionType::Adjustment,
            'test',
            null,
        );

        app(AiCreditService::class)->debit(
            (string) $this->user->id,
            150_000,
            'asset_metadata',
            (string) $metadata->id,
        );

        $paginator = $this->ledger->ledgerPaginated((string) $this->user->id, 20);
        $items = $paginator->getCollection();

        $this->assertGreaterThanOrEqual(2, $items->count());
        $this->assertSame(AiCreditTransactionType::AnalysisDebit, $items->first()->type);
        $this->assertStringContainsString('sunset.jpg', (string) $items->first()->ledger_description);
        $this->assertStringContainsString($folder->name, (string) $items->first()->ledger_description);
    }

    public function test_usage_events_excludes_voided_rows(): void
    {
        $folder = $this->collection();
        $file = HostedFile::query()->create([
            'user_id' => $this->user->id,
            'workspace_id' => $folder->workspace_id,
            'folder_id' => $folder->id,
            'name' => 'photo.jpg',
            'mime' => 'image/jpeg',
            'size' => 1024,
            'disk' => 'local',
            'path' => strtolower(Str::random(40)),
            'visibility' => 'private',
        ]);

        AiUsageEvent::query()->create([
            'user_id' => $this->user->id,
            'file_id' => $file->id,
            'folder_id' => $folder->id,
            'stage' => 'vision',
            'alias_model' => 'gpt-5.6',
            'resolved_model' => 'gpt-5.6',
            'prompt_tokens' => 10,
            'completion_tokens' => 20,
            'total_tokens' => 30,
            'provider_cost_micro_usd' => 1000,
            'billable_micro_usd' => 1350,
            'billing_source' => 'prepaid',
            'billed_at' => now(),
            'created_at' => now(),
        ]);

        AiUsageEvent::query()->create([
            'user_id' => $this->user->id,
            'file_id' => $file->id,
            'folder_id' => $folder->id,
            'stage' => 'thinking',
            'alias_model' => 'gpt-5.6',
            'resolved_model' => 'gpt-5.6',
            'prompt_tokens' => 5,
            'completion_tokens' => 5,
            'total_tokens' => 10,
            'provider_cost_micro_usd' => 500,
            'billable_micro_usd' => 0,
            'billing_source' => 'daily_free',
            'billed_at' => now(),
            'created_at' => now()->subMinute(),
        ])->forceFill(['voided_at' => now()])->save();

        $paginator = $this->ledger->usageEventsPaginated((string) $this->user->id, 20);

        $this->assertCount(1, $paginator->getCollection());
        $this->assertSame('vision', $paginator->getCollection()->first()->stage);
    }

    private function collection(): Folder
    {
        $ws = app(MicrostockService::class)->createWorkspace($this->user);

        return Folder::query()->create([
            'user_id' => $this->user->id,
            'workspace_id' => $ws->id,
            'name' => 'Summer set',
            'visibility' => 'private',
        ]);
    }

    private function grantMicrostockFree(User $user): void
    {
        $service = Service::query()->where('code', MicrostockCatalog::CODE_PLAN_FREE)->first()
            ?? Service::factory()->microstockPlanFree()->create();

        ServiceEntitlement::query()->updateOrCreate(
            ['user_id' => $user->id, 'service_id' => $service->id],
            [
                'status' => EntitlementStatus::Active,
                'quantity' => 1,
                'starts_at' => now(),
                'ends_at' => null,
            ],
        );

        $this->grantMicrostockAiCredits($user);
    }
}
