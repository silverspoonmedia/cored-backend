<?php

namespace Tests\Concerns;

use App\Domain\Identity\Models\User;
use App\Support\Auth\AuthCookie;

trait InteractsWithWebAuth
{
    protected function actingWithJwtCookie(User $user): static
    {
        $token = auth('api')->login($user);

        return $this->withCookie(AuthCookie::NAME, $token);
    }
}
