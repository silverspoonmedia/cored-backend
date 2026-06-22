<?php

namespace App\Events\Auth;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LoginSuccessful
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public User $user,
        public string $ipAddress,
        public ?string $userAgent = null,
        /** @var 'password'|'two_factor' */
        public string $loginMethod = 'password',
    ) {}
}
