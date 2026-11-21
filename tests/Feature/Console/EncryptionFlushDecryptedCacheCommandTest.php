<?php

namespace Tests\Feature\Console;

use App\Support\Encryption\DecryptedValueCache;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The flush command forgets one context and refuses to pretend a bulk flush
 * exists. Both halves matter: a silent success on the no-context form would
 * leave plaintext in the cache after a key rotation while the operator believes
 * it was purged.
 */
class EncryptionFlushDecryptedCacheCommandTest extends TestCase
{
    private const CONTEXT = 'users.user-1.two_factor_secret';

    public function test_it_forgets_the_cache_entries_of_a_context(): void
    {
        $cache = new DecryptedValueCache;

        $cache->remember(self::CONTEXT, 'ciphertext', fn (): string => 'plaintext');

        $this->assertTrue($cache->has(self::CONTEXT, 'ciphertext'), 'The fixture did not populate the decrypted cache.');

        $this->artisan('encryption:flush-decrypted-cache', ['context' => self::CONTEXT])
            ->expectsOutputToContain('Flushed decrypted cache context')
            ->assertSuccessful();

        $this->assertFalse($cache->has(self::CONTEXT, 'ciphertext'), 'The command left the decrypted plaintext in the cache.');
    }

    public function test_it_fails_without_a_context_instead_of_reporting_a_bulk_flush(): void
    {
        $cache = new DecryptedValueCache;

        $cache->remember(self::CONTEXT, 'ciphertext', fn (): string => 'plaintext');

        $this->artisan('encryption:flush-decrypted-cache')
            ->expectsOutputToContain('Bulk decrypted-cache flush is not available')
            ->assertFailed();

        // The error path must not clear anything: a half-purge is worse than
        // none, because the operator reads the failure as "nothing happened".
        $this->assertTrue($cache->has(self::CONTEXT, 'ciphertext'));
    }

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }
}
