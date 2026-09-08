<?php

namespace Tests\Domain\Archivd\Unit\Support;

use App\Domain\Archivd\Enums\WorkspaceMemberStatus;
use App\Domain\Archivd\Enums\WorkspaceRole;
use App\Domain\Archivd\Enums\WorkspaceStatus;
use App\Domain\Archivd\Events\ListingChanged;
use App\Domain\Archivd\Events\QuotaChanged;
use App\Domain\Archivd\Events\WorkspaceActivityAppended;
use App\Domain\Archivd\Events\WorkspaceMembersChanged;
use App\Domain\Archivd\Models\HostedFile;
use App\Domain\Archivd\Models\Workspace;
use App\Domain\Archivd\Models\WorkspaceMember;
use App\Domain\Archivd\Support\ArchivdRealtime;
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class ArchivdRealtimeTest extends TestCase
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
            // Listing realtime is independent of notification delivery mode.
            'notifications.delivery' => 'polling',
        ]);
    }

    private function disableBroadcast(): void
    {
        config(['broadcasting.default' => 'null']);
    }

    public function test_enabled_follows_broadcast_credentials_not_notification_type(): void
    {
        $rt = app(ArchivdRealtime::class);

        $this->disableBroadcast();
        $this->assertFalse($rt->enabled());
        $this->assertNull($rt->clientConfig());

        $this->enableBroadcast();
        $this->assertTrue($rt->enabled());
        $this->assertIsArray($rt->clientConfig());
        $this->assertSame('key', $rt->clientConfig()['key']);
    }

    public function test_listing_changed_noop_when_unconfigured(): void
    {
        Event::fake([ListingChanged::class]);
        $this->disableBroadcast();

        app(ArchivdRealtime::class)->listingChanged(
            action: 'rename',
            entityType: 'file',
            entityId: 'x',
            ownerUserId: 'o',
            workspaceId: null,
            folderId: null,
        );

        Event::assertNotDispatched(ListingChanged::class);
    }

    public function test_listing_changed_dispatches_when_configured(): void
    {
        Event::fake([ListingChanged::class]);
        $this->enableBroadcast();

        app(ArchivdRealtime::class)->listingChanged(
            action: 'rename',
            entityType: 'file',
            entityId: 'file-1',
            ownerUserId: 'owner-1',
            workspaceId: null,
            folderId: null,
            actorId: 'actor-1',
        );

        Event::assertDispatched(ListingChanged::class, function (ListingChanged $e): bool {
            return $e->action === 'rename'
                && $e->entityId === 'file-1'
                && $e->ownerUserId === 'owner-1'
                && $e->actorId === 'actor-1';
        });
    }

    public function test_file_listing_includes_resource_and_activity_for_team(): void
    {
        Event::fake([ListingChanged::class, WorkspaceActivityAppended::class]);
        $this->enableBroadcast();

        $owner = $this->userWithRole('User', 'rt-owner@example.com');
        $ws = Workspace::query()->create([
            'owner_user_id' => $owner->id,
            'name' => 'RT Team',
            'status' => WorkspaceStatus::Active,
        ]);
        WorkspaceMember::query()->create([
            'workspace_id' => $ws->id,
            'user_id' => $owner->id,
            'role' => WorkspaceRole::Owner,
            'status' => WorkspaceMemberStatus::Active,
            'joined_at' => now(),
        ]);

        $file = HostedFile::query()->create([
            'user_id' => $owner->id,
            'workspace_id' => $ws->id,
            'folder_id' => null,
            'name' => 'doc.pdf',
            'disk' => 'local',
            'path' => str_repeat('a', 40),
            'size' => 100,
            'mime' => 'application/pdf',
        ]);

        app(ArchivdRealtime::class)->fileListing('rename', $file, $owner);

        Event::assertDispatched(ListingChanged::class, function (ListingChanged $e) use ($file, $ws): bool {
            return $e->action === 'rename'
                && $e->entityType === 'file'
                && $e->entityId === $file->id
                && $e->workspaceId === $ws->id
                && is_array($e->resource)
                && ($e->resource['name'] ?? null) === 'doc.pdf';
        });
        Event::assertDispatched(WorkspaceActivityAppended::class);
    }

    public function test_quota_and_members_dispatch(): void
    {
        Event::fake([QuotaChanged::class, WorkspaceMembersChanged::class]);
        $this->enableBroadcast();

        $rt = app(ArchivdRealtime::class);
        $rt->quotaChanged('owner-q');
        $rt->membersChanged('ws-m', 'removed', 'user-x', 'actor-y');

        Event::assertDispatched(QuotaChanged::class, fn (QuotaChanged $e) => $e->ownerUserId === 'owner-q');
        Event::assertDispatched(WorkspaceMembersChanged::class, function (WorkspaceMembersChanged $e): bool {
            return $e->workspaceId === 'ws-m'
                && $e->action === 'removed'
                && $e->userId === 'user-x';
        });
    }

    private function userWithRole(string $role, string $email): User
    {
        $user = User::factory()->create(['email' => $email]);
        $user->assignRole($role);

        return $user;
    }
}
