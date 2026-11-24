<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast channels
|--------------------------------------------------------------------------
| `routes/channels.php` is host code (`withRouting(channels:)` points at it), so
| the kernel's personal channel is registered here. Product channels belong to
| the project that installs the product package, through the kernel's
| `ChannelRegistrarRegistry`.
*/

Broadcast::channel('App.Models.User.{id}', function ($user, string $id): bool {
    return (string) $user->id === $id;
});
