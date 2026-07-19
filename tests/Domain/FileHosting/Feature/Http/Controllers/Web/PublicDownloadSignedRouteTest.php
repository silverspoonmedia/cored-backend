<?php

namespace Tests\Domain\FileHosting\Feature\Http\Controllers\Web;

use App\Domain\FileHosting\Models\HostedFile;
use App\Domain\FileHosting\Services\DirectUploadService;
use App\Domain\FileHosting\Services\SignedDownloadService;
use App\Domain\Identity\Models\User;
use Database\Seeders\FileHostingRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\Fakes\FakeFileHostingDirectUploadService;
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
        $this->seed(FileHostingRoleSeeder::class);

        config([
            'file-hosting.disk' => 'local',
            'file-hosting.public_download_signature_ttl_minutes' => 5,
        ]);

        Storage::fake('local');

        $this->app->instance(
            DirectUploadService::class,
            $this->app->make(FakeFileHostingDirectUploadService::class),
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
            'file-hosting.public.files.download',
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
            'file-hosting.public.files.download',
            now()->addMinutes(5),
            ['file' => $file->id],
        );

        $this->get($signed.'x')
            ->assertForbidden();
    }

    private function sharedFile(): HostedFile
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);
        $user->assignRole('User');

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
