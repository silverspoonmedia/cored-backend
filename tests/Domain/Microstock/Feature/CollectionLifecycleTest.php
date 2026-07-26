<?php

namespace Tests\Domain\Microstock\Feature;

use App\Domain\FileHosting\Enums\CollectionStatus;
use App\Domain\FileHosting\Exceptions\FileHostingException;
use App\Domain\FileHosting\Models\Folder;
use App\Domain\FileHosting\Models\HostedFile;
use App\Domain\FileHosting\Services\DirectUploadService;
use App\Domain\Identity\Models\User;
use App\Domain\Microstock\Contracts\AiMetadataService;
use App\Domain\Microstock\Enums\MetadataStatus;
use App\Domain\Microstock\Exceptions\MicrostockException;
use App\Domain\Microstock\Jobs\ProcessCollection;
use App\Domain\Microstock\Models\AssetMetadata;
use App\Domain\Microstock\Services\CollectionService;
use App\Domain\Microstock\Services\CsvExportService;
use App\Domain\Microstock\Services\MicrostockService;
use App\Domain\Microstock\Support\Prompts;
use App\Domain\Service\Enums\EntitlementStatus;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServiceEntitlement;
use App\Domain\Service\Support\MicrostockCatalog;
use Database\Seeders\FileHostingRoleSeeder;
use Database\Seeders\MicrostockRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class CollectionLifecycleTest extends TestCase
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
        ]);

        $this->owner = $this->userWithRole('User', 'owner@example.com');
        $this->grantMicrostockFree($this->owner);
        $this->grantPaidMain($this->owner); // collaborative upload gate requires a paid plan
    }

    // ---- Upload gating (root blocked / locked blocked) ----

    public function test_root_upload_blocked_in_microstock_workspace(): void
    {
        $ws = app(MicrostockService::class)->workspaceFor($this->owner);

        try {
            app(DirectUploadService::class)->initiate(
                $this->owner,
                'asset.jpg',
                'image/jpeg',
                512, // small -> single strategy, no S3 engine call
                null, // root: must be rejected
                null,
                'local',
                $ws->id,
            );
            $this->fail('Root upload in a microstock workspace must be rejected');
        } catch (FileHostingException $e) {
            $this->assertSame(422, $e->statusCode());
            $this->assertStringContainsString(
                __('web/file-hosting.microstock.error_requires_folder'),
                $e->getMessage(),
            );
        }
    }

    public function test_upload_inside_locked_collection_blocked(): void
    {
        $ws = app(MicrostockService::class)->workspaceFor($this->owner);
        $folder = $this->makeFolder($this->owner, $ws->id);

        app(CollectionService::class)->lock($this->owner, $folder->id, 'a set of kitchen photos');

        // Gating runs before any storage signing, so the real service throws here.
        try {
            app(DirectUploadService::class)->initiate(
                $this->owner,
                'asset2.jpg',
                'image/jpeg',
                512,
                $folder->id,
                null,
                'local',
                $ws->id,
            );
            $this->fail('Upload into a locked collection must be rejected');
        } catch (FileHostingException $e) {
            $this->assertSame(422, $e->statusCode());
            $this->assertStringContainsString(
                __('web/file-hosting.microstock.error_collection_locked'),
                $e->getMessage(),
            );
        }
    }

    // ---- Lock lifecycle ----

    public function test_lock_requires_hint_and_transitions_status(): void
    {
        $ws = app(MicrostockService::class)->workspaceFor($this->owner);
        $folder = $this->makeFolder($this->owner, $ws->id);

        try {
            app(CollectionService::class)->lock($this->owner, $folder->id, '   ');
            $this->fail('Empty hint must be rejected');
        } catch (MicrostockException $e) {
            $this->assertSame(422, $e->statusCode());
        }

        $locked = app(CollectionService::class)->lock($this->owner, $folder->id, 'eco kitchen flat-lays');
        $this->assertSame(CollectionStatus::Locked, $locked->collection_status);
        $this->assertSame('eco kitchen flat-lays', $locked->collection_hint);
        $this->assertNotNull($locked->collection_locked_at);
        $this->assertTrue($locked->isCollectionLocked());

        // Re-lock while locked is rejected.
        try {
            app(CollectionService::class)->lock($this->owner, $folder->id, 'again');
            $this->fail('Re-locking a locked collection must be rejected');
        } catch (MicrostockException $e) {
            $this->assertSame(422, $e->statusCode());
        }
    }

    public function test_lock_rejects_non_microstock_folder(): void
    {
        // Personal (no workspace) folder is not a microstock collection.
        $folder = Folder::query()->create([
            'user_id' => $this->owner->id,
            'workspace_id' => null,
            'parent_id' => null,
            'name' => 'Personal',
            'visibility' => 'private',
        ]);

        try {
            app(CollectionService::class)->lock($this->owner, $folder->id, 'hint');
            $this->fail('Non-microstock folder must not be lockable as a collection');
        } catch (MicrostockException $e) {
            $this->assertSame(404, $e->statusCode());
        }
    }

    // ---- Analyze / orchestration ----

    public function test_analyze_requires_lock_and_files_then_dispatches(): void
    {
        Bus::fake();

        $ws = app(MicrostockService::class)->workspaceFor($this->owner);
        $folder = $this->makeFolder($this->owner, $ws->id);
        $this->makeFile($this->owner, $ws->id, $folder->id, 'a.jpg');

        // Not locked yet.
        try {
            app(CollectionService::class)->analyze($this->owner, $folder->id);
            $this->fail('Analyze before lock must be rejected');
        } catch (MicrostockException $e) {
            $this->assertSame(422, $e->statusCode());
        }

        app(CollectionService::class)->lock($this->owner, $folder->id, 'hint');
        $updated = app(CollectionService::class)->analyze($this->owner, $folder->id);

        $this->assertSame(CollectionStatus::Processing, $updated->collection_status);
        Bus::assertDispatched(ProcessCollection::class);
    }

    public function test_analyze_empty_collection_rejected(): void
    {
        $ws = app(MicrostockService::class)->workspaceFor($this->owner);
        $folder = $this->makeFolder($this->owner, $ws->id);
        app(CollectionService::class)->lock($this->owner, $folder->id, 'hint');

        try {
            app(CollectionService::class)->analyze($this->owner, $folder->id);
            $this->fail('Analyze on an empty collection must be rejected');
        } catch (MicrostockException $e) {
            $this->assertSame(422, $e->statusCode());
        }
    }

    public function test_process_collection_is_non_recursive_and_marks_review(): void
    {
        $this->bindFakeAi();

        $ws = app(MicrostockService::class)->workspaceFor($this->owner);
        $folder = $this->makeFolder($this->owner, $ws->id);
        $sub = $this->makeFolder($this->owner, $ws->id, 'Sub', $folder->id);

        $inRoot = $this->makeFile($this->owner, $ws->id, $folder->id, 'root.jpg');
        $inSub = $this->makeFile($this->owner, $ws->id, $sub->id, 'sub.jpg'); // must NOT be processed

        app(CollectionService::class)->lock($this->owner, $folder->id, 'kitchen hint');
        $folder->markCollectionStatus(CollectionStatus::Processing);

        (new ProcessCollection((string) $folder->id))->handle();

        // A batch was created for the root file only; sub-folder file untouched.
        $this->assertDatabaseHas('microstock_asset_metadata', ['file_id' => $inRoot->id]);
        $this->assertDatabaseMissing('microstock_asset_metadata', ['file_id' => $inSub->id]);
    }

    public function test_process_collection_empty_marks_failed(): void
    {
        $ws = app(MicrostockService::class)->workspaceFor($this->owner);
        $folder = $this->makeFolder($this->owner, $ws->id);
        $folder->markCollectionStatus(CollectionStatus::Processing);

        (new ProcessCollection((string) $folder->id))->handle();

        $this->assertSame(CollectionStatus::Failed, $folder->refresh()->collection_status);
    }

    // ---- Hint threading (unit) ----

    public function test_prompts_include_collection_hint(): void
    {
        $hint = 'flat-lay eco kitchen products';

        $this->assertStringContainsString($hint, Prompts::visionUser($hint));
        $this->assertStringContainsString($hint, Prompts::thinkingUser('T', ['a'], $hint));

        // No hint -> unchanged base prompts.
        $this->assertStringNotContainsString('Collection context', Prompts::visionUser());
        $this->assertStringNotContainsString('Collection context', Prompts::thinkingUser('T', ['a']));
    }

    // ---- Status snapshot ----

    public function test_status_reports_progress(): void
    {
        $ws = app(MicrostockService::class)->workspaceFor($this->owner);
        $folder = $this->makeFolder($this->owner, $ws->id);
        $a = $this->makeFile($this->owner, $ws->id, $folder->id, 'a.jpg');
        $b = $this->makeFile($this->owner, $ws->id, $folder->id, 'b.jpg');

        AssetMetadata::query()->create([
            'file_id' => $a->id, 'user_id' => $this->owner->id,
            'status' => MetadataStatus::Finalized, 'title' => 'A', 'keywords' => ['x'],
        ]);
        AssetMetadata::query()->create([
            'file_id' => $b->id, 'user_id' => $this->owner->id,
            'status' => MetadataStatus::Generated, 'title' => 'B', 'keywords' => ['y'],
        ]);

        $status = app(CollectionService::class)->status($this->owner, $folder->id);
        $this->assertSame(2, $status['total']);
        $this->assertSame(2, $status['processed']);
        $this->assertSame(1, $status['finalized']);
    }

    // ---- CSV per collection ----

    public function test_build_for_collection_only_finalized_in_folder(): void
    {
        $ws = app(MicrostockService::class)->workspaceFor($this->owner);
        $folder = $this->makeFolder($this->owner, $ws->id);
        $other = $this->makeFolder($this->owner, $ws->id, 'Other');

        $inFolder = $this->makeFile($this->owner, $ws->id, $folder->id, 'in.jpg');
        $inOther = $this->makeFile($this->owner, $ws->id, $other->id, 'out.jpg');
        $draft = $this->makeFile($this->owner, $ws->id, $folder->id, 'draft.jpg');

        AssetMetadata::query()->create([
            'file_id' => $inFolder->id, 'user_id' => $this->owner->id,
            'status' => MetadataStatus::Finalized, 'title' => 'In', 'keywords' => ['one', 'two'],
        ]);
        AssetMetadata::query()->create([
            'file_id' => $inOther->id, 'user_id' => $this->owner->id,
            'status' => MetadataStatus::Finalized, 'title' => 'Out', 'keywords' => ['x'],
        ]);
        AssetMetadata::query()->create([
            'file_id' => $draft->id, 'user_id' => $this->owner->id,
            'status' => MetadataStatus::Generated, 'title' => 'Draft', 'keywords' => ['y'],
        ]);

        $built = app(CsvExportService::class)->buildForCollection($folder);

        // Only the finalized file in THIS folder.
        $this->assertSame(1, $built['count']);
        $this->assertSame('in.jpg', $built['rows'][1][0]);
        $this->assertSame('In', $built['rows'][1][1]);
        $this->assertSame('one, two', $built['rows'][1][2]);
    }

    public function test_export_persists_csv_and_marks_exported(): void
    {
        Storage::fake('local');

        $ws = app(MicrostockService::class)->workspaceFor($this->owner);
        $folder = $this->makeFolder($this->owner, $ws->id);
        $file = $this->makeFile($this->owner, $ws->id, $folder->id, 'a.jpg');

        AssetMetadata::query()->create([
            'file_id' => $file->id, 'user_id' => $this->owner->id,
            'status' => MetadataStatus::Finalized, 'title' => 'Title A', 'keywords' => ['one'],
        ]);

        // Not in review yet.
        try {
            app(CollectionService::class)->export($this->owner, $folder->id);
            $this->fail('Export before review must be rejected');
        } catch (MicrostockException $e) {
            $this->assertSame(422, $e->statusCode());
        }

        $folder->markCollectionStatus(CollectionStatus::Review);
        $result = app(CollectionService::class)->export($this->owner, $folder->id);

        $this->assertSame(1, $result['count']);
        $fresh = $folder->refresh();
        $this->assertSame(CollectionStatus::Exported, $fresh->collection_status);
        $this->assertNotNull($fresh->collection_csv_path);
        Storage::disk('local')->assertExists($fresh->collection_csv_path);

        $csv = app(CollectionService::class)->downloadCsv($this->owner, $folder->id);
        $this->assertStringContainsString('Title A', $csv['contents']);
    }

    // ---- helpers ----

    private function bindFakeAi(): void
    {
        $this->app->instance(AiMetadataService::class, new class implements AiMetadataService
        {
            public function generate(string $imageUrl, ?string $hint = null): array
            {
                return [
                    'title' => 'Curated title',
                    'keywords' => ['a', 'b'],
                    'raw' => ['vision' => null, 'thinking' => null],
                ];
            }
        });
    }

    private function makeFolder(User $owner, string $workspaceId, string $name = 'Collection', ?string $parentId = null): Folder
    {
        return Folder::query()->create([
            'user_id' => $owner->id,
            'workspace_id' => $workspaceId,
            'parent_id' => $parentId,
            'name' => $name,
            'visibility' => 'private',
        ]);
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

    private function grantPaidMain(User $user): void
    {
        $service = Service::query()->where('code', 'file-hosting.plan.1tb.monthly')->first()
            ?? Service::factory()->fileHostingMain1Tb()->create();

        ServiceEntitlement::query()->updateOrCreate(
            ['user_id' => $user->id, 'service_id' => $service->id],
            [
                'status' => EntitlementStatus::Active,
                'quantity' => 1,
                'starts_at' => now(),
                'ends_at' => now()->addDays(30),
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
