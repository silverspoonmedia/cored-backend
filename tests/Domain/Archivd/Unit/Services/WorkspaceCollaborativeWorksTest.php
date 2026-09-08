<?php

namespace Tests\Domain\Archivd\Unit\Services;

use App\Domain\Archivd\Enums\WorkspaceRole;
use App\Domain\Archivd\Exceptions\ArchivdException;
use App\Domain\Archivd\Services\DirectUploadService;
use App\Domain\Archivd\Services\FolderService;
use App\Domain\Archivd\Services\QuotaService;
use App\Domain\Archivd\Services\WorkspaceService;
use App\Domain\Archivd\Support\WorkspaceActivityRecorder;
use App\Domain\Identity\Models\User;
use App\Domain\Service\Enums\EntitlementStatus;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServiceEntitlement;
use Database\Seeders\ArchivdRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\Fakes\FakeArchivdDirectUploadService;
use Tests\TestCase;

class WorkspaceCollaborativeWorksTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private User $owner;

    private User $editor;

    private User $viewer;

    private User $stranger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->seed(ArchivdRoleSeeder::class);

        config([
            'archivd.disk' => 'local',
            'archivd.default_quota_bytes' => 10 * 1024 * 1024,
            'archivd.allowed_mime_prefixes' => ['image/', 'application/', 'text/'],
            'activitylog.enabled' => true,
        ]);

        Storage::fake('local');

        $this->app->instance(
            DirectUploadService::class,
            $this->app->make(FakeArchivdDirectUploadService::class),
        );

        $this->owner = $this->userWithRole('User', 'owner@example.com');
        $this->editor = $this->userWithRole('User', 'editor@example.com');
        $this->viewer = $this->userWithRole('User', 'viewer@example.com');
        $this->stranger = $this->userWithRole('User', 'stranger@example.com');
    }

    public function test_free_user_cannot_create_workspace(): void
    {
        $this->expectException(ArchivdException::class);
        $this->expectExceptionMessage(__('api/archivd.workspace.requires_paid'));

        app(WorkspaceService::class)->create($this->owner, 'Team Drive');
    }

    public function test_paid_owner_creates_workspace_invites_member_upload_quota_activity(): void
    {
        $this->grantPaidMain($this->owner);

        $ws = app(WorkspaceService::class)->create($this->owner, 'Team Drive');
        $this->assertSame($this->owner->id, $ws->owner_user_id);
        $this->assertDatabaseHas('archivd_workspace_members', [
            'workspace_id' => $ws->id,
            'user_id' => $this->owner->id,
            'role' => WorkspaceRole::Owner->value,
        ]);

        $invite = app(WorkspaceService::class)->invite(
            $this->owner,
            $ws->id,
            $this->editor->email,
            WorkspaceRole::Editor->value,
        );

        $member = app(WorkspaceService::class)->acceptInvite($this->editor, $invite->token);
        $this->assertSame(WorkspaceRole::Editor, $member->role);

        $folder = app(FolderService::class)->create(
            $this->editor,
            'Shared',
            null,
            null,
            $ws->id,
        );
        $this->assertSame($this->owner->id, $folder->user_id);
        $this->assertSame($ws->id, $folder->workspace_id);

        $uploads = app(DirectUploadService::class);
        $init = $uploads->initiate(
            $this->editor,
            'team-doc.pdf',
            'application/pdf',
            2048,
            $folder->id,
            null,
            null,
            $ws->id,
        );
        $complete = $uploads->complete($this->editor, $init['upload']->id);
        $file = $complete['file'];

        $this->assertSame($this->owner->id, $file->user_id);
        $this->assertSame($ws->id, $file->workspace_id);
        $this->assertSame($this->editor->id, $file->created_by);

        $usage = app(QuotaService::class)->usage($this->owner->id);
        $this->assertSame(2048, $usage['used_bytes']);

        // Personal drive of editor must stay empty of team files
        $personal = app(FolderService::class)->browse($this->editor, null);
        $this->assertCount(0, $personal['files']);
        $this->assertNull($personal['workspace_id']);

        $activity = app(WorkspaceService::class)->activity($this->owner, $ws->id, 50);
        $events = $activity->pluck('event')->all();
        $this->assertContains('workspace_created', $events);
        $this->assertContains('member_invited', $events);
        $this->assertContains('member_joined', $events);
        $this->assertContains('folder_created', $events);
        $this->assertContains('file_created', $events);

        $this->assertTrue(
            Activity::query()
                ->where('log_name', WorkspaceActivityRecorder::LOG_NAME)
                ->where('properties->workspace_id', $ws->id)
                ->exists(),
        );
    }

    public function test_viewer_cannot_mutate_editor_can(): void
    {
        $this->grantPaidMain($this->owner);
        $ws = app(WorkspaceService::class)->create($this->owner, 'Roles');

        $inviteV = app(WorkspaceService::class)->invite(
            $this->owner,
            $ws->id,
            $this->viewer->email,
            WorkspaceRole::Viewer->value,
        );
        app(WorkspaceService::class)->acceptInvite($this->viewer, $inviteV->token);

        $inviteE = app(WorkspaceService::class)->invite(
            $this->owner,
            $ws->id,
            $this->editor->email,
            WorkspaceRole::Editor->value,
        );
        app(WorkspaceService::class)->acceptInvite($this->editor, $inviteE->token);

        app(FolderService::class)->create($this->editor, 'Ok', null, null, $ws->id);

        try {
            app(FolderService::class)->create($this->viewer, 'Nope', null, null, $ws->id);
            $this->fail('Viewer must not create folders');
        } catch (ArchivdException $e) {
            $this->assertSame(403, $e->statusCode());
        }

        // Viewer may browse
        $browse = app(FolderService::class)->browse($this->viewer, null, null, $ws->id);
        $this->assertSame($ws->id, $browse['workspace_id']);
        $this->assertCount(1, $browse['folders']);
    }

    public function test_remove_member_revokes_access(): void
    {
        $this->grantPaidMain($this->owner);
        $ws = app(WorkspaceService::class)->create($this->owner, 'Revoke');

        $invite = app(WorkspaceService::class)->invite(
            $this->owner,
            $ws->id,
            $this->editor->email,
            WorkspaceRole::Editor->value,
        );
        app(WorkspaceService::class)->acceptInvite($this->editor, $invite->token);

        app(WorkspaceService::class)->removeMember($this->owner, $ws->id, $this->editor->id);

        try {
            app(FolderService::class)->browse($this->editor, null, null, $ws->id);
            $this->fail('Removed member must not browse');
        } catch (ArchivdException $e) {
            $this->assertSame(403, $e->statusCode());
        }
    }

    public function test_stranger_cannot_see_workspace_or_personal_leak(): void
    {
        $this->grantPaidMain($this->owner);
        $ws = app(WorkspaceService::class)->create($this->owner, 'Private Team');

        app(FolderService::class)->create($this->owner, 'PersonalOnly', null, null, null);
        app(FolderService::class)->create($this->owner, 'TeamOnly', null, null, $ws->id);

        try {
            app(FolderService::class)->browse($this->stranger, null, null, $ws->id);
            $this->fail('Stranger must not browse workspace');
        } catch (ArchivdException $e) {
            $this->assertSame(403, $e->statusCode());
        }

        // Personal browse of owner as stranger still hierarchy-denied
        try {
            app(FolderService::class)->browse($this->stranger, null, $this->owner->id, null);
            $this->fail('Stranger must not browse personal drive');
        } catch (ArchivdException $e) {
            $this->assertSame(403, $e->statusCode());
        }
    }

    public function test_paid_owner_can_create_multiple_workspaces_up_to_base_cap(): void
    {
        $this->grantPaidMain($this->owner);
        config(['archivd.base_owned_workspace_slots' => 3]);

        $ws = app(WorkspaceService::class);
        $ws->create($this->owner, 'Team 1');
        $ws->create($this->owner, 'Team 2');
        $ws->create($this->owner, 'Team 3');

        $this->assertCount(3, $ws->listForUser($this->owner));
        $this->assertSame(3, app(QuotaService::class)->usage($this->owner->id)['owned_workspace_used']);
        $this->assertSame(3, app(QuotaService::class)->usage($this->owner->id)['owned_workspace_limit']);
        $this->assertFalse(app(QuotaService::class)->usage($this->owner->id)['can_create_workspace']);

        try {
            $ws->create($this->owner, 'Team 4');
            $this->fail('Must hit slot limit');
        } catch (ArchivdException $e) {
            $this->assertSame(422, $e->statusCode());
            $this->assertStringContainsString('3 / 3', $e->getMessage());
        }
    }

    public function test_workspace_slot_addon_raises_owned_limit_without_storage(): void
    {
        $this->grantPaidMain($this->owner);
        config(['archivd.base_owned_workspace_slots' => 2]);

        $quotaBefore = app(QuotaService::class)->usage($this->owner->id)['quota_bytes'];
        $this->grantWorkspaceSlots($this->owner, 2);

        $usage = app(QuotaService::class)->usage($this->owner->id);
        $this->assertSame($quotaBefore, $usage['quota_bytes']);
        $this->assertSame(4, $usage['owned_workspace_limit']);
        $this->assertTrue($usage['can_create_workspace']);

        $ws = app(WorkspaceService::class);
        for ($i = 1; $i <= 4; $i++) {
            $ws->create($this->owner, "Slot Team {$i}");
        }

        $this->assertCount(4, $ws->listForUser($this->owner));

        try {
            $ws->create($this->owner, 'Over');
            $this->fail('Must hit raised slot limit');
        } catch (ArchivdException $e) {
            $this->assertSame(422, $e->statusCode());
        }
    }

    public function test_paid_user_owns_one_and_joins_another_with_role_caps(): void
    {
        $this->grantPaidMain($this->owner);
        $this->grantPaidMain($this->editor);

        $wsA = app(WorkspaceService::class)->create($this->owner, 'Owner A Team');
        $wsB = app(WorkspaceService::class)->create($this->editor, 'Owner B Team');

        $invite = app(WorkspaceService::class)->invite(
            $this->owner,
            $wsA->id,
            $this->editor->email,
            WorkspaceRole::Editor->value,
        );
        app(WorkspaceService::class)->acceptInvite($this->editor, $invite->token);

        $list = app(WorkspaceService::class)->listForUser($this->editor);
        $ids = $list->pluck('id')->all();
        $this->assertContains($wsA->id, $ids);
        $this->assertContains($wsB->id, $ids);
        $this->assertCount(2, $list);

        // In A: editor caps — can mutate
        app(FolderService::class)->create($this->editor, 'In A', null, null, $wsA->id);

        // In B: owner caps
        app(FolderService::class)->create($this->editor, 'In B', null, null, $wsB->id);

        $browseA = app(FolderService::class)->browse($this->editor, null, null, $wsA->id);
        $this->assertSame($wsA->id, $browseA['workspace_id']);
        $this->assertCount(1, $browseA['folders']);

        $browseB = app(FolderService::class)->browse($this->editor, null, null, $wsB->id);
        $this->assertSame($wsB->id, $browseB['workspace_id']);
        $this->assertCount(1, $browseB['folders']);

        // Owner of A as viewer in B cannot mutate
        $inviteViewer = app(WorkspaceService::class)->invite(
            $this->editor,
            $wsB->id,
            $this->owner->email,
            WorkspaceRole::Viewer->value,
        );
        app(WorkspaceService::class)->acceptInvite($this->owner, $inviteViewer->token);

        try {
            app(FolderService::class)->create($this->owner, 'Nope in B', null, null, $wsB->id);
            $this->fail('Owner of A as viewer in B must not mutate');
        } catch (ArchivdException $e) {
            $this->assertSame(403, $e->statusCode());
        }

        // Still owner in A
        app(FolderService::class)->create($this->owner, 'Still owner A', null, null, $wsA->id);
    }

    public function test_free_user_can_join_paid_owner_workspace(): void
    {
        $this->grantPaidMain($this->owner);
        $ws = app(WorkspaceService::class)->create($this->owner, 'Paid Team');

        $invite = app(WorkspaceService::class)->invite(
            $this->owner,
            $ws->id,
            $this->editor->email,
            WorkspaceRole::Editor->value,
        );
        // editor has no paid entitlement
        $member = app(WorkspaceService::class)->acceptInvite($this->editor, $invite->token);
        $this->assertSame(WorkspaceRole::Editor, $member->role);

        app(FolderService::class)->create($this->editor, 'Free Joiner Folder', null, null, $ws->id);

        try {
            app(WorkspaceService::class)->create($this->editor, 'Free Own Team');
            $this->fail('Free user must not create workspace');
        } catch (ArchivdException $e) {
            $this->assertStringContainsString(
                __('api/archivd.workspace.requires_paid'),
                $e->getMessage(),
            );
        }

        $list = app(WorkspaceService::class)->listForUser($this->editor);
        $this->assertCount(1, $list);
        $this->assertSame($ws->id, $list->first()->id);
    }

    public function test_archive_frees_slot_and_create_inserts_new_row(): void
    {
        $this->grantPaidMain($this->owner);
        config(['archivd.base_owned_workspace_slots' => 1]);

        $service = app(WorkspaceService::class);
        $ws = $service->create($this->owner, 'Original');
        $originalId = $ws->id;

        $service->archive($this->owner, $ws->id);
        $this->assertCount(0, $service->listForUser($this->owner));

        $created = $service->create($this->owner, 'Reborn');
        $this->assertNotSame($originalId, $created->id);
        $this->assertSame('Reborn', $created->name);
        $this->assertTrue($created->isActive());

        $list = $service->listForUser($this->owner);
        $this->assertCount(1, $list);
        $this->assertSame($created->id, $list->first()->id);

        $this->assertDatabaseHas('archivd_workspaces', [
            'id' => $originalId,
            'status' => 'archived',
        ]);
    }

    public function test_list_excludes_archived_joined_workspace(): void
    {
        $this->grantPaidMain($this->owner);
        $ws = app(WorkspaceService::class)->create($this->owner, 'Soon Gone');

        $invite = app(WorkspaceService::class)->invite(
            $this->owner,
            $ws->id,
            $this->editor->email,
            WorkspaceRole::Editor->value,
        );
        app(WorkspaceService::class)->acceptInvite($this->editor, $invite->token);

        app(WorkspaceService::class)->archive($this->owner, $ws->id);

        $this->assertCount(0, app(WorkspaceService::class)->listForUser($this->editor));
        $this->assertCount(0, app(WorkspaceService::class)->listForUser($this->owner));
    }

    public function test_api_workspace_includes_my_role_and_is_owner(): void
    {
        $this->grantPaidMain($this->owner);

        $created = $this->actingAsJwt($this->owner)
            ->postJson('/api/v1/archivd/workspaces', ['name' => 'Role Payload'])
            ->assertCreated()
            ->json('data');

        $this->assertTrue($created['is_owner']);
        $this->assertSame('owner', $created['my_role']);

        $invite = $this->actingAsJwt($this->owner)
            ->postJson('/api/v1/archivd/workspaces/'.$created['id'].'/invites', [
                'email' => $this->editor->email,
                'role' => 'editor',
            ])
            ->assertCreated()
            ->json('data');

        $this->actingAsJwt($this->editor)
            ->postJson('/api/v1/archivd/workspaces/invites/accept', [
                'token' => $invite['token'],
            ])
            ->assertOk();

        $list = $this->actingAsJwt($this->editor)
            ->getJson('/api/v1/archivd/workspaces')
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $list);
        $this->assertFalse($list[0]['is_owner']);
        $this->assertSame('editor', $list[0]['my_role']);
    }

    public function test_workspace_list_batches_member_role_lookup(): void
    {
        $this->grantPaidMain($this->owner);

        $ids = [];
        for ($i = 1; $i <= 3; $i++) {
            $created = $this->actingAsJwt($this->owner)
                ->postJson('/api/v1/archivd/workspaces', ['name' => 'Batch '.$i])
                ->assertCreated()
                ->json('data');
            $ids[] = $created['id'];

            $invite = $this->actingAsJwt($this->owner)
                ->postJson('/api/v1/archivd/workspaces/'.$created['id'].'/invites', [
                    'email' => $this->editor->email,
                    'role' => 'editor',
                ])
                ->assertCreated()
                ->json('data');

            $this->actingAsJwt($this->editor)
                ->postJson('/api/v1/archivd/workspaces/invites/accept', [
                    'token' => $invite['token'],
                ])
                ->assertOk();
        }

        $memberQueries = 0;
        DB::listen(function ($query) use (&$memberQueries): void {
            if (str_contains((string) $query->sql, 'archivd_workspace_members')) {
                $memberQueries++;
            }
        });

        $list = $this->actingAsJwt($this->editor)
            ->getJson('/api/v1/archivd/workspaces')
            ->assertOk()
            ->json('data');

        $this->assertCount(3, $list);
        $this->assertSame(['editor', 'editor', 'editor'], collect($list)->pluck('my_role')->all());
        $this->assertLessThanOrEqual(2, $memberQueries);
    }

    public function test_rename_and_delete_log_activity(): void
    {
        $this->grantPaidMain($this->owner);
        $ws = app(WorkspaceService::class)->create($this->owner, 'Activity');

        $folder = app(FolderService::class)->create($this->owner, 'Old', null, null, $ws->id);
        app(FolderService::class)->rename($this->owner, $folder->id, 'New', null, $ws->id);
        app(FolderService::class)->softDelete($this->owner, $folder->id, null, $ws->id);

        $events = app(WorkspaceService::class)->activity($this->owner, $ws->id)
            ->pluck('event')
            ->all();

        $this->assertContains('folder_renamed', $events);
        $this->assertContains('folder_soft_deleted', $events);
    }

    public function test_api_workspace_endpoints(): void
    {
        $this->grantPaidMain($this->owner);

        $created = $this->actingAsJwt($this->owner)
            ->postJson('/api/v1/archivd/workspaces', ['name' => 'API Team'])
            ->assertCreated()
            ->json('data');

        $this->actingAsJwt($this->owner)
            ->getJson('/api/v1/archivd/workspaces')
            ->assertOk()
            ->assertJsonPath('data.0.id', $created['id']);

        $invite = $this->actingAsJwt($this->owner)
            ->postJson('/api/v1/archivd/workspaces/'.$created['id'].'/invites', [
                'email' => $this->editor->email,
                'role' => 'editor',
            ])
            ->assertCreated()
            ->json('data');

        $this->actingAsJwt($this->editor)
            ->postJson('/api/v1/archivd/workspaces/invites/accept', [
                'token' => $invite['token'],
            ])
            ->assertOk();

        $this->actingAsJwt($this->editor)
            ->postJson('/api/v1/archivd/folders', [
                'name' => 'Via API',
                'workspace_id' => $created['id'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.workspace_id', $created['id'])
            ->assertJsonPath('data.user_id', $this->owner->id);

        $browse = $this->actingAsJwt($this->editor)
            ->getJson('/api/v1/archivd/browse?workspace_id='.$created['id'])
            ->assertOk()
            ->json('data');

        $this->assertSame($created['id'], $browse['workspace_id']);
        $this->assertCount(1, $browse['folders']);

        $this->actingAsJwt($this->owner)
            ->getJson('/api/v1/archivd/workspaces/'.$created['id'].'/activity')
            ->assertOk()
            ->assertJsonStructure(['data' => [['event', 'properties']]]);
    }

    public function test_invited_free_member_can_upload_to_team_workspace(): void
    {
        // Paid owner shares a team workspace; the invited member is FREE.
        // Content collaboration (upload) only needs mutate access — no paid gate.
        $this->grantPaidMain($this->owner);
        $ws = app(WorkspaceService::class)->create($this->owner, 'Collab');

        $invite = app(WorkspaceService::class)->invite(
            $this->owner,
            $ws->id,
            $this->editor->email,
            WorkspaceRole::Editor->value,
        );
        // editor has NO paid entitlement
        app(WorkspaceService::class)->acceptInvite($this->editor, $invite->token);

        $folder = app(FolderService::class)->create($this->editor, 'Docs', null, null, $ws->id);

        $uploads = app(DirectUploadService::class);
        $init = $uploads->initiate(
            $this->editor,
            'member-doc.pdf',
            'application/pdf',
            1024,
            $folder->id,
            null,
            null,
            $ws->id,
        );
        $complete = $uploads->complete($this->editor, $init['upload']->id);

        $this->assertSame($this->editor->id, $complete['file']->created_by);
        $this->assertSame($this->owner->id, $complete['file']->user_id);
    }

    private function grantPaidMain(User $user): void
    {
        $service = Service::query()->where('code', 'archivd.plan.1tb.monthly')->first()
            ?? Service::factory()->ArchivdMain1Tb()->create();

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

    private function grantWorkspaceSlots(User $user, int $quantity = 1): void
    {
        $service = Service::query()->where('code', 'archivd.addon.workspace-slot.monthly')->first()
            ?? Service::factory()->ArchivdAddonWorkspaceSlot()->create();

        ServiceEntitlement::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'service_id' => $service->id,
            ],
            [
                'status' => EntitlementStatus::Active,
                'quantity' => max(1, $quantity),
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

    private function actingAsJwt(User $user): static
    {
        $token = auth('api')->login($user);

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }
}
