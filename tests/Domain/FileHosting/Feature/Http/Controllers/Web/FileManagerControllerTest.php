<?php

namespace Tests\Domain\FileHosting\Feature\Http\Controllers\Web;

use App\Domain\FileHosting\Services\FileService;
use App\Domain\Identity\Models\User;
use Database\Seeders\FileHostingRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * Web FileManagerController is a thin API consumer via ConsumesApi.
 *
 * These tests mock the API responses (Http::fake) to verify the controller
 * correctly forwards requests, renders views, and handles redirects.
 * Full-stack API tests live in FileHostingTest.
 */
class FileManagerControllerTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->seed(FileHostingRoleSeeder::class);

        config([
            'file-hosting.disk' => 'local',
            'file-hosting.default_quota_bytes' => 1024 * 1024,
        ]);

        Storage::fake('local');
    }

    public function test_index_renders_with_folders_and_files(): void
    {
        $user = $this->actingWithApiGuard(User::factory()->create());

        Http::fake([
            '*/api/v1/file-hosting/browse*' => Http::response([
                'status' => 'success',
                'data' => [
                    'owner_id' => fake()->uuid(),
                    'folder' => null,
                    'breadcrumb' => [],
                    'folders' => [['id' => fake()->uuid(), 'name' => 'Documents', 'visibility' => 'private']],
                    'files' => [['id' => fake()->uuid(), 'name' => 'report.pdf', 'mime' => 'application/pdf', 'size' => 1024, 'visibility' => 'private']],
                ],
            ], 200),
            '*/api/v1/file-hosting/quota*' => Http::response([
                'status' => 'success',
                'data' => [
                    'used_bytes' => 512,
                    'quota_bytes' => 1048576,
                    'remaining_bytes' => 1048064,
                    'state' => 'free',
                    'collaborative_works' => false,
                    'can_create_workspace' => false,
                    'owned_workspace_used' => 0,
                    'owned_workspace_limit' => 0,
                ],
            ], 200),
            '*/api/v1/file-hosting/workspaces' => Http::response([
                'status' => 'success',
                'data' => [],
            ], 200),
        ]);

        $response = $user->get('/file-hosting');

        $response->assertOk();
        $response->assertViewIs('adminlte4.file-hosting.index');
        $response->assertViewHas('browse');
        $response->assertViewHas('quota');
        $response->assertViewHas('planState', 'free');
        $response->assertViewHas('canCreateWorkspace', false);
        $response->assertSee(__('web/file-hosting.workspace_personal'));
        $response->assertSee(__('web/file-hosting.collab.free_cannot_create'));
        $response->assertSee(__('web/file-hosting.collab.upgrade_cta'));
    }

    public function test_index_paid_shows_create_workspace_cta(): void
    {
        $user = $this->actingWithApiGuard(User::factory()->create());

        Http::fake([
            '*/api/v1/file-hosting/browse*' => Http::response([
                'status' => 'success',
                'data' => [
                    'owner_id' => fake()->uuid(),
                    'folder' => null,
                    'breadcrumb' => [],
                    'folders' => [],
                    'files' => [],
                ],
            ], 200),
            '*/api/v1/file-hosting/quota*' => Http::response([
                'status' => 'success',
                'data' => [
                    'used_bytes' => 0,
                    'quota_bytes' => 1099511627776,
                    'remaining_bytes' => 1099511627776,
                    'state' => 'paid',
                    'collaborative_works' => true,
                    'can_create_workspace' => true,
                    'owned_workspace_used' => 0,
                    'owned_workspace_limit' => 10,
                ],
            ], 200),
            '*/api/v1/file-hosting/workspaces' => Http::response([
                'status' => 'success',
                'data' => [],
            ], 200),
        ]);

        $response = $user->get('/file-hosting');

        $response->assertOk();
        $response->assertViewHas('planState', 'paid');
        $response->assertViewHas('canCreateWorkspace', true);
        $response->assertViewHas('ownedWorkspaceLimit', 10);
        $response->assertSee(__('web/file-hosting.collab.create_workspace'));
        $response->assertSee(__('web/file-hosting.collab.slots_counter', ['used' => 0, 'limit' => 10]));
        $response->assertDontSee(__('web/file-hosting.collab.free_cannot_create'));
    }

    public function test_index_does_not_auto_provision_microstock_system_workspace(): void
    {
        $user = $this->actingWithApiGuard(User::factory()->create());

        Http::fake([
            '*/api/v1/file-hosting/browse*' => Http::response([
                'status' => 'success',
                'data' => [
                    'owner_id' => fake()->uuid(),
                    'folder' => null,
                    'breadcrumb' => [],
                    'folders' => [],
                    'files' => [],
                ],
            ], 200),
            '*/api/v1/file-hosting/quota*' => Http::response([
                'status' => 'success',
                'data' => [
                    'used_bytes' => 0,
                    'quota_bytes' => 1048576,
                    'remaining_bytes' => 1048576,
                    'state' => 'free',
                    'collaborative_works' => false,
                    'can_create_workspace' => false,
                    'owned_workspace_used' => 0,
                    'owned_workspace_limit' => 0,
                ],
            ], 200),
            '*/api/v1/file-hosting/workspaces' => Http::response([
                'status' => 'success',
                'data' => [],
            ], 200),
        ]);

        $user->get('/file-hosting')->assertOk();

        // Microstock is now activated explicitly via /services, not lazily on
        // the first File Hosting paint — no system workspace is created here.
        $this->assertDatabaseMissing('file_hosting_workspaces', [
            'kind' => 'microstock',
            'status' => 'active',
        ]);
    }

    public function test_store_workspace_redirects_into_team_context(): void
    {
        $user = $this->actingWithApiGuard(User::factory()->create());
        $workspaceId = fake()->uuid();

        Http::fake([
            '*/api/v1/file-hosting/workspaces' => Http::response([
                'status' => 'success',
                'message' => 'Workspace created.',
                'data' => [
                    'id' => $workspaceId,
                    'name' => 'Team workspace',
                    'owner_user_id' => $user->user->id ?? null,
                ],
            ], 201),
        ]);

        $user->post('/file-hosting/workspaces', ['name' => 'Team workspace'])
            ->assertRedirect(route('file-hosting.index', ['workspace_id' => $workspaceId]));
    }

    public function test_trash_renders_with_trashed_items(): void
    {
        $user = $this->actingWithApiGuard(User::factory()->create());

        Http::fake([
            '*/api/v1/file-hosting/trash*' => Http::response([
                'status' => 'success',
                'data' => [
                    'folders' => [['id' => fake()->uuid(), 'name' => 'Old Folder', 'deleted_at' => '2026-07-15']],
                    'files' => [['id' => fake()->uuid(), 'name' => 'old.pdf', 'mime' => 'application/pdf', 'deleted_at' => '2026-07-15']],
                ],
            ], 200),
        ]);

        $response = $user->get('/file-hosting/trash');

        $response->assertOk();
        $response->assertViewIs('adminlte4.file-hosting.trash');
        $response->assertViewHas('trash');
    }

    public function test_show_renders_file_detail(): void
    {
        $user = $this->actingWithApiGuard(User::factory()->create());
        $fileId = fake()->uuid();

        Http::fake([
            "*/api/v1/file-hosting/files/{$fileId}*" => Http::response([
                'status' => 'success',
                'data' => [
                    'id' => $fileId,
                    'name' => 'report.pdf',
                    'mime' => 'application/pdf',
                    'size' => 2048,
                    'visibility' => 'private',
                    'view_count' => 5,
                    'download_count' => 2,
                    'created_at' => '2026-07-15T00:00:00.000000Z',
                ],
            ], 200),
        ]);

        $response = $user->get("/file-hosting/files/{$fileId}");

        $response->assertOk();
        $response->assertViewIs('adminlte4.file-hosting.show');
        $response->assertViewHas('file');
    }

    public function test_store_folder_redirects_on_success(): void
    {
        $user = $this->actingWithApiGuard(User::factory()->create());

        Http::fake([
            '*/api/v1/file-hosting/folders*' => Http::response([
                'status' => 'success',
                'message' => 'Folder created.',
                'data' => ['id' => fake()->uuid(), 'name' => 'New Folder'],
            ], 201),
        ]);

        $user->post('/file-hosting/folders', ['name' => 'New Folder'])
            ->assertRedirect(route('file-hosting.index'));
    }

    public function test_destroy_folder_redirects_on_success(): void
    {
        $user = $this->actingWithApiGuard(User::factory()->create());
        $folderId = fake()->uuid();

        Http::fake([
            "*/api/v1/file-hosting/folders/{$folderId}*" => Http::response([
                'status' => 'success',
                'message' => 'Folder moved to trash.',
            ], 200),
        ]);

        $user->delete("/file-hosting/folders/{$folderId}")
            ->assertRedirect(route('file-hosting.index'));
    }

    public function test_destroy_file_redirects_on_success(): void
    {
        $user = $this->actingWithApiGuard(User::factory()->create());
        $fileId = fake()->uuid();

        Http::fake([
            "*/api/v1/file-hosting/files/{$fileId}*" => Http::response([
                'status' => 'success',
                'message' => 'File moved to trash.',
            ], 200),
        ]);

        $user->delete("/file-hosting/files/{$fileId}")
            ->assertRedirect(route('file-hosting.index'));
    }

    public function test_rename_file_redirects_on_success(): void
    {
        $user = $this->actingWithApiGuard(User::factory()->create());
        $fileId = fake()->uuid();

        Http::fake([
            "*/api/v1/file-hosting/files/{$fileId}/rename*" => Http::response([
                'status' => 'success',
                'message' => 'File renamed.',
                'data' => ['id' => $fileId, 'name' => 'renamed.pdf'],
            ], 200),
        ]);

        $user->patch("/file-hosting/files/{$fileId}/rename", ['name' => 'renamed.pdf'])
            ->assertRedirect(route('file-hosting.index'));
    }

    public function test_rename_folder_redirects_on_success(): void
    {
        $user = $this->actingWithApiGuard(User::factory()->create());
        $folderId = fake()->uuid();

        Http::fake([
            "*/api/v1/file-hosting/folders/{$folderId}/rename*" => Http::response([
                'status' => 'success',
                'message' => 'Folder renamed.',
                'data' => ['id' => $folderId, 'name' => 'renamed'],
            ], 200),
        ]);

        $user->patch("/file-hosting/folders/{$folderId}/rename", ['name' => 'renamed'])
            ->assertRedirect(route('file-hosting.index'));
    }

    public function test_set_file_visibility_redirects_back_on_success(): void
    {
        $user = $this->actingWithApiGuard(User::factory()->create());
        $fileId = fake()->uuid();

        Http::fake([
            "*/api/v1/file-hosting/files/{$fileId}/visibility*" => Http::response([
                'status' => 'success',
                'message' => 'Visibility updated.',
                'data' => ['id' => $fileId, 'visibility' => 'anyone_with_link'],
            ], 200),
        ]);

        $user->patch("/file-hosting/files/{$fileId}/visibility", ['visibility' => 'anyone_with_link'])
            ->assertRedirect();
    }

    public function test_set_folder_visibility_redirects_back_on_success(): void
    {
        $user = $this->actingWithApiGuard(User::factory()->create());
        $folderId = fake()->uuid();

        Http::fake([
            "*/api/v1/file-hosting/folders/{$folderId}/visibility*" => Http::response([
                'status' => 'success',
                'message' => 'Visibility updated.',
                'data' => ['id' => $folderId, 'visibility' => 'anyone_with_link'],
            ], 200),
        ]);

        $user->patch("/file-hosting/folders/{$folderId}/visibility", ['visibility' => 'anyone_with_link'])
            ->assertRedirect();
    }

    public function test_move_file_redirects_on_success(): void
    {
        $user = $this->actingWithApiGuard(User::factory()->create());
        $fileId = fake()->uuid();
        $targetFolderId = fake()->uuid();

        Http::fake([
            "*/api/v1/file-hosting/files/{$fileId}/move*" => Http::response([
                'status' => 'success',
                'message' => 'File moved.',
                'data' => ['id' => $fileId, 'folder_id' => $targetFolderId],
            ], 200),
        ]);

        $user->patch("/file-hosting/files/{$fileId}/move", ['folder_id' => $targetFolderId])
            ->assertRedirect(route('file-hosting.index', ['folder_id' => $targetFolderId]));
    }

    public function test_move_folder_redirects_on_success(): void
    {
        $user = $this->actingWithApiGuard(User::factory()->create());
        $folderId = fake()->uuid();
        $targetParentId = fake()->uuid();

        Http::fake([
            "*/api/v1/file-hosting/folders/{$folderId}/move*" => Http::response([
                'status' => 'success',
                'message' => 'Folder moved.',
                'data' => ['id' => $folderId, 'parent_id' => $targetParentId],
            ], 200),
        ]);

        $user->patch("/file-hosting/folders/{$folderId}/move", ['parent_id' => $targetParentId])
            ->assertRedirect(route('file-hosting.index', ['folder_id' => $targetParentId]));
    }

    public function test_restore_file_redirects_to_trash_on_success(): void
    {
        $user = $this->actingWithApiGuard(User::factory()->create());
        $fileId = fake()->uuid();

        Http::fake([
            "*/api/v1/file-hosting/files/{$fileId}/restore*" => Http::response([
                'status' => 'success',
                'message' => 'File restored.',
                'data' => ['id' => $fileId],
            ], 200),
        ]);

        $user->post("/file-hosting/files/{$fileId}/restore")
            ->assertRedirect(route('file-hosting.trash'));
    }

    public function test_restore_folder_redirects_to_trash_on_success(): void
    {
        $user = $this->actingWithApiGuard(User::factory()->create());
        $folderId = fake()->uuid();

        Http::fake([
            "*/api/v1/file-hosting/folders/{$folderId}/restore*" => Http::response([
                'status' => 'success',
                'message' => 'Folder restored.',
                'data' => ['id' => $folderId],
            ], 200),
        ]);

        $user->post("/file-hosting/folders/{$folderId}/restore")
            ->assertRedirect(route('file-hosting.trash'));
    }

    public function test_force_destroy_file_redirects_to_trash_on_success(): void
    {
        $user = $this->actingWithApiGuard(User::factory()->create());
        $fileId = fake()->uuid();

        Http::fake([
            "*/api/v1/file-hosting/files/{$fileId}/force*" => Http::response([
                'status' => 'success',
                'message' => 'File permanently deleted.',
            ], 200),
        ]);

        $user->delete("/file-hosting/files/{$fileId}/force")
            ->assertRedirect(route('file-hosting.trash'));
    }

    public function test_force_destroy_folder_redirects_to_trash_on_success(): void
    {
        $user = $this->actingWithApiGuard(User::factory()->create());
        $folderId = fake()->uuid();

        Http::fake([
            "*/api/v1/file-hosting/folders/{$folderId}/force*" => Http::response([
                'status' => 'success',
                'message' => 'Folder permanently deleted.',
            ], 200),
        ]);

        $user->delete("/file-hosting/folders/{$folderId}/force")
            ->assertRedirect(route('file-hosting.trash'));
    }

    public function test_search_returns_json_results(): void
    {
        $user = $this->actingWithApiGuard(User::factory()->create());

        Http::fake([
            '*/api/v1/file-hosting/search*' => Http::response([
                'status' => 'success',
                'data' => [
                    ['id' => fake()->uuid(), 'name' => 'report.pdf', 'mime' => 'application/pdf', 'size' => 1024],
                ],
            ], 200),
        ]);

        $user->getJson('/file-hosting/search?q=report')
            ->assertOk()
            ->assertJsonStructure([
                'status',
                'data' => [['id', 'name', 'mime', 'size']],
            ]);
    }

    public function test_download_redirects_to_storage_url(): void
    {
        $user = $this->actingWithApiGuard(User::factory()->create());
        $fileId = fake()->uuid();

        // Web hop no longer loops back over HTTP; it calls FileService directly.
        $files = \Mockery::mock(FileService::class);
        $files->shouldReceive('download')
            ->once()
            ->andReturn([
                'url' => 'https://fake-storage.test/download/'.$fileId,
                'expires_at' => now()->addMinutes(60)->toISOString(),
            ]);
        $this->app->instance(FileService::class, $files);

        $user->get("/file-hosting/files/{$fileId}/download")
            ->assertRedirect('https://fake-storage.test/download/'.$fileId);
    }

    public function test_index_redirects_to_dashboard_on_api_error(): void
    {
        $user = $this->actingWithApiGuard(User::factory()->create());

        Http::fake([
            '*/api/v1/file-hosting/browse*' => Http::response([
                'status' => 'error',
                'message' => 'Unauthorized.',
            ], 403),
            '*/api/v1/file-hosting/quota*' => Http::response([
                'status' => 'error',
                'message' => 'Unauthorized.',
            ], 403),
        ]);

        $user->get('/file-hosting')
            ->assertRedirect(route('dashboard'));
    }

    public function test_index_returns_json_when_request_expects_json(): void
    {
        $user = $this->actingWithApiGuard(User::factory()->create());

        $browseData = [
            'owner_id' => fake()->uuid(),
            'folder' => null,
            'breadcrumb' => [],
            'folders' => [['id' => fake()->uuid(), 'name' => 'Docs', 'visibility' => 'private']],
            'files' => [],
        ];

        Http::fake([
            '*/api/v1/file-hosting/browse*' => Http::response([
                'status' => 'success',
                'data' => $browseData,
            ], 200),
        ]);

        $user->getJson('/file-hosting')
            ->assertOk()
            ->assertJsonPath('data.folders.0.name', 'Docs');
    }

    public function test_index_returns_json_error_on_api_failure_when_expecting_json(): void
    {
        $user = $this->actingWithApiGuard(User::factory()->create());

        Http::fake([
            '*/api/v1/file-hosting/browse*' => Http::response([
                'status' => 'error',
                'message' => 'Unauthorized.',
            ], 403),
        ]);

        $user->getJson('/file-hosting')
            ->assertForbidden()
            ->assertJsonPath('message', 'Unauthorized.');
    }

    public function test_trash_returns_json_when_request_expects_json(): void
    {
        $user = $this->actingWithApiGuard(User::factory()->create());
        $folderId = fake()->uuid();

        Http::fake([
            '*/api/v1/file-hosting/trash*' => Http::response([
                'status' => 'success',
                'data' => [
                    'folders' => [['id' => $folderId, 'name' => 'Gone', 'deleted_at' => '2026-07-15']],
                    'files' => [],
                ],
            ], 200),
        ]);

        $user->getJson('/file-hosting/trash')
            ->assertOk()
            ->assertJsonPath('data.folders.0.id', $folderId)
            ->assertJsonPath('data.folders.0.name', 'Gone');
    }

    public function test_show_returns_json_when_request_expects_json(): void
    {
        $user = $this->actingWithApiGuard(User::factory()->create());
        $fileId = fake()->uuid();

        Http::fake([
            "*/api/v1/file-hosting/files/{$fileId}*" => Http::response([
                'status' => 'success',
                'data' => [
                    'id' => $fileId,
                    'name' => 'spa.pdf',
                    'mime' => 'application/pdf',
                    'size' => 4096,
                    'visibility' => 'private',
                    'view_count' => 1,
                    'download_count' => 0,
                ],
            ], 200),
        ]);

        $user->getJson("/file-hosting/files/{$fileId}")
            ->assertOk()
            ->assertJsonPath('data.id', $fileId)
            ->assertJsonPath('data.name', 'spa.pdf')
            ->assertJsonPath('data.visibility', 'private');
    }

    public function test_show_returns_json_error_when_api_fails(): void
    {
        $user = $this->actingWithApiGuard(User::factory()->create());
        $fileId = fake()->uuid();

        Http::fake([
            "*/api/v1/file-hosting/files/{$fileId}*" => Http::response([
                'status' => 'error',
                'message' => 'File not found.',
            ], 404),
        ]);

        $user->getJson("/file-hosting/files/{$fileId}")
            ->assertNotFound()
            ->assertJsonPath('message', 'File not found.');
    }

    public function test_workspaces_list_returns_json(): void
    {
        $user = $this->actingWithApiGuard(User::factory()->create());
        $wsId = fake()->uuid();

        Http::fake([
            '*/api/v1/file-hosting/workspaces' => Http::response([
                'status' => 'success',
                'data' => [
                    ['id' => $wsId, 'name' => 'Alpha', 'my_role' => 'owner', 'is_owner' => true],
                ],
            ], 200),
        ]);

        $user->getJson('/file-hosting/workspaces')
            ->assertOk()
            ->assertJsonPath('data.0.id', $wsId)
            ->assertJsonPath('data.0.name', 'Alpha');
    }

    public function test_mutation_json_includes_api_data_entity(): void
    {
        $user = $this->actingWithApiGuard(User::factory()->create());
        $folderId = fake()->uuid();

        Http::fake([
            '*/api/v1/file-hosting/folders' => Http::response([
                'status' => 'success',
                'message' => 'Folder created.',
                'data' => [
                    'id' => $folderId,
                    'name' => 'SPA Folder',
                    'visibility' => 'private',
                ],
            ], 201),
        ]);

        $user->postJson('/file-hosting/folders', ['name' => 'SPA Folder'])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.id', $folderId)
            ->assertJsonPath('data.name', 'SPA Folder');
    }

    public function test_init_upload_forwards_disk_param_to_api(): void
    {
        $user = $this->actingWithApiGuard(User::factory()->create());
        $captured = null;

        Http::fake(function (ClientRequest $request) use (&$captured) {
            if (str_contains($request->url(), '/api/v1/file-hosting/uploads')) {
                $captured = $request->data();

                return Http::response([
                    'status' => 'success',
                    'data' => [
                        'upload_id' => fake()->uuid(),
                        'strategy' => 'single',
                        'path' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                        'parts' => [],
                    ],
                ], 201);
            }

            return Http::response([], 200);
        });

        $user->post('/file-hosting/uploads', [
            'name' => 'report.pdf',
            'mime' => 'application/pdf',
            'size' => 1024,
            'disk' => 'ce',
        ])->assertStatus(201);

        $this->assertNotNull($captured);
        $this->assertSame('ce', $captured['disk'] ?? null);
    }

    public function test_bulk_file_visibility_forwards_to_api(): void
    {
        $user = $this->actingWithApiGuard(User::factory()->create());
        $captured = null;

        Http::fake(function (ClientRequest $request) use (&$captured) {
            if (str_contains($request->url(), '/api/v1/file-hosting/files/bulk-visibility')) {
                $captured = $request->data();

                return Http::response([
                    'status' => 'success',
                    'data' => ['affected' => 2],
                ], 200);
            }

            return Http::response([], 200);
        });

        $user->post('/file-hosting/files/bulk-visibility', [
            'ids' => [fake()->uuid(), fake()->uuid()],
            'visibility' => 'anyone_with_link',
        ])->assertOk();

        $this->assertNotNull($captured);
        $this->assertSame('anyone_with_link', $captured['visibility'] ?? null);
        $this->assertCount(2, $captured['ids'] ?? []);
    }

    public function test_bulk_file_destroy_forwards_to_api(): void
    {
        $user = $this->actingWithApiGuard(User::factory()->create());
        $captured = null;

        Http::fake(function (ClientRequest $request) use (&$captured) {
            if (str_contains($request->url(), '/api/v1/file-hosting/files/bulk')) {
                $captured = $request->data();

                return Http::response([
                    'status' => 'success',
                    'data' => ['affected' => 1],
                ], 200);
            }

            return Http::response([], 200);
        });

        $user->delete('/file-hosting/files/bulk', ['ids' => [fake()->uuid()]])
            ->assertOk();

        $this->assertNotNull($captured);
        $this->assertCount(1, $captured['ids'] ?? []);
    }

    private function actingWithApiGuard(User $user): static
    {
        return $this->actingAs($user, 'api');
    }
}
