<?php

namespace Tests\Unit\Support\Encryption;

use App\Domain\ActivityLog\Support\CryptoActivityRecorder;
use App\Support\Encryption\DecryptedValueCache;
use App\Support\Encryption\DecryptionContext;
use App\Support\Encryption\EncryptedEnvelope;
use App\Support\Encryption\EnvelopeEncryptionService;
use App\Support\Oci\Kms\Contracts\KmsDekClientInterface;
use App\Support\Oci\Kms\LocalKmsDekClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class EnvelopeEncryptionServiceTest extends TestCase
{
    use RefreshDatabase;

    private EnvelopeEncryptionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(KmsDekClientInterface::class, new LocalKmsDekClient);

        $this->service = new EnvelopeEncryptionService(
            new LocalKmsDekClient,
            new DecryptedValueCache,
            new CryptoActivityRecorder,
        );
    }

    public function test_encrypt_and_decrypt_roundtrip(): void
    {
        $context = new DecryptionContext('users', 'user-1', 'two_factor_secret', 'test');

        $envelope = $this->service->encrypt('super-secret', $context);

        $this->assertTrue(EncryptedEnvelope::isEnvelopeJson($envelope));

        $plaintext = $this->service->decrypt($envelope, $context);

        $this->assertSame('super-secret', $plaintext);
    }

    public function test_migrate_legacy_crypt_value_to_envelope(): void
    {
        $legacy = Crypt::encryptString('legacy-secret');

        $envelope = $this->service->migrateLegacyValue($legacy);

        $this->assertTrue(EncryptedEnvelope::isEnvelopeJson($envelope));
        $this->assertSame(
            'legacy-secret',
            $this->service->decryptWithoutCache($envelope),
        );
    }
}
