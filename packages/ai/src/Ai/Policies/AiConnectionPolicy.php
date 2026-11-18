<?php

namespace App\Domain\Ai\Policies;

use App\Domain\Ai\Models\AiConnection;
use App\Domain\Identity\Models\User;

class AiConnectionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view ai connections');
    }

    public function view(User $user, AiConnection $connection): bool
    {
        return $user->can('view ai connections');
    }

    public function create(User $user): bool
    {
        return $user->can('manage ai connections');
    }

    public function update(User $user, AiConnection $connection): bool
    {
        return $user->can('manage ai connections');
    }

    public function delete(User $user, AiConnection $connection): bool
    {
        return $user->can('manage ai connections');
    }
}
