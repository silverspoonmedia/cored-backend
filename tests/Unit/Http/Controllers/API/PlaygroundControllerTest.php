<?php

namespace Tests\Unit\Http\Controllers\API;

use App\Http\Controllers\API\PlaygroundController;
use App\Http\Requests\Playground\IndexUserRequest;
use App\Http\Requests\Playground\StoreUserRequest;
use App\Http\Requests\Playground\UpdateUserRequest;
use App\Models\User;
use App\Services\Playground\UserService;
use App\Support\DataTable\DataTableResponseFactory;
use App\Support\DataTable\Resolvers\PlaygroundUserRowActionResolver;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class PlaygroundControllerTest extends TestCase
{
    private UserService&MockInterface $userService;

    private DataTableResponseFactory $dataTables;

    private PlaygroundUserRowActionResolver $rowActions;

    private PlaygroundController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userService = Mockery::mock(UserService::class);
        $this->dataTables = new DataTableResponseFactory;
        $this->rowActions = new PlaygroundUserRowActionResolver;
        $this->controller = new PlaygroundController(
            $this->userService,
            $this->dataTables,
            $this->rowActions,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    private function toTestResponse(JsonResponse $response): TestResponse
    {
        return TestResponse::fromBaseResponse($response);
    }

    public function test_ping_returns_success_response(): void
    {
        $response = $this->toTestResponse($this->controller->ping());

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Pong Global',
            ]);
    }

    public function test_index_returns_all_users(): void
    {
        $user = new User(['name' => 'Alice', 'email' => 'alice@example.com']);
        $user->id = '019e8766-857b-7259-a580-a21e1ddfa27e';

        $this->userService
            ->shouldReceive('list')
            ->once()
            ->andReturn(new Collection([$user]));

        $request = Mockery::mock(IndexUserRequest::class);
        $request->shouldReceive('wantsDatatable')->once()->andReturn(false);

        $response = $this->toTestResponse($this->controller->index($request));

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Users retrieved successfully.',
                'data' => [
                    ['id' => '019e8766-857b-7259-a580-a21e1ddfa27e', 'name' => 'Alice', 'email' => 'alice@example.com'],
                ],
            ]);
    }

    public function test_show_returns_user(): void
    {
        $user = new User(['name' => 'Alice', 'email' => 'alice@example.com']);
        $user->id = '019e8766-857b-7259-a580-a21e1ddfa27e';

        $this->userService
            ->shouldReceive('get')
            ->once()
            ->with('019e8766-857b-7259-a580-a21e1ddfa27e')
            ->andReturn($user);

        $response = $this->toTestResponse($this->controller->show('019e8766-857b-7259-a580-a21e1ddfa27e'));

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'User retrieved successfully.',
                'data' => [
                    'id' => '019e8766-857b-7259-a580-a21e1ddfa27e',
                    'name' => 'Alice',
                    'email' => 'alice@example.com',
                ],
            ]);
    }

    public function test_store_delegates_to_service(): void
    {
        $payload = [
            'name' => 'Bob',
            'email' => 'bob@example.com',
            'password' => 'password123',
        ];

        $created = new User([
            'name' => 'Bob',
            'email' => 'bob@example.com',
        ]);
        $created->id = '019e8766-857b-7259-a580-a21e1ddfa28e';

        $request = Mockery::mock(StoreUserRequest::class);
        $request->shouldReceive('validated')->once()->andReturn($payload);

        $this->userService
            ->shouldReceive('create')
            ->once()
            ->with($payload)
            ->andReturn($created);

        $response = $this->toTestResponse($this->controller->store($request));

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'message' => 'User created successfully.',
                'data' => [
                    'id' => '019e8766-857b-7259-a580-a21e1ddfa28e',
                    'name' => 'Bob',
                    'email' => 'bob@example.com',
                ],
            ]);
    }

    public function test_update_delegates_to_service(): void
    {
        $payload = ['name' => 'Bobby'];

        $updated = new User([
            'name' => 'Bobby',
            'email' => 'bob@example.com',
        ]);
        $updated->id = '019e8766-857b-7259-a580-a21e1ddfa27e';

        $request = Mockery::mock(UpdateUserRequest::class);
        $request->shouldReceive('validated')->once()->andReturn($payload);

        $this->userService
            ->shouldReceive('update')
            ->once()
            ->with('019e8766-857b-7259-a580-a21e1ddfa27e', $payload)
            ->andReturn($updated);

        $response = $this->toTestResponse($this->controller->update($request, '019e8766-857b-7259-a580-a21e1ddfa27e'));

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'User updated successfully.',
                'data' => [
                    'id' => '019e8766-857b-7259-a580-a21e1ddfa27e',
                    'name' => 'Bobby',
                    'email' => 'bob@example.com',
                ],
            ]);
    }

    public function test_destroy_delegates_to_service(): void
    {
        $this->userService
            ->shouldReceive('delete')
            ->once()
            ->with('019e8766-857b-7259-a580-a21e1ddfa27e');

        $response = $this->toTestResponse($this->controller->destroy('019e8766-857b-7259-a580-a21e1ddfa27e'));

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'User deleted successfully.',
            ]);
    }
}
