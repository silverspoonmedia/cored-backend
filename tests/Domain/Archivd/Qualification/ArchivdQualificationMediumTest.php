<?php

namespace Tests\Domain\Archivd\Qualification;

use App\Domain\Archivd\Enums\WorkspaceRole;
use App\Domain\Archivd\Models\HostedFile;
use App\Domain\Archivd\Services\DirectUploadService;
use App\Domain\Archivd\Services\FolderService;
use App\Domain\Archivd\Services\QuotaService;
use App\Domain\Archivd\Services\WorkspaceService;
use App\Domain\Archivd\Support\WorkspaceActivityRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\Domain\Archivd\Qualification\Concerns\QualificationHarness;
use Tests\TestCase;

class ArchivdQualificationMediumTest extends TestCase
{
    use QualificationHarness;
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpQualification();
    }

    public function test_f_h_m01_team_upload_bills_owner_quota(): void
    {
        $owner = $this->userWithRole('User', 'owner-m01@example.com');
        $editor = $this->userWithRole('User', 'editor-m01@example.com');
        $this->grantPaidMain($owner);

        $ws = app(WorkspaceService::class)->create($owner, 'Bill Owner');
        $invite = app(WorkspaceService::class)->invite($owner, $ws->id, $editor->email, WorkspaceRole::Editor->value);
        app(WorkspaceService::class)->acceptInvite($editor, $invite->token);

        $ownerBefore = app(QuotaService::class)->usage($owner->id)['used_bytes'];
        $editorBefore = app(QuotaService::class)->usage($editor->id)['used_bytes'];

        $init = app(DirectUploadService::class)->initiate(
            $editor,
            'team.pdf',
            'application/pdf',
            4096,
            null,
            null,
            null,
            $ws->id,
        );
        $complete = app(DirectUploadService::class)->complete($editor, $init['upload']->id);
        $file = $complete['file'];

        $this->assertSame($owner->id, $file->user_id);
        $this->assertSame($editor->id, $file->created_by);
        $this->assertSame($ws->id, $file->workspace_id);
        $this->assertSame($ownerBefore + 4096, app(QuotaService::class)->usage($owner->id)['used_bytes']);
        $this->assertSame($editorBefore, app(QuotaService::class)->usage($editor->id)['used_bytes']);
    }

    public function test_f_h_m02_free_user_joins_paid_team(): void
    {
        $owner = $this->userWithRole('User', 'owner-m02@example.com');
        $free = $this->userWithRole('User', 'free-m02@example.com');
        $this->grantPaidMain($owner);

        $ws = app(WorkspaceService::class)->create($owner, 'Paid Team');
        $invite = app(WorkspaceService::class)->invite($owner, $ws->id, $free->email, WorkspaceRole::Editor->value);
        app(WorkspaceService::class)->acceptInvite($free, $invite->token);

        $this->assertContains($ws->id, app(WorkspaceService::class)->listForUser($free)->pluck('id')->all());

        $this->actingAsJwt($free)
            ->postJson('/api/v1/archivd/workspaces', ['name' => 'Own'])
            ->assertStatus(422);
    }

    public function test_f_h_m03_own_a_join_b_role_isolation(): void
    {
        $a = $this->userWithRole('User', 'a-m03@example.com');
        $b = $this->userWithRole('User', 'b-m03@example.com');
        $this->grantPaidMain($a);
        $this->grantPaidMain($b);

        $wsA = app(WorkspaceService::class)->create($a, 'WA');
        $wsB = app(WorkspaceService::class)->create($b, 'WB');

        $invite = app(WorkspaceService::class)->invite($a, $wsA->id, $b->email, WorkspaceRole::Viewer->value);
        app(WorkspaceService::class)->acceptInvite($b, $invite->token);

        app(FolderService::class)->create($a, 'PersonalA', null, null, null);
        app(FolderService::class)->create($a, 'TeamA', null, null, $wsA->id);

        $this->actingAsJwt($b)
            ->postJson('/api/v1/archivd/folders', [
                'name' => 'ViewerNope',
                'workspace_id' => $wsA->id,
            ])
            ->assertForbidden();

        app(FolderService::class)->create($b, 'InOwnB', null, null, $wsB->id);

        $browsePersonalB = app(FolderService::class)->browse($b, null, null, null);
        $personalNames = collect($browsePersonalB['folders'])->pluck('name')->all();
        $this->assertNotContains('PersonalA', $personalNames);
        $this->assertNotContains('TeamA', $personalNames);
    }

    public function test_f_h_m04_search_scoped_to_workspace(): void
    {
        $owner = $this->userWithRole('User', 'owner-m04@example.com');
        $this->grantPaidMain($owner);
        $ws = app(WorkspaceService::class)->create($owner, 'Search Scope');

        $this->seedFile($owner, 'scope-hit.pdf', 100);
        $this->seedFile($owner, 'scope-hit.pdf', 100, ['workspace_id' => $ws->id, 'name' => 'scope-hit-team.pdf']);

        $personal = $this->actingAsJwt($owner)
            ->getJson('/api/v1/archivd/search?q=scope-hit')
            ->assertOk()
            ->json('data');
        $personalNames = collect($personal)->pluck('name')->all();
        $this->assertContains('scope-hit.pdf', $personalNames);
        $this->assertNotContains('scope-hit-team.pdf', $personalNames);

        $team = $this->actingAsJwt($owner)
            ->getJson('/api/v1/archivd/search?q=scope-hit&workspace_id='.$ws->id)
            ->assertOk()
            ->json('data');
        $teamNames = collect($team)->pluck('name')->all();
        $this->assertContains('scope-hit-team.pdf', $teamNames);
        $this->assertNotContains('scope-hit.pdf', $teamNames);
    }

    public function test_f_h_m05_bulk_visibility_max_100(): void
    {
        $user = $this->userWithRole('User');
        $this->grantPaidMain($user);
        $ids = [];
        for ($i = 0; $i < 101; $i++) {
            $ids[] = $this->seedFile($user, 'bulk-'.$i.'.txt', 10)->id;
        }

        $this->actingAsJwt($user)
            ->postJson('/api/v1/archivd/files/bulk-visibility', [
                'ids' => $ids,
                'visibility' => 'anyone_with_link',
            ])
            ->assertStatus(422);

        $ok = array_slice($ids, 0, 100);
        $this->actingAsJwt($user)
            ->postJson('/api/v1/archivd/files/bulk-visibility', [
                'ids' => $ok,
                'visibility' => 'anyone_with_link',
            ])
            ->assertOk();
    }

    public function test_f_h_m06_bulk_soft_delete(): void
    {
        $alice = $this->userWithRole('User', 'alice-m06@example.com');
        $bob = $this->userWithRole('User', 'bob-m06@example.com');
        $a1 = $this->seedFile($alice, 'a1.pdf');
        $a2 = $this->seedFile($alice, 'a2.pdf');
        $b1 = $this->seedFile($bob, 'b1.pdf');

        $this->actingAsJwt($alice)
            ->deleteJson('/api/v1/archivd/files/bulk', [
                'ids' => [$a1->id, $a2->id, $b1->id],
            ])
            ->assertOk();

        $this->assertSoftDeleted('archivd_files', ['id' => $a1->id]);
        $this->assertSoftDeleted('archivd_files', ['id' => $a2->id]);
        $this->assertNull($b1->fresh()->deleted_at);
    }

    public function test_f_h_m07_multi_region_init_disabled_block(): void
    {
        config([
            'archivd.regions' => ['ce' => 'local', 'us' => 'local'],
            'archivd.disabled_regions' => ['us'],
        ]);

        $user = $this->userWithRole('User');

        $this->actingAsJwt($user)
            ->postJson('/api/v1/archivd/uploads', [
                'name' => 'x.pdf',
                'mime' => 'application/pdf',
                'size' => 100,
                'disk' => 'us',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['disk']);
    }

    public function test_f_h_m08_disabled_region_still_downloads(): void
    {
        config([
            'archivd.regions' => ['ce' => 'local'],
            'archivd.disabled_regions' => ['ce'],
        ]);

        $user = $this->userWithRole('User');
        $file = $this->seedFile($user, 'old-region.pdf', 100, ['disk' => 'local']);
        Storage::disk('local')->put('archivd/'.$user->id.'/'.$file->path, 'x');

        $this->actingAsJwt($user)
            ->getJson('/api/v1/archivd/files/'.$file->id.'/download')
            ->assertOk()
            ->assertJsonStructure(['data' => ['url', 'expires_at']]);
    }

    public function test_f_h_m09_force_delete_removes_s3_object(): void
    {
        $user = $this->userWithRole('User');
        $file = $this->seedFile($user, 'force.pdf');
        $key = 'archivd/'.$user->id.'/'.$file->path;
        Storage::disk('local')->put($key, 'payload');

        $this->actingAsJwt($user)
            ->deleteJson('/api/v1/archivd/files/'.$file->id)
            ->assertOk();

        $this->actingAsJwt($user)
            ->deleteJson('/api/v1/archivd/files/'.$file->id.'/force')
            ->assertOk();

        $this->assertNull(HostedFile::withTrashed()->find($file->id));
        Storage::disk('local')->assertMissing($key);
    }

    public function test_f_h_m10_workspace_activity_membership_and_file_ops(): void
    {
        $owner = $this->userWithRole('User', 'owner-m10@example.com');
        $editor = $this->userWithRole('User', 'editor-m10@example.com');
        $this->grantPaidMain($owner);

        $ws = app(WorkspaceService::class)->create($owner, 'Activity Feed');
        $invite = app(WorkspaceService::class)->invite($owner, $ws->id, $editor->email, WorkspaceRole::Editor->value);
        app(WorkspaceService::class)->acceptInvite($editor, $invite->token);

        app(FolderService::class)->create($editor, 'Shared', null, null, $ws->id);
        $init = app(DirectUploadService::class)->initiate(
            $editor,
            'act.pdf',
            'application/pdf',
            512,
            null,
            null,
            null,
            $ws->id,
        );
        app(DirectUploadService::class)->complete($editor, $init['upload']->id);

        $events = app(WorkspaceService::class)->activity($owner, $ws->id)->pluck('event')->all();
        $this->assertNotEmpty($events);

        $this->assertTrue(
            Activity::query()
                ->where('log_name', WorkspaceActivityRecorder::LOG_NAME)
                ->where('subject_id', $ws->id)
                ->exists()
        );
    }
}
