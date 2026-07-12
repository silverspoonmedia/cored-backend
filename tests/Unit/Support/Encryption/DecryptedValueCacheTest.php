<?php

namespace Tests\Unit\Support\Encryption;

use App\Domain\ActivityLog\Support\CryptoActivityRecorder;
use App\Support\Encryption\DecryptedValueCache;
use App\Support\Encryption\DecryptionContext;
use App\Support\Encryption\EnvelopeEncryptionService;
use App\Support\Oci\Kms\LocalKmsDekClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class DecryptedValueCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_decrypt_uses_cache_on_second_read(): void
    {
        Cache::flush();

        $service = new EnvelopeEncryptionService(
            new LocalKmsDekClient,
            new DecryptedValueCache,
            new CryptoActivityRecorder,
        );

        $context = new DecryptionContext('users', 'user-1', 'two_factor_secret', 'test');
        $envelope = $service->encrypt('cached-secret', $context);

        $service->decrypt($envelope, $context);
        $service->decrypt($envelope, $context);

        $this->assertSame(1, Activity::query()->where('log_name', 'crypto')->count());
    }

    public function test_decrypt_cache_survives_beyond_legacy_ttl_when_ciphertext_stable(): void
    {
        config(['encryption.decrypted_cache_max_ttl' => 86400]);

        Carbon::setTestNow('2026-01-01 00:00:00');
        Cache::flush();

        $service = new EnvelopeEncryptionService(
            new LocalKmsDekClient,
            new DecryptedValueCache,
            new CryptoActivityRecorder,
        );

        $context = new DecryptionContext('users', 'user-2', 'two_factor_secret', 'test');
        $envelope = $service->encrypt('cached-secret', $context);

        $service->decrypt($envelope, $context);

        Carbon::setTestNow('2026-01-01 00:20:00');

        $service->decrypt($envelope, $context);

        $this->assertSame(1, Activity::query()->where('log_name', 'crypto')->count());

        Carbon::setTestNow();
    }

    public function test_forget_by_context_clears_decrypt_cache(): void
    {
        Cache::flush();

        $cache = new DecryptedValueCache;
        $contextKey = 'users.two_factor_secret.user-4';

        $cache->remember($contextKey, 'stored-envelope', fn (): string => 'plain');

        $this->assertTrue($cache->has($contextKey, 'stored-envelope'));

        $cache->forgetByContext($contextKey);

        $this->assertFalse($cache->has($contextKey, 'stored-envelope'));
    }

    public function test_always_audit_mode_logs_every_decrypt(): void
    {
        config(['encryption.audit_decrypts' => 'always']);

        Cache::flush();

        $service = new EnvelopeEncryptionService(
            new LocalKmsDekClient,
            new DecryptedValueCache,
            new CryptoActivityRecorder,
        );

        $context = new DecryptionContext('users', 'user-3', 'two_factor_secret', 'test');
        $envelope = $service->encrypt('cached-secret', $context);

        $service->decrypt($envelope, $context);
        $service->decrypt($envelope, $context);

        $this->assertSame(2, Activity::query()->where('log_name', 'crypto')->count());
    }
}
