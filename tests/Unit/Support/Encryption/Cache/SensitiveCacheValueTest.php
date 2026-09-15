<?php

namespace Tests\Unit\Support\Encryption\Cache;

use App\Domain\ActivityLog\Support\ActivityLogRealtime;
use App\Domain\ActivityLog\Support\CryptoActivityRecorder;
use App\Support\Encryption\Cache\SensitiveCacheValue;
use App\Support\Encryption\DecryptedValueCache;
use App\Support\Encryption\EncryptedEnvelope;
use App\Support\Encryption\EnvelopeEncryptionService;
use App\Support\Encryption\InvoicePayloadDecryptLogger;
use App\Support\Oci\Kms\LocalKmsDekClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class SensitiveCacheValueTest extends TestCase
{
    use RefreshDatabase;

    private SensitiveCacheValue $sensitiveCache;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $encryption = new EnvelopeEncryptionService(
            new LocalKmsDekClient,
            new DecryptedValueCache,
            new CryptoActivityRecorder(new ActivityLogRealtime),
            new InvoicePayloadDecryptLogger,
        );

        $this->sensitiveCache = new SensitiveCacheValue($encryption);
    }

    public function test_wrap_encrypts_sensitive_prefix_keys(): void
    {
        $payload = ['user_id' => 'abc', 'token' => 'pending'];

        $wrapped = $this->sensitiveCache->wrap('2fa_token:session-1', $payload);

        $this->assertIsString($wrapped);
        $this->assertTrue(EncryptedEnvelope::isEnvelopeJson($wrapped));
    }

    public function test_wrap_leaves_non_sensitive_keys_unchanged(): void
    {
        $payload = ['user_id' => 'abc'];

        $this->assertSame($payload, $this->sensitiveCache->wrap('menu:dynamic', $payload));
    }

    public function test_unwrap_roundtrips_sensitive_values(): void
    {
        $key = '2fa_token:session-1';
        $payload = ['user_id' => 'abc', 'token' => 'pending'];

        $wrapped = $this->sensitiveCache->wrap($key, $payload);
        $unwrapped = $this->sensitiveCache->unwrap($key, $wrapped);

        $this->assertSame($payload, $unwrapped);
        $this->assertSame(1, Activity::query()->where('log_name', 'crypto')->count());
    }
}
