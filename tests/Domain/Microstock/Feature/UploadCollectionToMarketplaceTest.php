<?php

namespace Tests\Domain\Microstock\Feature;

use App\Domain\Archivd\Enums\CollectionStatus;
use App\Domain\Archivd\Models\Folder;
use App\Domain\Archivd\Models\HostedFile;
use App\Domain\Identity\Models\User;
use App\Domain\Microstock\Contracts\MarketplaceTransport;
use App\Domain\Microstock\Enums\MarketplaceCredentialStatus;
use App\Domain\Microstock\Enums\MarketplaceUploadStatus;
use App\Domain\Microstock\Enums\MetadataStatus;
use App\Domain\Microstock\Exceptions\MarketplaceUploadException;
use App\Domain\Microstock\Jobs\UploadCollectionToMarketplace;
use App\Domain\Microstock\Models\AssetMetadata;
use App\Domain\Microstock\Models\MarketplaceCredential;
use App\Domain\Microstock\Models\MarketplaceUpload;
use App\Domain\Microstock\Services\CredentialService;
use App\Domain\Microstock\Services\MarketplaceUploadService;
use App\Domain\Microstock\Services\MicrostockService;
use App\Domain\Service\Enums\EntitlementStatus;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServiceEntitlement;
use App\Domain\Microstock\Support\MicrostockCatalog;
use Database\Seeders\ArchivdRoleSeeder;
use Database\Seeders\MicrostockRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Tests\Concerns\GrantsMicrostockAiCredits;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * The transfer itself is faked: the decision record keeps every suite off the
 * network, so what is asserted here is the state machine — the attempt row, the
 * credential status, and the fact that nothing local is deleted
 * (decision: docs/domains/microstock/reference-marketplace-upload.md).
 */
class UploadCollectionToMarketplaceTest extends TestCase
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

    public function test_a_successful_transfer_marks_the_attempt_and_credential(): void
    {
        $transport = $this->bindTransport();
        [$folder, $file, $credential] = $this->curatedCollection();
        $upload = $this->queued($folder, $credential);

        app(UploadCollectionToMarketplace::class, ['uploadId' => (string) $upload->id])
            ->handle($transport, app(MarketplaceUploadService::class));

        $this->assertSame(1, $transport->calls);
        $this->assertSame('/incoming', $transport->remotePath);
        $this->assertSame('metadata.csv', $transport->manifestName);
        $this->assertSame(['a.jpg'], array_column($transport->files, 'name'));

        $upload->refresh();
        $this->assertSame('uploaded', $upload->status->value);
        $this->assertNull($upload->error);
        $this->assertNotNull($upload->started_at);
        $this->assertNotNull($upload->finished_at);

        $credential->refresh();
        $this->assertSame(MarketplaceCredentialStatus::Verified, $credential->status);
        $this->assertNull($credential->last_error);
        $this->assertNotNull($credential->last_verified_at);
        $this->assertNotNull($credential->last_used_at);

        // Copy-out: the source file and its metadata row are untouched.
        $this->assertDatabaseHas('archivd_files', ['id' => $file->id]);
        $this->assertDatabaseHas('microstock_asset_metadata', [
            'file_id' => $file->id,
            'status' => MetadataStatus::Finalized->value,
        ]);
    }

    public function test_a_transport_failure_is_recorded_on_the_attempt_and_the_credential(): void
    {
        $transport = $this->bindTransport(
            new MarketplaceUploadException(__('api/microstock.upload_auth_failed')),
        );
        [$folder, , $credential] = $this->curatedCollection();
        $upload = $this->queued($folder, $credential);

        app(UploadCollectionToMarketplace::class, ['uploadId' => (string) $upload->id])
            ->handle($transport, app(MarketplaceUploadService::class));

        $upload->refresh();
        $this->assertSame('failed', $upload->status->value);
        $this->assertSame(__('api/microstock.upload_auth_failed'), $upload->error);
        $this->assertNotNull($upload->finished_at);

        $credential->refresh();
        $this->assertSame(MarketplaceCredentialStatus::Failed, $credential->status);
        $this->assertSame(__('api/microstock.upload_auth_failed'), $credential->last_error);
        $this->assertNotNull($credential->last_used_at);
    }

    public function test_a_terminal_attempt_is_not_transferred_twice(): void
    {
        $transport = $this->bindTransport();
        [$folder, , $credential] = $this->curatedCollection();
        $upload = $this->queued($folder, $credential);

        $upload->forceFill(['status' => MarketplaceUploadStatus::Uploaded])->save();

        app(UploadCollectionToMarketplace::class, ['uploadId' => (string) $upload->id])
            ->handle($transport, app(MarketplaceUploadService::class));

        $this->assertSame(0, $transport->calls);
    }

    public function test_a_batch_with_nothing_finalized_fails_without_touching_the_transport(): void
    {
        $transport = $this->bindTransport();
        [$folder, , $credential] = $this->curatedCollection();

        // The attempt is created while the row exists, then the row is unfinalized
        // before the worker runs — the same race the guard exists for.
        $upload = $this->queued($folder, $credential);
        AssetMetadata::query()->update(['status' => MetadataStatus::Generated]);

        app(UploadCollectionToMarketplace::class, ['uploadId' => (string) $upload->id])
            ->handle($transport, app(MarketplaceUploadService::class));

        $this->assertSame(0, $transport->calls);

        $upload->refresh();
        $this->assertSame('failed', $upload->status->value);
        $this->assertSame(__('api/microstock.upload_nothing_finalized'), $upload->error);
    }

    /**
     * A credential whose secret is missing its host is refused before a socket is
     * opened.
     */
    public function test_an_incomplete_credential_never_reaches_the_transport(): void
    {
        $transport = $this->bindTransport(
            new MarketplaceUploadException(__('api/microstock.upload_credential_incomplete')),
        );

        $folder = $this->collection(CollectionStatus::Review);
        $file = $this->file($folder, 'a.jpg');
        $this->finalize($file, 'Title', ['k']);

        $credential = app(CredentialService::class)->store(
            $this->owner,
            'adobe_stock',
            'incomplete',
            ['username' => 'u'],
        );

        $upload = $this->queued($folder, $credential);

        app(UploadCollectionToMarketplace::class, ['uploadId' => (string) $upload->id])
            ->handle($transport, app(MarketplaceUploadService::class));

        $upload->refresh();
        $this->assertSame('failed', $upload->status->value);
        $this->assertSame(__('api/microstock.upload_credential_incomplete'), $upload->error);
    }

    private function queued(Folder $folder, MarketplaceCredential $credential): MarketplaceUpload
    {
        Bus::fake();

        $upload = app(MarketplaceUploadService::class)->queue($this->owner, $folder->id, $credential->id);

        Bus::fake(); // keep the fake in place for the next queue() in the same test

        return $upload;
    }

    /**
     * @return array{0: Folder, 1: HostedFile, 2: MarketplaceCredential}
     */
    private function curatedCollection(): array
    {
        $folder = $this->collection(CollectionStatus::Review);
        $file = $this->file($folder, 'a.jpg', 2048);
        $this->finalize($file, 'Title A', ['one', 'two']);

        $credential = app(CredentialService::class)->store(
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

        return [$folder, $file, $credential];
    }

    private function bindTransport(?MarketplaceUploadException $failWith = null): MarketplaceTransport
    {
        $fake = new class($failWith) implements MarketplaceTransport
        {
            public int $calls = 0;

            public array $connection = [];

            public string $remotePath = '';

            public array $files = [];

            public string $manifestName = '';

            public string $manifestContents = '';

            public function __construct(private readonly ?MarketplaceUploadException $failWith) {}

            public function upload(
                array $connection,
                string $remotePath,
                array $files,
                string $manifestName,
                string $manifestContents,
            ): void {
                $this->calls++;
                $this->connection = $connection;
                $this->remotePath = $remotePath;
                $this->files = $files;
                $this->manifestName = $manifestName;
                $this->manifestContents = $manifestContents;

                if ($this->failWith !== null) {
                    throw $this->failWith;
                }
            }
        };

        $this->app->instance(MarketplaceTransport::class, $fake);

        return $fake;
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
