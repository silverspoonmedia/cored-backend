<?php

namespace Tests\Domain\Microstock\Unit\Support;

use App\Domain\Archivd\Enums\CollectionStatus;
use App\Domain\Archivd\Enums\WorkspaceKind;
use App\Domain\Archivd\Models\Folder;
use App\Domain\Archivd\Models\HostedFile;
use App\Domain\Archivd\Models\Workspace;
use App\Domain\Identity\Models\User;
use App\Domain\Microstock\Enums\MetadataStatus;
use App\Domain\Microstock\Events\AssetMetadataProgress;
use App\Domain\Microstock\Events\CollectionAnalysisProgress;
use App\Domain\Microstock\Models\AssetMetadata;
use App\Domain\Microstock\Support\MicrostockRealtime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class MicrostockRealtimeTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
    }

    private function enableBroadcast(): void
    {
        config([
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher' => [
                'driver' => 'pusher',
                'key' => 'key',
                'secret' => 'secret',
                'app_id' => 'app',
                'options' => ['cluster' => 'ap1', 'port' => 443, 'scheme' => 'https'],
            ],
        ]);
    }

    private function disableBroadcast(): void
    {
        config(['broadcasting.default' => 'null']);
    }

    public function test_enabled_follows_broadcast_credentials(): void
    {
        $rt = app(MicrostockRealtime::class);

        $this->disableBroadcast();
        $this->assertFalse($rt->enabled());

        $this->enableBroadcast();
        $this->assertTrue($rt->enabled());
    }

    public function test_collection_progress_dispatches_workspace_channel_event(): void
    {
        Event::fake([CollectionAnalysisProgress::class]);
        $this->enableBroadcast();

        [$folder] = $this->makeCollectionFixture();

        app(MicrostockRealtime::class)->collectionProgress($folder, force: true);

        Event::assertDispatched(CollectionAnalysisProgress::class, function (CollectionAnalysisProgress $event) use ($folder): bool {
            return $event->folderId === (string) $folder->id
                && $event->workspaceId === (string) $folder->workspace_id
                && $event->snapshot['status'] === CollectionStatus::Processing->value
                && $event->broadcastOn()[0]->name === 'private-archivd.workspace.'.$folder->workspace_id;
        });
    }

    public function test_asset_progress_dispatches_microstock_payload(): void
    {
        Event::fake([AssetMetadataProgress::class]);
        $this->enableBroadcast();

        [$folder, $file, $metadata] = $this->makeCollectionFixture(withMetadata: true);

        app(MicrostockRealtime::class)->assetProgress($metadata, $file, force: true);

        Event::assertDispatched(AssetMetadataProgress::class, function (AssetMetadataProgress $event) use ($file, $folder): bool {
            return $event->fileId === (string) $file->id
                && $event->folderId === (string) $folder->id
                && $event->microstock['status'] === MetadataStatus::Pending->value
                && isset($event->microstock['stages']['vision']);
        });
    }

    public function test_progress_noop_when_broadcast_unconfigured(): void
    {
        Event::fake([CollectionAnalysisProgress::class, AssetMetadataProgress::class]);
        $this->disableBroadcast();

        [$folder, $file, $metadata] = $this->makeCollectionFixture(withMetadata: true);
        $rt = app(MicrostockRealtime::class);

        $rt->collectionProgress($folder, force: true);
        $rt->assetProgress($metadata, $file, force: true);

        Event::assertNothingDispatched();
    }

    /**
     * @return array{0: Folder, 1?: HostedFile, 2?: AssetMetadata}
     */
    private function makeCollectionFixture(bool $withMetadata = false): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::query()->create([
            'owner_user_id' => $user->id,
            'name' => 'Microstock WS',
            'kind' => WorkspaceKind::Microstock,
            'status' => 'active',
        ]);
        $folder = Folder::query()->create([
            'user_id' => $user->id,
            'workspace_id' => $workspace->id,
            'name' => 'Collection',
            'visibility' => 'private',
            'collection_status' => CollectionStatus::Processing,
            'collection_locked_at' => now(),
        ]);
        $file = HostedFile::query()->create([
            'user_id' => $user->id,
            'workspace_id' => $workspace->id,
            'folder_id' => $folder->id,
            'name' => 'photo.jpg',
            'mime' => 'image/jpeg',
            'size' => 1024,
            'disk' => 'local',
            'path' => 'photo.jpg',
            'visibility' => 'private',
        ]);

        if (! $withMetadata) {
            return [$folder];
        }

        $metadata = AssetMetadata::query()->create([
            'file_id' => $file->id,
            'user_id' => $user->id,
            'status' => MetadataStatus::Pending,
        ]);

        return [$folder, $file, $metadata];
    }
}
