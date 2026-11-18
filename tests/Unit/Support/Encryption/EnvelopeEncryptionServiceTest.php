<?php

namespace Tests\Unit\Support\Encryption;

use App\Domain\ActivityLog\Support\ActivityLogRealtime;
use App\Domain\ActivityLog\Support\CryptoActivityRecorder;
use App\Support\Encryption\DecryptedValueCache;
use App\Support\Encryption\DecryptionContext;
use App\Support\Encryption\EncryptedEnvelope;
use App\Support\Encryption\EnvelopeEncryptionService;
use App\Support\Encryption\InvoicePayloadDecryptLogger;
use App\Support\Oci\Kms\Contracts\KmsDekClientInterface;
use App\Support\Oci\Kms\LocalKmsDekClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Spatie\Activitylog\Models\Activity;
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
            new CryptoActivityRecorder(new ActivityLogRealtime),
            new InvoicePayloadDecryptLogger,
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

    public function test_decrypt_audit_includes_envelope_metadata_on_cache_miss(): void
    {
        config(['encryption.audit_decrypts' => 'cache_miss']);

        $context = new DecryptionContext('users', 'user-1', 'two_factor_secret', 'test');
        $envelope = $this->service->encrypt('super-secret', $context);

        $this->service->decrypt($envelope, $context);

        $activity = Activity::query()->where('log_name', 'crypto')->firstOrFail();

        $this->assertFalse($activity->properties->get('cache_hit'));
        $this->assertSame(1, $activity->properties->get('envelope_version'));
        $this->assertFalse($activity->properties->get('legacy_ciphertext'));
    }

    public function test_decrypt_audit_always_logs_cache_hits(): void
    {
        config(['encryption.audit_decrypts' => 'always']);

        Cache::flush();

        $context = new DecryptionContext('users', 'user-2', 'two_factor_secret', 'test');
        $envelope = $this->service->encrypt('cached-secret', $context);

        $this->service->decrypt($envelope, $context);
        $this->service->decrypt($envelope, $context);

        $activities = Activity::query()->where('log_name', 'crypto')->get();

        $this->assertCount(2, $activities);
        $this->assertFalse($activities[0]->properties->get('cache_hit'));
        $this->assertTrue($activities[1]->properties->get('cache_hit'));
    }
}
