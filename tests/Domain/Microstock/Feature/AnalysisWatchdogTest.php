<?php

namespace Tests\Domain\Microstock\Feature;

use App\Domain\FileHosting\Enums\CollectionStatus;
use App\Domain\FileHosting\Models\Folder;
use App\Domain\FileHosting\Models\HostedFile;
use App\Domain\Identity\Models\User;
use App\Domain\Microstock\Enums\MetadataStatus;
use App\Domain\Microstock\Jobs\AnalysisWatchdog;
use App\Domain\Microstock\Models\AssetMetadata;
use App\Domain\Microstock\Services\CollectionService;
use App\Domain\Microstock\Services\MicrostockService;
use App\Domain\Service\Enums\EntitlementStatus;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServiceEntitlement;
use App\Domain\Service\Support\MicrostockCatalog;
use Database\Seeders\FileHostingRoleSeeder;
use Database\Seeders\MicrostockRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class AnalysisWatchdogTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->seed(FileHostingRoleSeeder::class);
        $this->seed(MicrostockRoleSeeder::class);

        config([
            'file-hosting.disk' => 'local',
            'file-hosting.default_quota_bytes' => 50 * 1024 * 1024,
            'activitylog.enabled' => false,
            'microstock.csv_disk' => 'local',
            'microstock.ai.phase.normal_after' => 600,
            'microstock.ai.phase.grace_after' => 1500,
        ]);

        $this->owner = $this->userWithRole('User', 'owner@example.com');
        $this->grantMicrostockFree($this->owner);
    }

    public function test_pending_metadata_past_grace_is_marked_failed(): void
    {
        Bus::fake(); // suppress self re-dispatch for this assertion
        [$folder, $file] = $this->collectionWithFile();

        $expired = AssetMetadata::query()->create([
            'file_id' => $file->id,
            'user_id' => $this->owner->id,
            'status' => MetadataStatus::Pending,
        ]);
        AssetMetadata::query()->where('id', $expired->id)->update([
            'created_at' => now()->subSeconds(2000), // past grace (1500)
        ]);

        (new AnalysisWatchdog)->handle();

        $this->assertSame(MetadataStatus::Failed, $expired->refresh()->status);
        $this->assertStringContainsString('grace period', $expired->error);
    }

    public function test_recent_pending_metadata_is_left_untouched(): void
    {
        Bus::fake();
        [$folder, $file] = $this->collectionWithFile();

        $fresh = AssetMetadata::query()->create([
            'file_id' => $file->id,
            'user_id' => $this->owner->id,
            'status' => MetadataStatus::Pending,
            'created_at' => now()->subSeconds(100), // well under grace
        ]);

        (new AnalysisWatchdog)->handle();

        $this->assertSame(MetadataStatus::Pending, $fresh->refresh()->status);
    }

    public function test_processing_folder_with_all_terminal_metadata_is_failed(): void
    {
        Bus::fake();
        [$folder, $file] = $this->collectionWithFile();
        $folder->markCollectionStatus(CollectionStatus::Processing);

        AssetMetadata::query()->create([
            'file_id' => $file->id,
            'user_id' => $this->owner->id,
            'status' => MetadataStatus::Failed, // no pending, no generated -> folder failed
            'created_at' => now()->subSeconds(2000),
        ]);

        (new AnalysisWatchdog)->handle();

        $this->assertSame(CollectionStatus::Failed, $folder->refresh()->collection_status);
    }

    public function test_processing_folder_with_some_generated_goes_to_review(): void
    {
        Bus::fake();
        [$folder, $file] = $this->collectionWithFile();
        $folder->markCollectionStatus(CollectionStatus::Processing);

        AssetMetadata::query()->create([
            'file_id' => $file->id,
            'user_id' => $this->owner->id,
            'status' => MetadataStatus::Failed,
            'created_at' => now()->subSeconds(2000),
        ]);
        $file2 = $this->makeFile($this->owner, $folder->workspace_id, $folder->id, 'b.jpg');
        AssetMetadata::query()->create([
            'file_id' => $file2->id,
            'user_id' => $this->owner->id,
            'status' => MetadataStatus::Generated,
            'created_at' => now()->subSeconds(100),
        ]);

        (new AnalysisWatchdog)->handle();

        $this->assertSame(CollectionStatus::Review, $folder->refresh()->collection_status);
    }

    public function test_zombie_pending_row_reaped_by_same_grace_rule(): void
    {
        Bus::fake();
        [$folder, $file] = $this->collectionWithFile();
        $folder->markCollectionStatus(CollectionStatus::Processing);

        $zombie = AssetMetadata::query()->create([
            'file_id' => $file->id,
            'user_id' => $this->owner->id,
            'status' => MetadataStatus::Pending,
        ]);
        AssetMetadata::query()->where('id', $zombie->id)->update([
            'created_at' => now()->subSeconds(2000),
        ]);

        (new AnalysisWatchdog)->handle();

        $this->assertSame(MetadataStatus::Failed, $zombie->refresh()->status);
    }

    public function test_watchdog_self_dispatches_only_while_processing_exists(): void
    {
        Bus::fake();

        // No processing folder -> no self dispatch.
        (new AnalysisWatchdog)->handle();
        Bus::assertNotDispatched(AnalysisWatchdog::class);

        // With a processing folder -> self dispatches.
        Bus::clearResolvedInstances();
        Bus::fake();
        [$folder, $file] = $this->collectionWithFile();
        $folder->markCollectionStatus(CollectionStatus::Processing);
        AssetMetadata::query()->create([
            'file_id' => $file->id,
            'user_id' => $this->owner->id,
            'status' => MetadataStatus::Pending,
            'created_at' => now()->subSeconds(100),
        ]);

        (new AnalysisWatchdog)->handle();
        Bus::assertDispatched(AnalysisWatchdog::class);
    }

    public function test_collection_status_phase_is_analyzing_when_fresh(): void
    {
        [$folder, $file] = $this->collectionWithFile();
        $folder->update(['collection_locked_at' => now()]);
        $folder->markCollectionStatus(CollectionStatus::Processing);

        $status = app(CollectionService::class)->status($this->owner, $folder->id);

        $this->assertSame('analyzing', $status['phase']);
    }

    public function test_collection_status_phase_is_degraded_when_past_normal(): void
    {
        [$folder, $file] = $this->collectionWithFile();
        $folder->update(['collection_locked_at' => now()->subSeconds(900)]);
        $folder->markCollectionStatus(CollectionStatus::Processing);

        $status = app(CollectionService::class)->status($this->owner, $folder->id);

        $this->assertSame('degraded', $status['phase']);
    }

    public function test_collection_status_phase_echoes_status_when_not_processing(): void
    {
        [$folder, $file] = $this->collectionWithFile();
        $folder->markCollectionStatus(CollectionStatus::Review);

        $status = app(CollectionService::class)->status($this->owner, $folder->id);

        $this->assertSame('review', $status['phase']);
    }

    /**
     * @return array{0: Folder, 1: HostedFile}
     */
    private function collectionWithFile(): array
    {
        $ws = app(MicrostockService::class)->workspaceFor($this->owner);
        $folder = Folder::query()->create([
            'user_id' => $this->owner->id,
            'workspace_id' => $ws->id,
            'parent_id' => null,
            'name' => 'Collection',
            'visibility' => 'private',
        ]);
        $file = $this->makeFile($this->owner, $ws->id, $folder->id, 'a.jpg');

        return [$folder, $file];
    }

    private function makeFile(User $owner, string $workspaceId, ?string $folderId, string $name): HostedFile
    {
        return HostedFile::query()->create([
            'user_id' => $owner->id,
            'workspace_id' => $workspaceId,
            'folder_id' => $folderId,
            'name' => $name,
            'mime' => 'image/jpeg',
            'size' => 1024,
            'disk' => 'local',
            'path' => strtolower(Str::random(40)),
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
    }

    private function userWithRole(string $role, string $email): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'name' => $email,
            'password' => bcrypt('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }
}
