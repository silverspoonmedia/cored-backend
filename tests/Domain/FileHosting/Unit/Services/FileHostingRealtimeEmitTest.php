<?php

namespace Tests\Domain\FileHosting\Unit\Services;

use App\Domain\FileHosting\Events\FileStatsChanged;
use App\Domain\FileHosting\Events\ListingChanged;
use App\Domain\FileHosting\Events\QuotaChanged;
use App\Domain\FileHosting\Events\WorkspaceActivityAppended;
use App\Domain\FileHosting\Events\WorkspaceMembersChanged;
use App\Domain\FileHosting\Models\FileUpload;
use App\Domain\FileHosting\Models\HostedFile;
use App\Domain\FileHosting\Services\DirectUploadService;
use App\Domain\FileHosting\Services\FileService;
use App\Domain\FileHosting\Services\FolderService;
use App\Domain\FileHosting\Services\WorkspaceService;
use App\Domain\FileHosting\Support\ObjectKeyResolver;
use App\Domain\Identity\Models\User;
use App\Domain\Service\Enums\EntitlementStatus;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServiceEntitlement;
use Database\Seeders\FileHostingRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * Assert service mutations dispatch FH realtime events when broadcast is configured.
 */
class FileHostingRealtimeEmitTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->seed(FileHostingRoleSeeder::class);

        config([
            'file-hosting.disk' => 'local',
            'file-hosting.directory' => 'file-hosting',
            'file-hosting.default_quota_bytes' => 10 * 1024 * 1024,
            'file-hosting.allowed_mime_prefixes' => ['image/', 'application/', 'text/'],
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher' => [
                'driver' => 'pusher',
                'key' => 'key',
                'secret' => 'secret',
                'app_id' => 'app',
                'options' => ['cluster' => 'ap1'],
            ],
            'notifications.delivery' => 'polling',
        ]);

        Storage::fake('local');
        $this->user = $this->userWithRole('User', 'fh-rt@example.com');
    }

    public function test_rename_file_dispatches_listing_changed(): void
    {
        Event::fake([ListingChanged::class]);

        $file = HostedFile::query()->create([
            'user_id' => $this->user->id,
            'folder_id' => null,
            'name' => 'old.pdf',
            'disk' => 'local',
            'path' => str_repeat('b', 40),
            'size' => 10,
            'mime' => 'application/pdf',
        ]);

        app(FileService::class)->rename($this->user, $file->id, 'new.pdf');

        Event::assertDispatched(ListingChanged::class, function (ListingChanged $e) use ($file): bool {
            return $e->action === 'rename'
                && $e->entityType === 'file'
                && $e->entityId === $file->id
                && $e->ownerUserId === $this->user->id
                && ($e->resource['name'] ?? null) === 'new.pdf';
        });
    }

    public function test_soft_delete_file_dispatches_listing_and_quota(): void
    {
        Event::fake([ListingChanged::class, QuotaChanged::class]);

        $file = HostedFile::query()->create([
            'user_id' => $this->user->id,
            'folder_id' => null,
            'name' => 'gone.pdf',
            'disk' => 'local',
            'path' => str_repeat('c', 40),
            'size' => 10,
            'mime' => 'application/pdf',
        ]);

        app(FileService::class)->softDelete($this->user, $file->id);

        Event::assertDispatched(ListingChanged::class, fn (ListingChanged $e) => $e->action === 'soft_delete');
        Event::assertDispatched(QuotaChanged::class, fn (QuotaChanged $e) => $e->ownerUserId === $this->user->id);
    }

    public function test_create_folder_dispatches_listing_changed(): void
    {
        Event::fake([ListingChanged::class]);

        $folder = app(FolderService::class)->create($this->user, 'Docs', null);

        Event::assertDispatched(ListingChanged::class, function (ListingChanged $e) use ($folder): bool {
            return $e->action === 'create'
                && $e->entityType === 'folder'
                && $e->entityId === $folder->id;
        });
    }

    public function test_complete_upload_dispatches_create_and_quota(): void
    {
        Event::fake([ListingChanged::class, QuotaChanged::class]);

        $objectId = Str::lower(Str::random(ObjectKeyResolver::OBJECT_ID_LENGTH));
        $objectKey = app(ObjectKeyResolver::class)->objectKey($this->user->id, $objectId);

        $upload = FileUpload::query()->create([
            'user_id' => $this->user->id,
            'workspace_id' => null,
            'folder_id' => null,
            'disk' => 'local',
            'path' => $objectId,
            's3_upload_id' => null,
            'strategy' => FileUpload::STRATEGY_SINGLE,
            'original_name' => 'up.pdf',
            'mime' => 'application/pdf',
            'size' => 512,
            'status' => FileUpload::STATUS_PENDING,
            'parts' => [],
            'expires_at' => Carbon::now()->addHour(),
        ]);

        Storage::disk('local')->put($objectKey, str_repeat('x', 512));

        app(DirectUploadService::class)->complete($this->user, $upload->id, null);

        Event::assertDispatched(ListingChanged::class, fn (ListingChanged $e) => $e->action === 'create' && $e->entityType === 'file');
        Event::assertDispatched(QuotaChanged::class);
    }

    public function test_remove_member_dispatches_members_changed(): void
    {
        // Fake all FH realtime events so create/invite/accept never hit real Pusher.
        Event::fake([
            WorkspaceMembersChanged::class,
            WorkspaceActivityAppended::class,
            ListingChanged::class,
            QuotaChanged::class,
            FileStatsChanged::class,
        ]);

        $this->grantPaidMain($this->user);
        $editor = $this->userWithRole('User', 'fh-editor@example.com');

        $ws = app(WorkspaceService::class)->create($this->user, 'Team');
        $invite = app(WorkspaceService::class)->invite(
            $this->user,
            $ws->id,
            $editor->email,
            'editor',
        );
        app(WorkspaceService::class)->acceptInvite($editor, $invite->token);

        app(WorkspaceService::class)->removeMember($this->user, $ws->id, $editor->id);

        Event::assertDispatched(WorkspaceMembersChanged::class, function (WorkspaceMembersChanged $e) use ($ws, $editor): bool {
            return $e->workspaceId === $ws->id
                && $e->action === 'removed'
                && $e->userId === $editor->id;
        });
    }

    public function test_no_events_when_broadcast_unconfigured(): void
    {
        config(['broadcasting.default' => 'null']);
        Event::fake([ListingChanged::class]);

        app(FolderService::class)->create($this->user, 'Silent', null);

        Event::assertNotDispatched(ListingChanged::class);
    }

    private function userWithRole(string $role, string $email): User
    {
        $user = User::factory()->create(['email' => $email]);
        $user->assignRole($role);

        return $user;
    }

    private function grantPaidMain(User $user): void
    {
        $service = Service::query()->where('code', 'file-hosting.plan.1tb.monthly')->first()
            ?? Service::factory()->fileHostingMain1Tb()->create();

        ServiceEntitlement::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'service_id' => $service->id,
            ],
            [
                'status' => EntitlementStatus::Active,
                'quantity' => 1,
                'starts_at' => now(),
                'ends_at' => now()->addDays(30),
            ],
        );
    }
}
