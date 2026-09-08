<?php

namespace Tests\Domain\Archivd\Qualification;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\Domain\Archivd\Qualification\Concerns\QualificationHarness;
use Tests\TestCase;

class ArchivdQualificationLowTest extends TestCase
{
    use QualificationHarness;
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpQualification();
    }

    public function test_f_h_l01_personal_browse_empty_tree(): void
    {
        $user = $this->userWithRole('User');

        $data = $this->actingAsJwt($user)
            ->getJson('/api/v1/archivd/browse')
            ->assertOk()
            ->json('data');

        $this->assertSame($user->id, $data['owner_id']);
        $this->assertSame([], $data['folders'] ?? $data['folders'] ?? []);
        $this->assertEmpty($data['files'] ?? []);
        $this->assertNull($data['breadcrumb'][0]['id'] ?? null);
    }

    public function test_f_h_l02_create_folder_and_rename(): void
    {
        $user = $this->userWithRole('User');

        $folder = $this->actingAsJwt($user)
            ->postJson('/api/v1/archivd/folders', ['name' => 'Docs'])
            ->assertCreated()
            ->json('data');

        $renamed = $this->actingAsJwt($user)
            ->patchJson('/api/v1/archivd/folders/'.$folder['id'].'/rename', ['name' => 'Documents'])
            ->assertOk()
            ->json('data');

        $this->assertSame($folder['id'], $renamed['id']);
        $this->assertSame('Documents', $renamed['name']);
    }

    public function test_f_h_l03_single_part_upload_complete(): void
    {
        $user = $this->userWithRole('User');
        $result = $this->uploadComplete($user, 'report.pdf', 1024);

        $this->assertMatchesRegularExpression('/^[a-z0-9]{40}$/', $result['path']);
        $this->assertSame($result['path'], $result['file']['path']);
        Storage::disk('local')->assertExists('archivd/'.$user->id.'/'.$result['file']['path']);
    }

    public function test_f_h_l04_auth_download_returns_url(): void
    {
        $user = $this->userWithRole('User');
        $file = $this->seedFile($user, 'dl.pdf');

        $data = $this->actingAsJwt($user)
            ->getJson('/api/v1/archivd/files/'.$file->id.'/download')
            ->assertOk()
            ->json('data');

        $this->assertNotEmpty($data['url']);
        $this->assertArrayHasKey('expires_at', $data);
        $this->assertSame(1, $file->fresh()->download_count);
    }

    public function test_f_h_l05_free_quota_under_limit(): void
    {
        $user = $this->userWithRole('User');

        $before = $this->actingAsJwt($user)
            ->getJson('/api/v1/archivd/quota')
            ->assertOk()
            ->json('data');

        $this->assertSame('free', $before['state']);
        $this->assertGreaterThan(0, $before['remaining_bytes']);

        $this->uploadComplete($user, 'small.pdf', 2048);

        $after = $this->actingAsJwt($user)
            ->getJson('/api/v1/archivd/quota')
            ->assertOk()
            ->json('data');

        $this->assertSame($before['used_bytes'] + 2048, $after['used_bytes']);
        $this->assertLessThan($before['remaining_bytes'], $after['remaining_bytes']);
    }

    public function test_f_h_l06_search_own_files(): void
    {
        $alice = $this->userWithRole('User', 'alice-l06@example.com');
        $bob = $this->userWithRole('User', 'bob-l06@example.com');
        $this->seedFile($alice, 'unique-alpha-report.pdf');
        $this->seedFile($bob, 'unique-alpha-secret.pdf');

        $hits = $this->actingAsJwt($alice)
            ->getJson('/api/v1/archivd/search?q=unique-alpha')
            ->assertOk()
            ->json('data');

        $names = collect($hits)->pluck('name')->all();
        $this->assertContains('unique-alpha-report.pdf', $names);
        $this->assertNotContains('unique-alpha-secret.pdf', $names);
    }

    public function test_f_h_l07_soft_delete_to_trash(): void
    {
        $user = $this->userWithRole('User');
        $file = $this->seedFile($user, 'trash-me.pdf');
        Storage::disk('local')->put('archivd/'.$user->id.'/'.$file->path, 'x');

        $this->actingAsJwt($user)
            ->deleteJson('/api/v1/archivd/files/'.$file->id)
            ->assertOk();

        $this->assertSoftDeleted('archivd_files', ['id' => $file->id]);
        Storage::disk('local')->assertExists('archivd/'.$user->id.'/'.$file->path);

        $trash = $this->actingAsJwt($user)
            ->getJson('/api/v1/archivd/trash')
            ->assertOk()
            ->json('data');

        $ids = collect($trash['files'] ?? $trash)->pluck('id')->all();
        $this->assertContains($file->id, $ids);
    }

    public function test_f_h_l08_restore_from_trash(): void
    {
        $user = $this->userWithRole('User');
        $file = $this->seedFile($user, 'restore-me.pdf');

        $this->actingAsJwt($user)
            ->deleteJson('/api/v1/archivd/files/'.$file->id)
            ->assertOk();

        $this->actingAsJwt($user)
            ->postJson('/api/v1/archivd/files/'.$file->id.'/restore')
            ->assertOk();

        $this->assertNull($file->fresh()->deleted_at);
        $this->actingAsJwt($user)
            ->getJson('/api/v1/archivd/browse')
            ->assertOk()
            ->assertJsonFragment(['name' => 'restore-me.pdf']);
    }

    public function test_f_h_l10_free_user_workspace_list_empty(): void
    {
        $user = $this->userWithRole('User');

        $this->actingAsJwt($user)
            ->getJson('/api/v1/archivd/workspaces')
            ->assertOk()
            ->assertJsonPath('data', []);

        $this->actingAsJwt($user)
            ->postJson('/api/v1/archivd/workspaces', ['name' => 'Nope'])
            ->assertStatus(422);
    }
}
