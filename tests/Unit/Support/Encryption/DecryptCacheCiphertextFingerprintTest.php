<?php

namespace Tests\Unit\Support\Encryption;

use App\Domain\ActivityLog\Support\CryptoActivityRecorder;
use App\Support\Encryption\DecryptedValueCache;
use App\Support\Encryption\DecryptionContext;
use App\Support\Encryption\EnvelopeEncryptionService;
use App\Support\Encryption\InvoicePayloadDecryptLogger;
use App\Support\Oci\Kms\LocalKmsDekClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class DecryptCacheCiphertextFingerprintTest extends TestCase
{
    use RefreshDatabase;

    public function test_different_ciphertext_for_same_context_is_cache_miss(): void
    {
        Cache::flush();

        $service = new EnvelopeEncryptionService(
            new LocalKmsDekClient,
            new DecryptedValueCache,
            new CryptoActivityRecorder,
            new InvoicePayloadDecryptLogger,
        );

        $context = new DecryptionContext('invoices', 'inv-1', 'gateway_response_payload', 'payment');

        $envelopeA = $service->encrypt(json_encode(['status' => 'pending'], JSON_THROW_ON_ERROR), $context);
        $envelopeB = $service->encrypt(json_encode(['status' => 'paid'], JSON_THROW_ON_ERROR), $context);

        $service->decrypt($envelopeA, $context);
        $service->decrypt($envelopeB, $context);

        $this->assertSame(2, Activity::query()->where('log_name', 'crypto')->count());
    }

    public function test_cache_layer_treats_ciphertext_fingerprint_mismatch_as_miss(): void
    {
        Cache::flush();

        $cache = new DecryptedValueCache;
        $contextKey = 'invoices.gateway_response_payload.inv-2';

        $cache->remember($contextKey, 'ciphertext-a', fn (): string => 'plain-a');

        $this->assertTrue($cache->has($contextKey, 'ciphertext-a'));
        $this->assertFalse($cache->has($contextKey, 'ciphertext-b'));
    }
}
