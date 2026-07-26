<?php

namespace Tests\Domain\Microstock\Feature;

use App\Domain\FileHosting\Enums\CollectionStatus;
use App\Domain\FileHosting\Models\Folder;
use App\Domain\FileHosting\Models\HostedFile;
use App\Domain\Identity\Models\User;
use App\Domain\Microstock\Enums\MetadataStatus;
use App\Domain\Microstock\Models\AssetMetadata;
use App\Domain\Microstock\Services\MicrostockService;
use App\Domain\Service\Enums\EntitlementStatus;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServiceEntitlement;
use App\Domain\Service\Support\MicrostockCatalog;
use Database\Seeders\FileHostingRoleSeeder;
use Database\Seeders\MicrostockRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class CollectionApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->seed(FileHostingRoleSeeder::class);
        $this->seed(MicrostockRoleSeeder::class);

        config([
            'file-hosting.disk' => 'local',
            'activitylog.enabled' => false,
            'microstock.csv_disk' => 'local',
        ]);

        $this->owner = $this->userWithRole('User', 'owner@example.com');
        $this->grantMicrostockFree($this->owner);
    }

    public function test_lock_endpoint_requires_hint_and_locks(): void
    {
        $folder = $this->collection();

        $this->actingAsJwt($this->owner)
            ->postJson("/api/v1/microstock/collections/{$folder->id}/lock", [])
            ->assertStatus(422);

        $this->actingAsJwt($this->owner)
            ->postJson("/api/v1/microstock/collections/{$folder->id}/lock", ['hint' => 'eco kitchen set'])
            ->assertOk()
            ->assertJsonPath('data.collection_status', CollectionStatus::Locked->value)
            ->assertJsonPath('data.collection_hint', 'eco kitchen set');
    }

    public function test_analyze_endpoint_transitions_and_dispatches(): void
    {
        Bus::fake();
        $folder = $this->collection(withFile: true);

        // Before lock -> 422.
        $this->actingAsJwt($this->owner)
            ->postJson("/api/v1/microstock/collections/{$folder->id}/analyze")
            ->assertStatus(422);

        $this->actingAsJwt($this->owner)
            ->postJson("/api/v1/microstock/collections/{$folder->id}/lock", ['hint' => 'hint'])
            ->assertOk();

        $this->actingAsJwt($this->owner)
            ->postJson("/api/v1/microstock/collections/{$folder->id}/analyze")
            ->assertStatus(202)
            ->assertJsonPath('data.collection_status', CollectionStatus::Processing->value);
    }

    public function test_status_endpoint_returns_progress(): void
    {
        $folder = $this->collection(withFile: true);

        $this->actingAsJwt($this->owner)
            ->getJson("/api/v1/microstock/collections/{$folder->id}/status")
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_export_and_download_endpoints(): void
    {
        Storage::fake('local');
        $folder = $this->collection(withFile: true);
        $file = HostedFile::query()->where('folder_id', $folder->id)->firstOrFail();

        AssetMetadata::query()->create([
            'file_id' => $file->id,
            'user_id' => $this->owner->id,
            'status' => MetadataStatus::Finalized,
            'title' => 'Exportable',
            'keywords' => ['k1', 'k2'],
        ]);

        // Not review yet.
        $this->actingAsJwt($this->owner)
            ->postJson("/api/v1/microstock/collections/{$folder->id}/export")
            ->assertStatus(422);

        $folder->markCollectionStatus(CollectionStatus::Review);

        $this->actingAsJwt($this->owner)
            ->postJson("/api/v1/microstock/collections/{$folder->id}/export")
            ->assertOk();

        $this->assertSame(CollectionStatus::Exported, $folder->refresh()->collection_status);

        $response = $this->actingAsJwt($this->owner)
            ->get("/api/v1/microstock/collections/{$folder->id}/download");
        $response->assertOk();
        $this->assertStringContainsString('Exportable', $response->getContent());
    }

    public function test_lock_endpoint_rejects_foreign_collection(): void
    {
        $other = $this->userWithRole('User', 'other@example.com');
        $folder = $this->collection();

        $this->actingAsJwt($other)
            ->postJson("/api/v1/microstock/collections/{$folder->id}/lock", ['hint' => 'x'])
            ->assertStatus(403);
    }

    private function collection(bool $withFile = false): Folder
    {
        $ws = app(MicrostockService::class)->workspaceFor($this->owner);

        $folder = Folder::query()->create([
            'user_id' => $this->owner->id,
            'workspace_id' => $ws->id,
            'parent_id' => null,
            'name' => 'Collection',
            'visibility' => 'private',
        ]);

        if ($withFile) {
            HostedFile::query()->create([
                'user_id' => $this->owner->id,
                'workspace_id' => $ws->id,
                'folder_id' => $folder->id,
                'name' => 'a.jpg',
                'mime' => 'image/jpeg',
                'size' => 1024,
                'disk' => 'local',
                'path' => strtolower(Str::random(40)),
                'visibility' => 'private',
            ]);
        }

        return $folder;
    }

    private function actingAsJwt(User $user): static
    {
        $token = auth('api')->login($user);

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }

    private function grantMicrostockFree(User $user): void
    {
        $service = Service::query()->where('code', MicrostockCatalog::CODE_PLAN_FREE)->first()
            ?? Service::factory()->microstockPlanFree()->create();

        ServiceEntitlement::query()->updateOrCreate(
            ['user_id' => $user->id, 'service_id' => $service->id],
            [
                'status' => EntitlementStatus::Active,
                'quantity' => 1,
                'starts_at' => now(),
                'ends_at' => null,
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
}
