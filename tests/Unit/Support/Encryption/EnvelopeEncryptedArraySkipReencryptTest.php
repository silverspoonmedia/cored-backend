<?php

namespace Tests\Unit\Support\Encryption;

use App\Domain\PaymentGateway\Models\Invoice;
use App\Support\Encryption\Casts\EnvelopeEncryptedArray;
use App\Support\Oci\Kms\Contracts\KmsDekClientInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\Support\Encryption\CountingKmsDekClient;
use Tests\TestCase;

class EnvelopeEncryptedArraySkipReencryptTest extends TestCase
{
    use RefreshDatabase;

    public function test_set_skips_generate_dek_when_plaintext_unchanged(): void
    {
        Cache::flush();

        // The compare-decrypt below writes a `crypto` activity row whose own PII
        // properties are envelope-encrypted, which costs an unrelated DEK wrap.
        // This test measures the cast only, so the audit row is suppressed.
        config(['encryption.audit_excluded_resource_types' => ['invoices']]);

        $kms = new CountingKmsDekClient;
        $this->app->instance(KmsDekClientInterface::class, $kms);

        $cast = new EnvelopeEncryptedArray('invoices', 'gateway_response_payload', 'payment');

        $model = new Invoice;
        $model->id = (string) Str::uuid();

        $payload = ['status' => 'pending', 'reference' => 'INV-1'];

        $envelope = $cast->set($model, 'gateway_response_payload', $payload, []);

        $this->assertSame(1, $kms->generateCount);

        $sameEnvelope = $cast->set($model, 'gateway_response_payload', $payload, [
            'gateway_response_payload' => $envelope,
        ]);

        $this->assertSame($envelope, $sameEnvelope);
        $this->assertSame(1, $kms->generateCount);
    }

    public function test_set_reencrypts_when_plaintext_changes(): void
    {
        Cache::flush();

        // See the note in the test above: keep the `crypto` audit row out of the
        // KMS counter.
        config(['encryption.audit_excluded_resource_types' => ['invoices']]);

        $kms = new CountingKmsDekClient;
        $this->app->instance(KmsDekClientInterface::class, $kms);

        $cast = new EnvelopeEncryptedArray('invoices', 'gateway_response_payload', 'payment');

        $model = new Invoice;
        $model->id = (string) Str::uuid();

        $envelope = $cast->set($model, 'gateway_response_payload', ['status' => 'pending'], []);

        $changedEnvelope = $cast->set($model, 'gateway_response_payload', ['status' => 'paid'], [
            'gateway_response_payload' => $envelope,
        ]);

        $this->assertNotSame($envelope, $changedEnvelope);
        $this->assertSame(2, $kms->generateCount);
    }
}
