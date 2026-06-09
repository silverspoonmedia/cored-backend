<?php

namespace Tests\Feature\Http\Controllers\API;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlaygroundUserDatatableTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_datatable_mode_returns_yajra_payload_with_actions(): void
    {
        $user = User::factory()->create(['email' => 'dt@example.com']);

        $response = $this->getJson('/api/playground/users?display_mode=datatable&draw=1&start=0&length=10');

        $response->assertOk()
            ->assertJsonStructure([
                'draw',
                'recordsTotal',
                'recordsFiltered',
                'data' => [
                    [
                        'id',
                        'name',
                        'email',
                        'actions' => [
                            ['key', 'label', 'method', 'url', 'style'],
                        ],
                    ],
                ],
            ])
            ->assertJsonPath('data.0.id', $user->id)
            ->assertJsonPath('data.0.actions.0.key', 'show')
            ->assertJsonPath('data.0.actions.2.key', 'delete');
    }

    public function test_index_query_mode_is_default_envelope(): void
    {
        User::factory()->create();

        $response = $this->getJson('/api/playground/users');

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'message' => 'Users retrieved successfully.',
            ])
            ->assertJsonMissing(['draw' => 1]);
    }
}
