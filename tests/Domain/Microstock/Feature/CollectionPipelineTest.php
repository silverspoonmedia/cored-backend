<?php

namespace Tests\Domain\Microstock\Feature;

use App\Domain\Archivd\Enums\CollectionStatus;
use App\Domain\Archivd\Enums\WorkspaceKind;
use App\Domain\Archivd\Models\Folder;
use App\Domain\Archivd\Models\HostedFile;
use App\Domain\Archivd\Models\Workspace;
use App\Domain\Identity\Models\User;
use App\Domain\Microstock\Enums\AnalysisPipeline;
use App\Domain\Microstock\Enums\AnalysisStageStatus;
use App\Domain\Microstock\Enums\MetadataStatus;
use App\Domain\Microstock\Jobs\AnalyzeAssetThinking;
use App\Domain\Microstock\Jobs\AnalyzeAssetVision;
use App\Domain\Microstock\Jobs\AnalysisWatchdog;
use App\Domain\Microstock\Jobs\ProcessCollection;
use App\Domain\Microstock\Models\AssetMetadata;
use App\Domain\Microstock\Services\MicrostockService;
use App\Domain\Service\Enums\EntitlementStatus;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServiceEntitlement;
use App\Domain\Service\Support\MicrostockCatalog;
use Database\Seeders\ArchivdRoleSeeder;
use Database\Seeders\MicrostockRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Concerns\GrantsMicrostockAiCredits;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class CollectionPipelineTest extends TestCase
{
    use GrantsMicrostockAiCredits;
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->seed(ArchivdRoleSeeder::class);
        $this->seed(MicrostockRoleSeeder::class);

        config([
            'archivd.disk' => 'local',
            'activitylog.enabled' => false,
            'microstock.daily_free.enabled' => false,
            'microstock.queues.collection_vision' => 'microstock-collection-vision',
            'microstock.queues.collection_thinking' => 'microstock-collection-thinking',
            'microstock.queues.solo' => 'microstock-solo',
        ]);

        $this->owner = $this->userWithRole('User', 'pipeline@example.com');
        $this->grantMicrostockFree($this->owner);
        $this->grantMicrostockAiCredits($this->owner);
    }

    public function test_process_collection_dispatches_one_vision_job_per_file(): void
    {
        Queue::fake();

        $ws = app(MicrostockService::class)->workspaceFor($this->owner);
        $folder = $this->makeFolder($this->owner, $ws->id);
        $this->makeFile($this->owner, $ws->id, $folder->id, 'a.jpg');
        $this->makeFile($this->owner, $ws->id, $folder->id, 'b.jpg');
        $this->makeFile($this->owner, $ws->id, $folder->id, 'c.jpg');

        $folder->markCollectionStatus(CollectionStatus::Processing);

        (new ProcessCollection((string) $folder->id, (string) $this->owner->id, (string) \Illuminate\Support\Str::uuid()))->handle(app(\App\Domain\Microstock\Services\AnalysisStageService::class));

        Queue::assertPushed(AnalyzeAssetVision::class, 3);
        Queue::assertPushedOn('microstock-collection-vision', AnalyzeAssetVision::class);
    }

    public function test_solo_request_uses_solo_queue_not_collection_queues(): void
    {
        Queue::fake();

        $ws = app(MicrostockService::class)->workspaceFor($this->owner);
        $folder = $this->makeFolder($this->owner, $ws->id);
        $file = $this->makeFile($this->owner, $ws->id, $folder->id, 'solo.jpg');

        app(MicrostockService::class)->requestAnalysis($this->owner, (string) $file->id);

        Queue::assertPushedOn('microstock-solo', AnalyzeAssetVision::class);
        Queue::assertNotPushed(AnalyzeAssetThinking::class);
    }

    public function test_thinking_idempotency_skips_when_already_ok(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::query()->create([
            'owner_user_id' => $user->id,
            'name' => 'WS',
            'kind' => WorkspaceKind::Team,
            'status' => 'active',
        ]);
        $file = HostedFile::query()->create([
            'user_id' => $user->id,
            'workspace_id' => $workspace->id,
            'folder_id' => null,
            'name' => 'x.jpg',
            'mime' => 'image/jpeg',
            'size' => 1024,
            'disk' => 'local',
            'path' => 'x',
            'visibility' => 'private',
        ]);

        $metadata = AssetMetadata::query()->create([
            'file_id' => $file->id,
            'user_id' => $user->id,
            'status' => MetadataStatus::Generated,
            'title' => 'Existing',
            'ai_vision' => [
                'status' => AnalysisStageStatus::Ok->value,
                'output' => [
                    'title' => 'Draft',
                    'keywords' => ['a'],
                    'description' => '',
                    'category' => '',
                    'raw' => [],
                ],
            ],
            'ai_thinking' => [
                'status' => AnalysisStageStatus::Ok->value,
            ],
        ]);

        (new AnalyzeAssetThinking((string) $metadata->id, 'hint', null, AnalysisPipeline::Solo))->handle(
            app(\App\Domain\Microstock\Contracts\AiMetadataService::class),
            app(\App\Domain\Microstock\Services\VisionImageResolver::class),
            app(\App\Domain\Microstock\Services\AnalysisStageService::class),
        );

        $this->assertSame('Existing', $metadata->refresh()->title);
    }

    public function test_watchdog_redispatches_stale_thinking_backlog(): void
    {
        Queue::fake();

        config(['microstock.ai.stage.thinking_backlog_after' => 60]);

        $ws = app(MicrostockService::class)->workspaceFor($this->owner);
        $folder = $this->makeFolder($this->owner, $ws->id);
        $folder->markCollectionStatus(CollectionStatus::Processing);
        $file = $this->makeFile($this->owner, $ws->id, $folder->id, 'stale.jpg');

        AssetMetadata::query()->create([
            'file_id' => $file->id,
            'user_id' => $this->owner->id,
            'status' => MetadataStatus::Pending,
            'ai_vision' => [
                'status' => AnalysisStageStatus::Ok->value,
                'completed_at' => now()->subMinutes(5)->toIso8601String(),
                'output' => [
                    'title' => 'Draft',
                    'keywords' => ['a'],
                    'description' => '',
                    'category' => '',
                    'raw' => [],
                ],
            ],
            'ai_thinking' => [
                'status' => AnalysisStageStatus::Pending->value,
            ],
        ]);

        (new AnalysisWatchdog)->handle(app(\App\Domain\Microstock\Services\AnalysisStageService::class));

        Queue::assertPushed(AnalyzeAssetThinking::class);
        Queue::assertPushedOn('microstock-collection-thinking', AnalyzeAssetThinking::class);
    }

    private function makeFolder(User $user, string $workspaceId, string $name = 'Collection'): Folder
    {
        return Folder::query()->create([
            'user_id' => $user->id,
            'workspace_id' => $workspaceId,
            'name' => $name,
            'visibility' => 'private',
        ]);
    }

    private function makeFile(User $user, string $workspaceId, string $folderId, string $name): HostedFile
    {
        return HostedFile::query()->create([
            'user_id' => $user->id,
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
}
