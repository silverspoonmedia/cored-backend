<?php

namespace Tests\Concerns;

use App\Domain\Identity\Models\User;

trait AssertsPayloadHygiene
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $keys
     */
    protected function assertJsonMissingPaths(array $payload, array $keys): void
    {
        foreach ($keys as $key) {
            $this->assertArrayNotHasKey($key, $payload, "Payload must not contain [{$key}]");
        }
    }

    protected function assertForbiddenFor(User $user, string $method, string $uri, mixed $data = []): void
    {
        $token = auth('api')->login($user);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->json($method, $uri, is_array($data) ? $data : [])
            ->assertForbidden();
    }
}
