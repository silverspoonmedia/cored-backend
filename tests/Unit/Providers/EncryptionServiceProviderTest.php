<?php

namespace Tests\Unit\Providers;

use App\Support\Encryption\Session\EncryptedDatabaseSessionHandler;
use Tests\TestCase;

class EncryptionServiceProviderTest extends TestCase
{
    public function test_default_config_keeps_database_session_driver(): void
    {
        $this->assertFalse((bool) config('encryption.encrypt_sessions'));
        $this->assertSame('array', config('session.driver'));
    }

    public function test_envelope_session_swap_only_when_explicitly_enabled(): void
    {
        config([
            'session.driver' => 'database',
            'encryption.encrypt_sessions' => true,
        ]);

        $driver = config('session.driver');

        if ($driver === 'database' && (bool) config('encryption.encrypt_sessions', false)) {
            $driver = 'encrypted_database';
        }

        $this->assertSame('encrypted_database', $driver);
    }

    public function test_encrypted_database_driver_is_registered_for_rollback(): void
    {
        config(['session.encrypt' => false]);

        $store = $this->app->make('session')->driver('encrypted_database');

        $this->assertInstanceOf(EncryptedDatabaseSessionHandler::class, $store->getHandler());
    }
}
