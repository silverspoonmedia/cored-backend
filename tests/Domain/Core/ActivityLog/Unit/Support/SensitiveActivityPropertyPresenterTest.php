<?php

namespace Tests\Domain\Core\ActivityLog\Unit\Support;

use App\Domain\ActivityLog\Support\SensitiveActivityProperties;
use App\Domain\ActivityLog\Support\SensitiveActivityPropertyPresenter;
use App\Support\Encryption\EnvelopeEncryptionService;
use Tests\TestCase;

class SensitiveActivityPropertyPresenterTest extends TestCase
{
    public function test_it_decrypts_envelope_pii_values_and_keeps_other_keys(): void
    {
        $envelope = app(EnvelopeEncryptionService::class)->encrypt(
            '203.0.113.5',
            SensitiveActivityProperties::context(
                'ip_address',
                '42',
                SensitiveActivityProperties::PURPOSE_READ,
            ),
        );

        $properties = SensitiveActivityPropertyPresenter::forApi([
            'ip_address' => $envelope,
            'login_method' => 'password',
        ], '42');

        $this->assertSame('203.0.113.5', $properties['ip_address']);
        $this->assertSame('password', $properties['login_method']);
    }

    public function test_it_passes_legacy_plaintext_rows_through(): void
    {
        $properties = SensitiveActivityPropertyPresenter::forApi([
            'ip_address' => '203.0.113.5',
            'user_agent' => 'Mozilla/5.0',
        ], '42');

        $this->assertSame('203.0.113.5', $properties['ip_address']);
        $this->assertSame('Mozilla/5.0', $properties['user_agent']);
    }

    public function test_it_never_leaks_ciphertext_when_decryption_fails(): void
    {
        $broken = json_encode([
            'v' => 1,
            'alg' => 'AES-256-GCM',
            'kms_key_id' => 'local',
            'dek_ciphertext' => 'not-a-wrapped-dek',
            'iv' => base64_encode('123456789012'),
            'tag' => base64_encode('1234567890123456'),
            'ciphertext' => base64_encode('nope'),
        ], JSON_THROW_ON_ERROR);

        $properties = SensitiveActivityPropertyPresenter::forApi(['ip_address' => $broken], '42');

        $this->assertSame(SensitiveActivityPropertyPresenter::UNAVAILABLE, $properties['ip_address']);
    }

    public function test_it_is_a_no_op_when_disabled(): void
    {
        config(['activitylog.sensitive_properties.enabled' => false]);

        $properties = SensitiveActivityPropertyPresenter::forApi([
            'ip_address' => '{"v":1}',
        ], '42');

        $this->assertSame('{"v":1}', $properties['ip_address']);
    }
}
