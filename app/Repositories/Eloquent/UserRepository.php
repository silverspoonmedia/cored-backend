<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;

class UserRepository implements UserRepositoryInterface
{
    public function findById(string $id): ?User
    {
        return User::query()->find($id);
    }

    public function findByIdOrFail(string $id): User
    {
        return User::query()->findOrFail($id);
    }

    public function findByEmail(string $email): ?User
    {
        return User::query()->where('email', $email)->first();
    }

    public function create(array $data): User
    {
        return User::query()->create($data);
    }

    public function update(string $id, array $data): User
    {
        $user = $this->findByIdOrFail($id);
        $user->update($data);

        return $user->fresh() ?? $user;
    }

    public function markEmailAsVerified(string $id): User
    {
        return $this->update($id, [
            'email_verified_at' => now(),
        ]);
    }
}
