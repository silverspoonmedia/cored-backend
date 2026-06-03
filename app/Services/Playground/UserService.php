<?php

namespace App\Services\Playground;

use App\Exceptions\Playground\CannotDeleteLastRemainingUserException;
use App\Exceptions\Playground\DisposableEmailNotAllowedException;
use App\Models\User;
use App\Repositories\Contracts\EloquentCrudRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class UserService
{
    /** @var list<string> */
    private const BLOCKED_EMAIL_DOMAINS = [
        'mailinator.com',
        'tempmail.com',
    ];

    public function __construct(
        private readonly EloquentCrudRepositoryInterface $repository,
    ) {}

    public function list(): Collection
    {
        return $this->repository->all(User::class);
    }

    public function get(string $id): Model
    {
        return $this->repository->findOrFail(User::class, $id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        $this->assertEmailDomainAllowed($data['email']);

        return $this->repository->create(User::class, $this->onlyPersistableAttributes($data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(string $id, array $data): Model
    {
        if (isset($data['email'])) {
            $this->assertEmailDomainAllowed($data['email']);
        }

        return $this->repository->update(User::class, $id, $this->onlyPersistableAttributes($data));
    }

    public function delete(string $id): void
    {
        if ($this->repository->count(User::class) <= 1) {
            throw new CannotDeleteLastRemainingUserException;
        }

        $this->repository->delete(User::class, $id);
    }

    private function assertEmailDomainAllowed(string $email): void
    {
        $domain = strtolower((string) strrchr($email, '@'));

        if ($domain !== '' && in_array(ltrim($domain, '@'), self::BLOCKED_EMAIL_DOMAINS, true)) {
            throw new DisposableEmailNotAllowedException;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function onlyPersistableAttributes(array $data): array
    {
        return array_intersect_key($data, array_flip(['name', 'email', 'password']));
    }
}
