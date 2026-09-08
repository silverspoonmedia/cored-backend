<?php

namespace Tests\Domain\Archivd\Unit\Events;

use App\Domain\Archivd\Events\FileStatsChanged;
use App\Domain\Archivd\Events\ListingChanged;
use App\Domain\Archivd\Events\QuotaChanged;
use App\Domain\Archivd\Events\WorkspaceActivityAppended;
use App\Domain\Archivd\Events\WorkspaceMembersChanged;
use Illuminate\Broadcasting\PrivateChannel;
use Tests\TestCase;

class ArchivdRealtimeEventsTest extends TestCase
{
    public function test_listing_changed_routes_personal_to_user_channel(): void
    {
        $event = new ListingChanged(
            action: 'rename',
            entityType: 'file',
            entityId: 'file-1',
            ownerUserId: 'owner-1',
            workspaceId: null,
            folderId: null,
            actorId: 'actor-1',
            resource: ['id' => 'file-1', 'name' => 'a.pdf'],
        );

        $channels = $event->broadcastOn();
        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertSame('private-archivd.user.owner-1', $channels[0]->name);
        $this->assertSame('listing.changed', $event->broadcastAs());

        $payload = $event->broadcastWith();
        $this->assertSame('rename', $payload['action']);
        $this->assertSame('file', $payload['entity_type']);
        $this->assertSame('file-1', $payload['entity_id']);
        $this->assertSame('owner-1', $payload['owner_user_id']);
        $this->assertNull($payload['workspace_id']);
        $this->assertSame(['id' => 'file-1', 'name' => 'a.pdf'], $payload['resource']);
    }

    public function test_listing_changed_routes_team_to_workspace_channel(): void
    {
        $event = new ListingChanged(
            action: 'create',
            entityType: 'folder',
            entityId: 'folder-1',
            ownerUserId: 'owner-1',
            workspaceId: 'ws-1',
            folderId: null,
            actorId: 'actor-1',
        );

        $this->assertSame('private-archivd.workspace.ws-1', $event->broadcastOn()[0]->name);
        $this->assertSame('ws-1', $event->broadcastWith()['workspace_id']);
    }

    public function test_listing_changed_bulk_payload(): void
    {
        $event = new ListingChanged(
            action: 'bulk_soft_delete',
            entityType: 'file',
            entityId: null,
            ownerUserId: 'owner-1',
            workspaceId: null,
            folderId: null,
            entityIds: ['a', 'b'],
        );

        $payload = $event->broadcastWith();
        $this->assertNull($payload['entity_id']);
        $this->assertSame(['a', 'b'], $payload['entity_ids']);
    }

    public function test_quota_changed_payload_and_channel(): void
    {
        $event = new QuotaChanged('owner-9');

        $this->assertSame('private-archivd.user.owner-9', $event->broadcastOn()[0]->name);
        $this->assertSame('quota.changed', $event->broadcastAs());
        $this->assertSame(['owner_user_id' => 'owner-9'], $event->broadcastWith());
    }

    public function test_workspace_members_changed_payload(): void
    {
        $event = new WorkspaceMembersChanged('ws-2', 'removed', 'user-3', 'actor-1');

        $this->assertSame('private-archivd.workspace.ws-2', $event->broadcastOn()[0]->name);
        $this->assertSame('members.changed', $event->broadcastAs());
        $this->assertSame([
            'workspace_id' => 'ws-2',
            'action' => 'removed',
            'user_id' => 'user-3',
            'actor_id' => 'actor-1',
        ], $event->broadcastWith());
    }

    public function test_workspace_activity_appended_payload(): void
    {
        $event = new WorkspaceActivityAppended('ws-3', 'actor-2');

        $this->assertSame('activity.appended', $event->broadcastAs());
        $this->assertSame([
            'workspace_id' => 'ws-3',
            'actor_id' => 'actor-2',
        ], $event->broadcastWith());
    }

    public function test_file_stats_changed_routes_by_workspace(): void
    {
        $personal = new FileStatsChanged('f1', 'owner-1', null, 2, 3);
        $this->assertSame('private-archivd.user.owner-1', $personal->broadcastOn()[0]->name);
        $this->assertSame('file.stats.changed', $personal->broadcastAs());
        $this->assertSame(2, $personal->broadcastWith()['view_count']);
        $this->assertSame(3, $personal->broadcastWith()['download_count']);

        $team = new FileStatsChanged('f2', 'owner-1', 'ws-1', 1, 0);
        $this->assertSame('private-archivd.workspace.ws-1', $team->broadcastOn()[0]->name);
    }
}
