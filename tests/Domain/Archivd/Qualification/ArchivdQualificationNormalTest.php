<?php

namespace Tests\Domain\Archivd\Qualification;

use App\Domain\Archivd\Enums\WorkspaceRole;
use App\Domain\Archivd\Models\FileUpload;
use App\Domain\Archivd\Services\QuotaService;
use App\Domain\Archivd\Services\WorkspaceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\Domain\Archivd\Qualification\Concerns\QualificationHarness;
use Tests\TestCase;

class ArchivdQualificationNormalTest extends TestCase
{
    use QualificationHarness;
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpQualification([
            'archivd.multipart_threshold_bytes' => 100,
            'archivd.chunk_size_bytes' => 100,
        ]);
    }

    public function test_f_h_n01_multipart_init_sign_complete(): void
    {
        $user = $this->userWithRole('User');
        $size = 250;

        $init = $this->actingAsJwt($user)
            ->postJson('/api/v1/archivd/uploads', [
                'name' => 'big.pdf',
                'mime' => 'application/pdf',
                'size' => $size,
            ])
            ->assertCreated()
            ->json('data');

        $this->assertSame(FileUpload::STRATEGY_MULTIPART, $init['strategy']);
        $this->assertGreaterThan(1, $init['part_count']);

        $this->actingAsJwt($user)
            ->postJson('/api/v1/archivd/uploads/'.$init['upload_id'].'/sign-parts', [
                'part_numbers' => [1, 2],
            ])
            ->assertOk();

        $complete = $this->actingAsJwt($user)
            ->postJson('/api/v1/archivd/uploads/'.$init['upload_id'].'/complete', [
                'parts' => [
                    ['part_number' => 1, 'etag' => 'e1', 'size' => 100],
                    ['part_number' => 2, 'etag' => 'e2', 'size' => 150],
                ],
            ])
            ->assertOk()
            ->json('data');

        $this->assertSame('big.pdf', $complete['file']['name']);
        $this->assertSame(FileUpload::STATUS_COMPLETED, FileUpload::query()->find($init['upload_id'])->status);
    }

    public function test_f_h_n02_display_name_auto_rename_on_complete(): void
    {
        $user = $this->userWithRole('User');
        $this->seedFile($user, 'report.pdf');

        $result = $this->uploadComplete($user, 'report.pdf', 512);
        $this->assertSame('report (2).pdf', $result['file']['name']);
        $this->assertMatchesRegularExpression('/^[a-z0-9]{40}$/', $result['file']['path']);
    }

    public function test_f_h_n03_manual_rename_collision_reject(): void
    {
        $user = $this->userWithRole('User');
        $a = $this->seedFile($user, 'A.pdf');
        $b = $this->seedFile($user, 'B.pdf');

        $this->actingAsJwt($user)
            ->patchJson('/api/v1/archivd/files/'.$b->id.'/rename', ['name' => 'A.pdf'])
            ->assertStatus(422);

        $this->assertSame('B.pdf', $b->fresh()->name);
        $this->assertSame('A.pdf', $a->fresh()->name);
    }

    public function test_f_h_n04_visibility_anyone_with_link_public_show(): void
    {
        $user = $this->userWithRole('User');
        $this->grantPaidMain($user);
        $file = $this->seedFile($user, 'share.pdf');

        $this->actingAsJwt($user)
            ->patchJson('/api/v1/archivd/files/'.$file->id.'/visibility', [
                'visibility' => 'anyone_with_link',
            ])
            ->assertOk()
            ->assertJsonPath('data.visibility', 'anyone_with_link');

        $this->getJson('/api/v1/archivd/public/files/'.$file->id)
            ->assertOk()
            ->assertJsonPath('data.name', 'share.pdf');

        $private = $this->seedFile($user, 'private.pdf');
        $this->getJson('/api/v1/archivd/public/files/'.$private->id)
            ->assertNotFound();
    }

    public function test_f_h_n05_folder_link_inherit_private_child(): void
    {
        $user = $this->userWithRole('User');
        $this->grantPaidMain($user);

        $folder = $this->actingAsJwt($user)
            ->postJson('/api/v1/archivd/folders', ['name' => 'SharedFolder'])
            ->assertCreated()
            ->json('data');

        $this->actingAsJwt($user)
            ->patchJson('/api/v1/archivd/folders/'.$folder['id'].'/visibility', [
                'visibility' => 'anyone_with_link',
            ])
            ->assertOk();

        $file = $this->seedFile($user, 'child.pdf', 100, [
            'folder_id' => $folder['id'],
            'visibility' => 'private',
        ]);

        $this->getJson('/api/v1/archivd/public/files/'.$file->id.'/download')
            ->assertOk();
    }

    public function test_f_h_n06_paid_create_workspace_under_base_slots(): void
    {
        $owner = $this->userWithRole('User');
        $this->grantPaidMain($owner);

        $ws = $this->actingAsJwt($owner)
            ->postJson('/api/v1/archivd/workspaces', ['name' => 'Team One'])
            ->assertCreated()
            ->json('data');

        $this->assertTrue($ws['is_owner']);
        $usage = app(QuotaService::class)->usage($owner->id);
        $this->assertSame(1, $usage['owned_workspace_used']);
        $this->assertGreaterThanOrEqual(10, $usage['owned_workspace_limit']);
    }

    public function test_f_h_n07_invite_editor_and_accept(): void
    {
        $owner = $this->userWithRole('User', 'owner-n07@example.com');
        $editor = $this->userWithRole('User', 'editor-n07@example.com');
        $this->grantPaidMain($owner);

        $ws = app(WorkspaceService::class)->create($owner, 'Invite Team');
        $invite = app(WorkspaceService::class)->invite(
            $owner,
            $ws->id,
            $editor->email,
            WorkspaceRole::Editor->value,
        );

        $member = app(WorkspaceService::class)->acceptInvite($editor, $invite->token);
        $this->assertSame(WorkspaceRole::Editor, $member->role);

        $ids = app(WorkspaceService::class)->listForUser($editor)->pluck('id')->all();
        $this->assertContains($ws->id, $ids);
    }

    public function test_f_h_n08_viewer_cannot_mutate(): void
    {
        $owner = $this->userWithRole('User', 'owner-n08@example.com');
        $viewer = $this->userWithRole('User', 'viewer-n08@example.com');
        $this->grantPaidMain($owner);

        $ws = app(WorkspaceService::class)->create($owner, 'View Only');
        $invite = app(WorkspaceService::class)->invite($owner, $ws->id, $viewer->email, WorkspaceRole::Viewer->value);
        app(WorkspaceService::class)->acceptInvite($viewer, $invite->token);

        $this->actingAsJwt($viewer)
            ->postJson('/api/v1/archivd/folders', [
                'name' => 'Nope',
                'workspace_id' => $ws->id,
            ])
            ->assertForbidden();
    }

    public function test_f_h_n09_admin_can_manage_members(): void
    {
        $owner = $this->userWithRole('User', 'owner-n09@example.com');
        $admin = $this->userWithRole('User', 'admin-n09@example.com');
        $target = $this->userWithRole('User', 'target-n09@example.com');
        $this->grantPaidMain($owner);

        $ws = app(WorkspaceService::class)->create($owner, 'Admin Manage');
        $inviteAdmin = app(WorkspaceService::class)->invite($owner, $ws->id, $admin->email, WorkspaceRole::Admin->value);
        app(WorkspaceService::class)->acceptInvite($admin, $inviteAdmin->token);

        $invite = $this->actingAsJwt($admin)
            ->postJson('/api/v1/archivd/workspaces/'.$ws->id.'/invites', [
                'email' => $target->email,
                'role' => 'editor',
            ])
            ->assertCreated()
            ->json('data');

        $this->actingAsJwt($target)
            ->postJson('/api/v1/archivd/workspaces/invites/accept', ['token' => $invite['token']])
            ->assertOk();

        $this->actingAsJwt($admin)
            ->deleteJson('/api/v1/archivd/workspaces/'.$ws->id.'/members/'.$target->id)
            ->assertOk();
    }

    public function test_f_h_n10_archive_frees_slot_create_inserts_new_row(): void
    {
        $owner = $this->userWithRole('User');
        $this->grantPaidMain($owner);
        config(['archivd.base_owned_workspace_slots' => 1]);

        $service = app(WorkspaceService::class);
        $ws = $service->create($owner, 'Original');
        $originalId = $ws->id;

        $service->archive($owner, $ws->id);
        $created = $service->create($owner, 'Reborn');

        $this->assertNotSame($originalId, $created->id);
        $this->assertDatabaseHas('archivd_workspaces', [
            'id' => $originalId,
            'status' => 'archived',
        ]);
    }
}
