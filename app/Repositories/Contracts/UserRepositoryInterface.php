<?php

namespace App\Repositories\Contracts;

use App\Models\User;

interface UserRepositoryInterface
{
    public function findById(string $id): ?User;

    public function findByIdOrFail(string $id): User;

    public function findByEmail(string $email): ?User;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): User;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(string $id, array $data): User;

    public function markEmailAsVerified(string $id): User;
}
