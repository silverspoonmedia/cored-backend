<?php

namespace Tests\Domain\Archivd\Feature\Http\Controllers\Web;

use App\Domain\Archivd\Models\HostedFile;
use App\Domain\Archivd\Services\DirectUploadService;
use App\Domain\Archivd\Services\SignedDownloadService;
use App\Domain\Identity\Models\User;
use App\Domain\Service\Enums\EntitlementStatus;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Models\ServiceEntitlement;
use Database\Seeders\ArchivdRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\Fakes\FakeArchivdDirectUploadService;
use Tests\Fakes\FakeSignedDownloadService;
use Tests\TestCase;

class PublicDownloadSignedRouteTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->seed(ArchivdRoleSeeder::class);

        config([
            'archivd.disk' => 'local',
            'archivd.public_download_signature_ttl_minutes' => 5,
        ]);

        Storage::fake('local');

        $this->app->instance(
            DirectUploadService::class,
            $this->app->make(FakeArchivdDirectUploadService::class),
        );
        $this->app->instance(
            SignedDownloadService::class,
            $this->app->make(FakeSignedDownloadService::class),
        );
    }

    public function test_unsigned_public_download_url_is_forbidden(): void
    {
        $file = $this->sharedFile();

        $this->get('/s/f/'.$file->id.'/download')
            ->assertForbidden();
    }

    public function test_signed_public_download_redirects_to_storage(): void
    {
        $file = $this->sharedFile();

        $signed = URL::temporarySignedRoute(
            'archivd.public.files.download',
            now()->addMinutes(5),
            ['file' => $file->id],
        );

        $response = $this->get($signed)->assertRedirect();

        $location = $response->headers->get('Location');
        $this->assertNotEmpty($location);
        $this->assertStringContainsString('fake-storage.test', (string) $location);
        $this->assertSame(1, $file->fresh()->download_count);
    }

    public function test_tampered_signature_is_forbidden(): void
    {
        $file = $this->sharedFile();

        $signed = URL::temporarySignedRoute(
            'archivd.public.files.download',
            now()->addMinutes(5),
            ['file' => $file->id],
        );

        $this->get($signed.'x')
            ->assertForbidden();
    }

    public function test_public_file_show_returns_json_when_accepts_json(): void
    {
        $file = $this->sharedFile();

        $this->getJson('/s/f/'.$file->id)
            ->assertOk()
            ->assertJsonPath('data.file.id', $file->id)
            ->assertJsonPath('data.file.name', $file->name)
            ->assertJsonStructure([
                'data' => [
                    'file' => ['id', 'name', 'mime', 'size'],
                    'downloadUrl',
                ],
            ]);
    }

    public function test_public_file_show_json_404_for_private_file(): void
    {
        $file = $this->sharedFile();
        $file->forceFill(['visibility' => 'private'])->save();

        $this->getJson('/s/f/'.$file->id)
            ->assertNotFound();
    }

    private function sharedFile(): HostedFile
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);
        $user->assignRole('User');

        $service = Service::query()->where('code', 'archivd.plan.1tb.monthly')->first()
            ?? Service::factory()->archivdMain1Tb()->create();

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

        return HostedFile::query()->create([
            'user_id' => $user->id,
            'folder_id' => null,
            'name' => 'shared.pdf',
            'disk' => 'local',
            'path' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
            'size' => 10,
            'mime' => 'application/pdf',
            'visibility' => 'anyone_with_link',
        ]);
    }
}
