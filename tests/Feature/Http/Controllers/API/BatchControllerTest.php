<?php

namespace Tests\Feature\Http\Controllers\API;

use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class BatchControllerTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private function tokenFor(User $user): string
    {
        return auth('api')->login($user);
    }

    private function makeUser(): User
    {
        $this->seedRolesAndPermissions();

        return User::factory()->create([
            'email' => 'batch@example.com',
            'password' => Hash::make('password123'),
        ]);
    }

    public function test_batch_requires_authentication(): void
    {
        $this->postJson('/api/v1/batch', [
            'requests' => [['method' => 'GET', 'path' => '/api/v1/auth/me']],
        ])->assertStatus(401);
    }

    public function test_batch_rejects_empty_payload(): void
    {
        $token = $this->tokenFor($this->makeUser());

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/batch', ['requests' => []])
            ->assertStatus(422);
    }

    public function test_batch_rejects_more_than_ten_requests(): void
    {
        $token = $this->tokenFor($this->makeUser());

        $requests = array_fill(0, 11, ['method' => 'GET', 'path' => '/api/v1/auth/me']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/batch', ['requests' => $requests])
            ->assertStatus(422);
    }

    public function test_batch_rejects_mutating_methods(): void
    {
        $token = $this->tokenFor($this->makeUser());

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/batch', [
                'requests' => [['method' => 'POST', 'path' => '/api/v1/auth/logout']],
            ])
            ->assertOk();

        $response->assertJsonPath('data.0.status', 422);
    }

    public function test_batch_rejects_paths_outside_the_api(): void
    {
        $token = $this->tokenFor($this->makeUser());

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/batch', [
                'requests' => [['method' => 'GET', 'path' => '/admin/users']],
            ])
            ->assertOk();

        $response->assertJsonPath('data.0.status', 422);
    }

    public function test_batch_dispatches_read_requests_and_preserves_envelopes(): void
    {
        $token = $this->tokenFor($this->makeUser());

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/batch', [
                'requests' => [
                    ['method' => 'GET', 'path' => '/api/v1/auth/me'],
                    ['method' => 'GET', 'path' => '/api/v1/auth/me'],
                ],
            ])
            ->assertOk();

        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('data.0.status', 200);
        $response->assertJsonPath('data.0.body.status', 'success');
        $response->assertJsonPath('data.0.body.data.email', 'batch@example.com');
        $response->assertJsonPath('data.1.status', 200);
    }

    public function test_batch_sub_request_keeps_authorization_header(): void
    {
        $token = $this->tokenFor($this->makeUser());

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/batch', [
                'requests' => [['method' => 'GET', 'path' => '/api/v1/auth/me']],
            ])
            ->assertOk();

        // Would be 401 if the sub-request lost the Bearer token.
        $response->assertJsonPath('data.0.status', 200);
    }

    public function test_batch_sub_request_preserves_query_string(): void
    {
        $token = $this->tokenFor($this->makeUser());

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/batch', [
                'requests' => [['method' => 'GET', 'path' => '/api/v1/navigation/menus?layout=sidebar']],
            ])
            ->assertOk();

        // The navigation endpoint resolves only when the layout query survives dispatch.
        $response->assertJsonPath('data.0.status', 200);
        $response->assertJsonPath('data.0.body.status', 'success');

        // meta counts every entry; none failed here.
        $response->assertJsonPath('meta.failed', 0);
        $response->assertJsonPath('meta.total', 1);
    }

    public function test_batch_meta_counts_failed_entries(): void
    {
        $token = $this->tokenFor($this->makeUser());

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/batch', [
                'requests' => [
                    ['method' => 'GET', 'path' => '/api/v1/auth/me'],
                    ['method' => 'GET', 'path' => '/api/v1/nonexistent-endpoint'],
                ],
            ])
            ->assertOk();

        // Outer status stays 200; meta.failed exposes the one dead entry so the
        // caller can tell partial failure without parsing each body.
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('data.0.status', 200);
        $response->assertJsonPath('data.1.status', 404);
        $response->assertJsonPath('meta.failed', 1);
        $response->assertJsonPath('meta.total', 2);
    }
}
