<?php

namespace Tests\Unit\Services\Playground;

use App\Exceptions\Playground\CannotDeleteLastRemainingUserException;
use App\Exceptions\Playground\DisposableEmailNotAllowedException;
use App\Models\User;
use App\Repositories\Contracts\EloquentCrudRepositoryInterface;
use App\Services\Playground\UserService;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class UserServiceTest extends TestCase
{
    private EloquentCrudRepositoryInterface&MockInterface $repository;

    private UserService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(EloquentCrudRepositoryInterface::class);
        $this->service = new UserService($this->repository);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_create_rejects_disposable_email_domain(): void
    {
        $this->repository->shouldNotReceive('create');

        $this->expectException(DisposableEmailNotAllowedException::class);

        $this->service->create([
            'name' => 'Bob',
            'email' => 'bob@mailinator.com',
            'password' => 'password123',
        ]);
    }

    public function test_create_persists_when_email_domain_is_allowed(): void
    {
        $payload = [
            'name' => 'Bob',
            'email' => 'bob@example.com',
            'password' => 'password123',
        ];

        $user = new User($payload);
        $user->id = 1;

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->with(User::class, $payload)
            ->andReturn($user);

        $result = $this->service->create($payload);

        $this->assertSame($user, $result);
    }

    public function test_delete_throws_when_only_one_user_remains(): void
    {
        $this->repository
            ->shouldReceive('count')
            ->once()
            ->with(User::class)
            ->andReturn(1);

        $this->repository->shouldNotReceive('delete');

        $this->expectException(CannotDeleteLastRemainingUserException::class);

        $this->service->delete(1);
    }

    public function test_delete_removes_user_when_more_than_one_exists(): void
    {
        $this->repository
            ->shouldReceive('count')
            ->once()
            ->with(User::class)
            ->andReturn(2);

        $this->repository
            ->shouldReceive('delete')
            ->once()
            ->with(User::class, 1)
            ->andReturn(true);

        $this->service->delete(1);

        $this->assertTrue(true);
    }
}
