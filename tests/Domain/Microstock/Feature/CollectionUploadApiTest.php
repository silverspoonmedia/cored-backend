<?php

namespace Tests\Domain\Microstock\Feature;

use App\Domain\Archivd\Enums\CollectionStatus;
use App\Domain\Archivd\Models\Folder;
use App\Domain\Archivd\Models\HostedFile;
use App\Domain\Identity\Models\User;
use App\Domain\Microstock\Enums\MetadataStatus;
use App\Domain\Microstock\Jobs\UploadCollectionToMarketplace;
use App\Domain\Microstock\Models\AssetMetadata;
use App\Domain\Microstock\Models\MarketplaceCredential;
use App\Domain\Microstock\Services\CredentialService;
use App\Domain\Microstock\Services\MicrostockService;
use App\Domain\Service\Enums\EntitlementStatus;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServiceEntitlement;
use App\Domain\Service\Support\MicrostockCatalog;
use Database\Seeders\ArchivdRoleSeeder;
use Database\Seeders\MicrostockRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Tests\Concerns\GrantsMicrostockAiCredits;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * POST/GET /api/v1/microstock/collections/{folder}/upload
 * (decision: docs/domains/microstock/reference-marketplace-upload.md).
 */
class CollectionUploadApiTest extends TestCase
{
    use GrantsMicrostockAiCredits;
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->seed(ArchivdRoleSeeder::class);
        $this->seed(MicrostockRoleSeeder::class);

        config([
            'archivd.disk' => 'local',
            'activitylog.enabled' => false,
            'microstock.upload.enabled' => true,
        ]);

        $this->owner = $this->userWithRole('User', 'owner@example.com');
        $this->grantMicrostockFree($this->owner);
    }

    public function test_upload_queues_a_transfer_and_returns_202(): void
    {
        Bus::fake();

        $folder = $this->collection(CollectionStatus::Review);
        $file = $this->file($folder, 'a.jpg', 2048);
        $this->finalize($file, 'Title A', ['one']);
        $credential = $this->credential();

        $this->actingAsJwt($this->owner)
            ->postJson("/api/v1/microstock/collections/{$folder->id}/upload", [
                'credential_id' => $credential->id,
            ])
            ->assertStatus(202)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', __('api/microstock.upload_queued'))
            ->assertJsonPath('data.marketplace', 'adobe_stock')
            ->assertJsonPath('data.status', 'queued')
            ->assertJsonPath('data.remote_path', '/incoming')
            ->assertJsonPath('data.file_count', 1)
            ->assertJsonPath('data.byte_count', 2048);

        Bus::assertDispatched(UploadCollectionToMarketplace::class);
        $this->assertDatabaseCount('microstock_marketplace_uploads', 1);
    }

    public function test_upload_status_is_null_before_the_first_attempt(): void
    {
        $folder = $this->collection(CollectionStatus::Review);

        $this->actingAsJwt($this->owner)
            ->getJson("/api/v1/microstock/collections/{$folder->id}/upload")
            ->assertOk()
            ->assertJsonPath('message', __('api/microstock.upload_status_loaded'))
            ->assertJsonPath('data', null);
    }

    public function test_upload_status_returns_the_latest_attempt(): void
    {
        Bus::fake();

        $folder = $this->collection(CollectionStatus::Review);
        $file = $this->file($folder, 'a.jpg');
        $this->finalize($file, 'Title A', ['one']);
        $credential = $this->credential();

        $this->actingAsJwt($this->owner)
            ->postJson("/api/v1/microstock/collections/{$folder->id}/upload", [
                'credential_id' => $credential->id,
            ])
            ->assertStatus(202);

        $this->actingAsJwt($this->owner)
            ->getJson("/api/v1/microstock/collections/{$folder->id}/upload")
            ->assertOk()
            ->assertJsonPath('data.status', 'queued')
            ->assertJsonPath('data.marketplace', 'adobe_stock');
    }

    public function test_upload_is_refused_before_the_collection_is_curated(): void
    {
        $folder = $this->collection(CollectionStatus::Locked);
        $credential = $this->credential();

        $this->actingAsJwt($this->owner)
            ->postJson("/api/v1/microstock/collections/{$folder->id}/upload", [
                'credential_id' => $credential->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', __('api/microstock.upload_not_ready'));
    }

    public function test_upload_is_refused_when_the_switch_is_off(): void
    {
        config(['microstock.upload.enabled' => false]);

        $folder = $this->collection(CollectionStatus::Review);
        $credential = $this->credential();

        $this->actingAsJwt($this->owner)
            ->postJson("/api/v1/microstock/collections/{$folder->id}/upload", [
                'credential_id' => $credential->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', __('api/microstock.upload_disabled'));
    }

    public function test_upload_requires_a_credential_id(): void
    {
        $folder = $this->collection(CollectionStatus::Review);

        $this->actingAsJwt($this->owner)
            ->postJson("/api/v1/microstock/collections/{$folder->id}/upload", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('credential_id');
    }

    public function test_upload_rejects_a_foreign_collection(): void
    {
        $other = $this->userWithRole('User', 'other@example.com');

        $folder = $this->collection(CollectionStatus::Review);
        $credential = $this->credential();

        $this->actingAsJwt($other)
            ->postJson("/api/v1/microstock/collections/{$folder->id}/upload", [
                'credential_id' => $credential->id,
            ])
            ->assertStatus(403);
    }

    private function credential(): MarketplaceCredential
    {
        return app(CredentialService::class)->store(
            $this->owner,
            'adobe_stock',
            'main',
            [
                'host' => 'sftp.contributor.adobestock.com',
                'username' => 'u',
                'password' => 'p',
                'remote_path' => '/incoming',
            ],
        );
    }

    private function collection(CollectionStatus $status): Folder
    {
        $ws = app(MicrostockService::class)->workspaceFor($this->owner);

        $folder = Folder::query()->create([
            'user_id' => $this->owner->id,
            'workspace_id' => $ws->id,
            'parent_id' => null,
            'name' => 'Collection',
            'visibility' => 'private',
        ]);

        $folder->markCollectionStatus($status);

        return $folder->refresh();
    }

    private function file(Folder $folder, string $name, int $size = 1024): HostedFile
    {
        return HostedFile::query()->create([
            'user_id' => $this->owner->id,
            'workspace_id' => $folder->workspace_id,
            'folder_id' => $folder->id,
            'name' => $name,
            'mime' => 'image/jpeg',
            'size' => $size,
            'disk' => 'local',
            'path' => strtolower(Str::random(40)),
            'visibility' => 'private',
        ]);
    }

    /**
     * @param  list<string>  $keywords
     */
    private function finalize(HostedFile $file, string $title, array $keywords): AssetMetadata
    {
        return AssetMetadata::query()->create([
            'file_id' => $file->id,
            'user_id' => $this->owner->id,
            'status' => MetadataStatus::Finalized,
            'title' => $title,
            'keywords' => $keywords,
        ]);
    }

    private function actingAsJwt(User $user): static
    {
        $token = auth('api')->login($user);

        return $this->withHeader('Authorization', 'Bearer '.$token);
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

        $this->grantMicrostockAiCredits($user);
    }
}
