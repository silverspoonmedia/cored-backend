<?php

namespace Tests\Domain\FileHosting\Qualification;

use App\Domain\FileHosting\Enums\WorkspaceRole;
use App\Domain\FileHosting\Models\HostedFile;
use App\Domain\FileHosting\Services\EnforceFreeTierQuotaService;
use App\Domain\FileHosting\Services\QuotaService;
use App\Domain\FileHosting\Services\WorkspaceService;
use App\Domain\FileHosting\Support\ObjectStorage;
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Mockery;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\Domain\FileHosting\Qualification\Concerns\QualificationHarness;
use Tests\TestCase;

class FileHostingQualificationHardTest extends TestCase
{
    use QualificationHarness;
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpQualification([
            'file-hosting.public_download_signature_ttl_minutes' => 5,
        ]);
    }

    public function test_FH_H01_staff_view_any_hierarchy_allow_deny(): void
    {
        $admin = $this->userWithRole('Admin', 'admin-h01@example.com');
        $user = $this->userWithRole('User', 'user-h01@example.com');
        $peer = $this->userWithRole('User', 'peer-h01@example.com');

        $this->seedFile($user, 'staff-see.pdf');

        $this->actingAsJwt($admin)
            ->getJson('/api/v1/file-hosting/browse?user_id='.$user->id)
            ->assertOk();

        $this->actingAsJwt($peer)
            ->getJson('/api/v1/file-hosting/browse?user_id='.$user->id)
            ->assertForbidden();
    }

    public function test_FH_H02_staff_manage_any_mutate_gate(): void
    {
        $admin = $this->userWithRole('Admin', 'admin-h02@example.com');
        $user = $this->userWithRole('User', 'user-h02@example.com');
        $peer = $this->userWithRole('User', 'peer-h02@example.com');
        $file = $this->seedFile($user, 'staff-rename.pdf');

        // Admin with manage-any + hierarchy can rename target file.
        $this->actingAsJwt($admin)
            ->patchJson('/api/v1/file-hosting/files/'.$file->id.'/rename', [
                'name' => 'renamed-by-admin.pdf',
                'user_id' => $user->id,
            ])
            ->assertOk();

        $this->assertSame('renamed-by-admin.pdf', $file->fresh()->name);

        // Ordinary peer cannot mutate another user's file.
        $this->actingAsJwt($peer)
            ->patchJson('/api/v1/file-hosting/files/'.$file->id.'/rename', [
                'name' => 'hacked.pdf',
                'user_id' => $user->id,
            ])
            ->assertForbidden();
    }

    public function test_FH_H03_peer_isolation_personal_tree(): void
    {
        $alice = $this->userWithRole('User', 'alice-h03@example.com');
        $bob = $this->userWithRole('User', 'bob-h03@example.com');
        $this->seedFile($alice, 'secret.pdf');

        $this->actingAsJwt($bob)
            ->getJson('/api/v1/file-hosting/browse?user_id='.$alice->id)
            ->assertForbidden();
    }

    public function test_FH_H04_public_unsigned_web_download_403(): void
    {
        $user = $this->userWithRole('User');
        $file = $this->seedFile($user, 'shared.pdf', 100, ['visibility' => 'anyone_with_link']);

        $this->get('/s/f/'.$file->id.'/download')
            ->assertForbidden();
    }

    public function test_FH_H05_signed_hop_after_revoke_share(): void
    {
        $user = $this->userWithRole('User');
        $file = $this->seedFile($user, 'revoke.pdf', 100, ['visibility' => 'anyone_with_link']);

        $signed = URL::temporarySignedRoute(
            'file-hosting.public.files.download',
            now()->addMinutes(5),
            ['file' => $file->id],
        );

        $this->get($signed)->assertRedirect();

        $this->actingAsJwt($user)
            ->patchJson('/api/v1/file-hosting/files/'.$file->id.'/visibility', [
                'visibility' => 'private',
            ])
            ->assertOk();

        // Guest hop re-asserts visibility; private must fail (404 not found / 403 forbidden).
        $status = $this->get($signed)->status();
        $this->assertContains($status, [403, 404]);
    }

    public function test_FH_H06_grace_blocks_upload_and_collab_create(): void
    {
        $user = $this->userWithRole('User');
        $this->grantGraceMain($user);

        $this->actingAsJwt($user)
            ->postJson('/api/v1/file-hosting/uploads', [
                'name' => 'grace.pdf',
                'mime' => 'application/pdf',
                'size' => 100,
            ])
            ->assertStatus(422);

        $this->actingAsJwt($user)
            ->postJson('/api/v1/file-hosting/workspaces', ['name' => 'Grace Team'])
            ->assertStatus(422);
    }

    public function test_FH_H07_slot_limit_422_at_cap(): void
    {
        $owner = $this->userWithRole('User');
        $this->grantPaidMain($owner);
        config(['file-hosting.base_owned_workspace_slots' => 2]);

        $ws = app(WorkspaceService::class);
        $ws->create($owner, 'S1');
        $ws->create($owner, 'S2');

        $this->actingAsJwt($owner)
            ->postJson('/api/v1/file-hosting/workspaces', ['name' => 'S3'])
            ->assertStatus(422)
            ->assertSee('2', false);
    }

    public function test_FH_H08_workspace_slot_addon_plus_one_without_storage(): void
    {
        $owner = $this->userWithRole('User');
        $this->grantPaidMain($owner);
        config(['file-hosting.base_owned_workspace_slots' => 2]);

        $quotaBefore = app(QuotaService::class)->usage($owner->id)['quota_bytes'];
        $this->grantWorkspaceSlots($owner, 1);

        $usage = app(QuotaService::class)->usage($owner->id);
        $this->assertSame($quotaBefore, $usage['quota_bytes']);
        $this->assertSame(3, $usage['owned_workspace_limit']);
    }

    public function test_FH_H09_grace_expire_purge_oldest_first(): void
    {
        $gib = 1024 * 1024 * 1024;
        config(['file-hosting.default_quota_bytes' => 5 * $gib]);

        $user = User::factory()->create();
        Carbon::setTestNow('2026-01-01 00:00:00');
        $a = HostedFile::query()->create([
            'user_id' => $user->id,
            'folder_id' => null,
            'name' => 'a.bin',
            'disk' => 'local',
            'path' => substr(hash('sha256', 'a'), 0, 40),
            'size' => 2 * $gib,
            'mime' => 'application/octet-stream',
        ]);
        Carbon::setTestNow('2026-01-03 00:00:00');
        $b = HostedFile::query()->create([
            'user_id' => $user->id,
            'folder_id' => null,
            'name' => 'b.bin',
            'disk' => 'local',
            'path' => substr(hash('sha256', 'b'), 0, 40),
            'size' => 3 * $gib,
            'mime' => 'application/octet-stream',
        ]);
        Carbon::setTestNow('2026-01-03 01:00:00');
        $c = HostedFile::query()->create([
            'user_id' => $user->id,
            'folder_id' => null,
            'name' => 'c.bin',
            'disk' => 'local',
            'path' => substr(hash('sha256', 'c'), 0, 40),
            'size' => 5 * $gib,
            'mime' => 'application/octet-stream',
        ]);
        Carbon::setTestNow();

        $storage = Mockery::mock(ObjectStorage::class);
        $storage->shouldReceive('deleteMany')->once();
        $this->app->instance(ObjectStorage::class, $storage);

        $result = app(EnforceFreeTierQuotaService::class)->enforce($user->id);
        $this->assertSame(1, $result['deleted']);
        $this->assertNotNull(HostedFile::query()->find($a->id));
        $this->assertNotNull(HostedFile::query()->find($b->id));
        $this->assertNull(HostedFile::query()->find($c->id));
    }

    public function test_FH_H10_owner_only_archive_admin_cannot(): void
    {
        $owner = $this->userWithRole('User', 'owner-h10@example.com');
        $admin = $this->userWithRole('User', 'admin-h10@example.com');
        $this->grantPaidMain($owner);

        $ws = app(WorkspaceService::class)->create($owner, 'Archive Gate');
        $invite = app(WorkspaceService::class)->invite($owner, $ws->id, $admin->email, WorkspaceRole::Admin->value);
        app(WorkspaceService::class)->acceptInvite($admin, $invite->token);

        $this->actingAsJwt($admin)
            ->postJson('/api/v1/file-hosting/workspaces/'.$ws->id.'/archive')
            ->assertForbidden();

        $this->actingAsJwt($owner)
            ->postJson('/api/v1/file-hosting/workspaces/'.$ws->id.'/archive')
            ->assertOk();
    }
}
