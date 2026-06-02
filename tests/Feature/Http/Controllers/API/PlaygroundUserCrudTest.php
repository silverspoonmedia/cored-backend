<?php

namespace Tests\Feature\Http\Controllers\API;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlaygroundUserCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_users(): void
    {
        User::factory()->count(2)->create();

        $response = $this->getJson('/api/playground/users');

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'message' => 'Users retrieved successfully.',
            ])
            ->assertJsonCount(2, 'data');
    }

    public function test_show_returns_single_user(): void
    {
        $user = User::factory()->create();

        $response = $this->getJson('/api/playground/users/'.$user->id);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'message' => 'User retrieved successfully.',
                'data' => [
                    'id' => $user->id,
                    'email' => $user->email,
                ],
            ]);
    }

    public function test_show_returns_not_found_for_missing_user(): void
    {
        $response = $this->getJson('/api/playground/users/999');

        $response->assertNotFound();
    }

    public function test_store_creates_user(): void
    {
        $response = $this->postJson('/api/playground/users', [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJson([
                'status' => 'success',
                'message' => 'User created successfully.',
                'data' => [
                    'name' => 'New User',
                    'email' => 'new@example.com',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'new@example.com',
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/playground/users', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_update_modifies_user(): void
    {
        $user = User::factory()->create();

        $response = $this->putJson('/api/playground/users/'.$user->id, [
            'name' => 'Updated Name',
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'message' => 'User updated successfully.',
                'data' => [
                    'id' => $user->id,
                    'name' => 'Updated Name',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_destroy_removes_user(): void
    {
        $users = User::factory()->count(2)->create();
        $user = $users->first();

        $response = $this->deleteJson('/api/playground/users/'.$user->id);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'message' => 'User deleted successfully.',
            ]);

        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
        ]);
    }

    public function test_destroy_rejects_last_remaining_user(): void
    {
        $user = User::factory()->create();

        $response = $this->deleteJson('/api/playground/users/'.$user->id);

        $response->assertStatus(409)
            ->assertJson([
                'status' => 'error',
                'message' => 'Cannot delete the last remaining user.',
            ]);
    }

    public function test_store_rejects_disposable_email_domain(): void
    {
        $response = $this->postJson('/api/playground/users', [
            'name' => 'Bob',
            'email' => 'bob@mailinator.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'message' => 'Disposable email domains are not allowed.',
            ]);

        $this->assertDatabaseMissing('users', [
            'email' => 'bob@mailinator.com',
        ]);
    }
}
