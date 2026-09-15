<?php

namespace Tests\Unit\Support\Encryption;

use App\Domain\ActivityLog\Support\ActivityLogRealtime;
use App\Domain\ActivityLog\Support\CryptoActivityRecorder;
use App\Support\Encryption\DecryptedValueCache;
use App\Support\Encryption\DecryptionContext;
use App\Support\Encryption\EnvelopeEncryptionService;
use App\Support\Encryption\InvoicePayloadDecryptLogger;
use App\Support\Oci\Kms\LocalKmsDekClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class SessionAuditExclusionTest extends TestCase
{
    use RefreshDatabase;

    public function test_session_decrypt_context_is_excluded_from_crypto_audit(): void
    {
        $service = new EnvelopeEncryptionService(
            new LocalKmsDekClient,
            new DecryptedValueCache,
            new CryptoActivityRecorder(new ActivityLogRealtime),
            new InvoicePayloadDecryptLogger,
        );

        $context = new DecryptionContext('sessions', 'sess-1', 'payload', 'session_read');
        $envelope = $service->encrypt('session-bytes', $context);

        $service->decrypt($envelope, $context);

        $this->assertSame(0, Activity::query()->where('log_name', 'crypto')->count());
    }
}
