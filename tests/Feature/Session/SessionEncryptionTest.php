<?php

namespace Tests\Feature\Session;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\EncryptedStore;
use Tests\TestCase;

class SessionEncryptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_session_encrypt_uses_laravel_encrypted_store_not_envelope_handler(): void
    {
        config([
            'session.driver' => 'database',
            'session.encrypt' => true,
            'encryption.encrypt_sessions' => false,
        ]);

        foreach (['session', 'session.store', 'session.driver'] as $abstract) {
            $this->app->forgetInstance($abstract);
        }

        $store = $this->app->make('session.store');

        $this->assertInstanceOf(EncryptedStore::class, $store);
        $this->assertNotInstanceOf(
            \App\Support\Encryption\Session\EncryptedDatabaseSessionHandler::class,
            $store->getHandler(),
        );
    }
}
