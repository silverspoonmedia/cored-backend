<?php

namespace App\Domain\Vtual\Policies;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Models\PlatformCredential;

class PlatformCredentialPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view vtual platform credentials');
    }

    public function view(User $user, PlatformCredential $credential): bool
    {
        return $user->can('view vtual platform credentials');
    }

    public function create(User $user): bool
    {
        return $user->can('manage vtual platform credentials');
    }

    public function update(User $user, PlatformCredential $credential): bool
    {
        return $user->can('manage vtual platform credentials');
    }

    public function delete(User $user, PlatformCredential $credential): bool
    {
        return $user->can('manage vtual platform credentials');
    }
}
