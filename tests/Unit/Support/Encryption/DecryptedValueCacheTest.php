<?php

namespace Tests\Unit\Support\Encryption;

use App\Domain\ActivityLog\Support\CryptoActivityRecorder;
use App\Support\Encryption\DecryptedValueCache;
use App\Support\Encryption\DecryptionContext;
use App\Support\Encryption\EnvelopeEncryptionService;
use App\Support\Oci\Kms\LocalKmsDekClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
