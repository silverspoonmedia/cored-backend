<?php

namespace Tests\Feature\Http\Controllers\Web;

use App\Models\User;
use App\Support\Auth\AuthCookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class UserFormAjaxTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_store_returns_json_validation_errors_without_reload(): void
    {
        $admin = $this->userWithRole('Admin');

        Http::fake([
            '*/api/v1/users' => Http::response([
                'message' => 'Periksa kembali isian Anda.',
                'errors' => [
                    'email' => ['Format email tidak valid.'],
                    'name' => ['Nama wajib diisi.'],
                ],
            ], 422),
        ]);

        $response = $this->actingWithJwtCookie($admin)
            ->postJson('/users', [
                'name' => '',
                'email' => 'not-an-email',
                'password' => 'short',
                'password_confirmation' => 'short',
                'role' => 'User',
            ]);

        $response->assertUnprocessable()
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('errors.email.0', 'Format email tidak valid.')
            ->assertJsonPath('errors.name.0', 'Nama wajib diisi.');
    }

    public function test_update_stays_on_page_on_success(): void
    {
        $admin = $this->userWithRole('Admin');
        $target = User::factory()->create(['email' => 'edit-me@example.com']);
        $target->assignRole('User');

        Http::fake([
            '*/api/v1/users/'.$target->id => Http::response([
                'status' => 'success',
                'message' => 'Updated.',
                'data' => ['id' => $target->id],
            ], 200),
        ]);

        $response = $this->actingWithJwtCookie($admin)
            ->putJson('/users/'.$target->id, [
                'name' => 'Updated Name',
                'email' => 'edit-me@example.com',
                'role' => 'User',
            ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'Pengguna berhasil diperbarui.');

        $this->assertArrayNotHasKey('redirect', $response->json());
    }

    public function test_store_returns_json_redirect_on_success(): void
    {
        $admin = $this->userWithRole('Admin');

        Http::fake([
            '*/api/v1/users' => Http::response([
                'status' => 'success',
                'message' => 'User created.',
                'data' => ['id' => '019e0000-0000-7000-8000-000000000001'],
            ], 201),
        ]);

        $response = $this->actingWithJwtCookie($admin)
            ->postJson('/users', [
                'name' => 'Baru',
                'email' => 'baru@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => 'User',
            ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'Pengguna berhasil dibuat.')
            ->assertJsonPath('redirect', route('users.index'));
    }

    private function actingWithJwtCookie(User $user): static
    {
        $token = auth('api')->login($user);

        return $this->withCookie(AuthCookie::NAME, $token);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
