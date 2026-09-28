<?php

namespace Tests\Domain\Microstock\Unit\Services;

use App\Domain\Archivd\Enums\CollectionStatus;
use App\Domain\Archivd\Models\Folder;
use App\Domain\Archivd\Models\HostedFile;
use App\Domain\Identity\Models\User;
use App\Domain\Microstock\Enums\MetadataStatus;
use App\Domain\Microstock\Exceptions\MicrostockException;
use App\Domain\Microstock\Jobs\UploadCollectionToMarketplace;
use App\Domain\Microstock\Models\AssetMetadata;
use App\Domain\Microstock\Models\MarketplaceCredential;
use App\Domain\Microstock\Services\CredentialService;
use App\Domain\Microstock\Services\MarketplaceUploadService;
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
 * The plan is what the job transfers and what the marketplace matches by
 * filename, so it is asserted directly instead of through the transport
 * (decision: docs/domains/microstock/reference-marketplace-upload.md).
 */
class MarketplaceUploadServiceTest extends TestCase
{
    use GrantsMicrostockAiCredits;
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private User $owner;

    private MarketplaceUploadService $uploads;

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

        $this->uploads = app(MarketplaceUploadService::class);
    }

    public function test_profile_unknown_marketplace_fails_closed(): void
    {
        $this->expectException(MicrostockException::class);
        $this->expectExceptionMessage(__('api/microstock.upload_marketplace_unsupported', ['marketplace' => 'shutterstock']));

        $this->uploads->profile('shutterstock');
    }

    public function test_queue_is_refused_while_the_upload_switch_is_off(): void
    {
        config(['microstock.upload.enabled' => false]);

        $folder = $this->collection(CollectionStatus::Review);
        $credential = $this->credential('adobe_stock');

        $this->expectException(MicrostockException::class);
        $this->expectExceptionMessage(__('api/microstock.upload_disabled'));

        $this->uploads->queue($this->owner, $folder->id, $credential->id);
    }

    public function test_queue_is_refused_before_the_collection_is_curated(): void
    {
        $folder = $this->collection(CollectionStatus::Locked);
        $credential = $this->credential('adobe_stock');

        $this->expectException(MicrostockException::class);
        $this->expectExceptionMessage(__('api/microstock.upload_not_ready'));

        $this->uploads->queue($this->owner, $folder->id, $credential->id);
    }

    public function test_queue_is_refused_when_no_metadata_is_finalized(): void
    {
        $folder = $this->collection(CollectionStatus::Review);
        $this->file($folder, 'a.jpg'); // file without metadata
        $credential = $this->credential('adobe_stock');

        $this->expectException(MicrostockException::class);
        $this->expectExceptionMessage(__('api/microstock.upload_nothing_finalized'));

        $this->uploads->queue($this->owner, $folder->id, $credential->id);
    }

    public function test_queue_dispatches_one_job_and_records_the_plan(): void
    {
        Bus::fake();

        $folder = $this->collection(CollectionStatus::Review);
        $file = $this->file($folder, 'a.jpg', 2048);
        $this->finalize($file, 'Title A', ['one', 'two']);
        $credential = $this->credential('adobe_stock', ['remote_path' => '/incoming']);

        $upload = $this->uploads->queue($this->owner, $folder->id, $credential->id);

        $this->assertSame('adobe_stock', $upload->marketplace);
        $this->assertSame('queued', $upload->status->value);
        $this->assertSame('/incoming', $upload->remote_path);
        $this->assertSame(1, $upload->file_count);
        $this->assertSame(2048, $upload->byte_count);
        $this->assertSame($this->owner->id, $upload->user_id);
        $this->assertSame($credential->id, $upload->credential_id);

        Bus::assertDispatched(
            UploadCollectionToMarketplace::class,
            fn ($job) => true,
        );
    }

    public function test_plan_matches_finalized_rows_and_renders_the_manifest(): void
    {
        $folder = $this->collection(CollectionStatus::Review);
        $finalized = $this->file($folder, 'a.jpg', 1024);
        $draft = $this->file($folder, 'b.jpg', 4096);

        $this->finalize($finalized, 'Title A', ['one', 'two'], 'Description A', 'Illustration');
        AssetMetadata::query()->create([
            'file_id' => $draft->id,
            'user_id' => $this->owner->id,
            'status' => MetadataStatus::Generated,
            'title' => 'Draft B',
            'keywords' => ['x'],
        ]);

        $credential = $this->credential('adobe_stock');
        $plan = $this->uploads->plan($folder, $credential, $this->uploads->profile('adobe_stock'));

        $this->assertSame(['a.jpg'], array_column($plan['files'], 'name'));
        $this->assertSame(1024, $plan['bytes']);
        $this->assertSame('metadata.csv', $plan['manifest_name']);

        $lines = array_map('str_getcsv', explode("\n", trim($plan['manifest_contents'])));
        $this->assertSame(['Filename', 'Title', 'Keywords', 'Category'], $lines[0]);
        $this->assertSame(['a.jpg', 'Title A', 'one, two', '1'], $lines[1]);
    }

    public function test_plan_refuses_a_filename_the_marketplace_cannot_match(): void
    {
        $folder = $this->collection(CollectionStatus::Review);
        $file = $this->file($folder, 'a file.jpg');
        $this->finalize($file, 'Title', ['k']);

        $credential = $this->credential('adobe_stock');

        $this->expectException(MicrostockException::class);
        $this->expectExceptionMessage(__('api/microstock.upload_filename_invalid', ['name' => 'a file.jpg']));

        $this->uploads->plan($folder, $credential, $this->uploads->profile('adobe_stock'));
    }

    public function test_plan_refuses_two_files_with_the_same_name(): void
    {
        $folder = $this->collection(CollectionStatus::Review);

        foreach (['a.jpg', 'a.jpg'] as $name) {
            $file = $this->file($folder, $name);
            $this->finalize($file, 'Title', ['k']);
        }

        $credential = $this->credential('adobe_stock');

        $this->expectException(MicrostockException::class);
        $this->expectExceptionMessage(__('api/microstock.upload_filename_duplicate', ['name' => 'a.jpg']));

        $this->uploads->plan($folder, $credential, $this->uploads->profile('adobe_stock'));
    }

    public function test_remote_path_prefers_the_credential_override(): void
    {
        $folder = $this->collection(CollectionStatus::Review);
        $file = $this->file($folder, 'a.jpg');
        $this->finalize($file, 'Title', ['k']);

        $profile = $this->uploads->profile('adobe_stock');

        $default = $this->uploads->plan($folder, $this->credential('adobe_stock'), $profile);
        $this->assertSame('/', $default['remote_path']);

        $override = $this->uploads->plan(
            $folder,
            $this->credential('adobe_stock', ['remote_path' => '/drop/2026'], 'override'),
            $profile,
        );
        $this->assertSame('/drop/2026', $override['remote_path']);
    }

    public function test_latest_for_returns_the_newest_attempt(): void
    {
        Bus::fake();

        $folder = $this->collection(CollectionStatus::Review);
        $file = $this->file($folder, 'a.jpg');
        $this->finalize($file, 'Title', ['k']);
        $credential = $this->credential('adobe_stock');

        $this->assertNull($this->uploads->latestFor($this->owner, $folder->id));

        $first = $this->uploads->queue($this->owner, $folder->id, $credential->id);
        $first->forceFill(['created_at' => now()->subHour()])->save();

        $second = $this->uploads->queue($this->owner, $folder->id, $credential->id);

        $latest = $this->uploads->latestFor($this->owner, $folder->id);
        $this->assertNotNull($latest);
        $this->assertSame($second->id, $latest->id);
    }

    public function test_a_credential_belonging_to_another_user_is_a_404(): void
    {
        $other = $this->userWithRole('User', 'other@example.com');

        $folder = $this->collection(CollectionStatus::Review);
        $file = $this->file($folder, 'a.jpg');
        $this->finalize($file, 'Title', ['k']);

        $foreign = app(CredentialService::class)->store(
            $other,
            'adobe_stock',
            'theirs',
            ['host' => 'sftp.contributor.adobestock.com', 'username' => 'u', 'password' => 'p'],
        );

        $this->expectException(MicrostockException::class);
        $this->expectExceptionMessage(__('api/microstock.credential_not_found'));

        $this->uploads->queue($this->owner, $folder->id, $foreign->id);
    }

    /**
     * @param  array<string, mixed>  $secret
     */
    private function credential(string $marketplace, array $secret = [], string $label = 'main'): MarketplaceCredential
    {
        return app(CredentialService::class)->store(
            $this->owner,
            $marketplace,
            $label,
            $secret === []
                ? ['host' => 'sftp.contributor.adobestock.com', 'username' => 'u', 'password' => 'p']
                : $secret + ['host' => 'sftp.contributor.adobestock.com', 'username' => 'u', 'password' => 'p'],
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
    private function finalize(
        HostedFile $file,
        string $title,
        array $keywords,
        ?string $description = null,
        ?string $category = null,
    ): AssetMetadata {
        return AssetMetadata::query()->create([
            'file_id' => $file->id,
            'user_id' => $this->owner->id,
            'status' => MetadataStatus::Finalized,
            'title' => $title,
            'keywords' => $keywords,
            'description' => $description,
            'category' => $category,
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
