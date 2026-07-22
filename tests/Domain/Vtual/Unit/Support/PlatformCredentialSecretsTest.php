<?php

namespace Tests\Domain\Vtual\Unit\Support;

use App\Domain\Vtual\Enums\PlatformCredentialType;
use App\Domain\Vtual\Exceptions\VtualException;
use App\Domain\Vtual\Support\PlatformCredentialSecrets;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlatformCredentialSecretsTest extends TestCase
{
    #[Test]
    public function normalize_api_key_payload(): void
    {
        $payload = PlatformCredentialSecrets::normalizePayload(
            PlatformCredentialType::ApiKey,
            ['api_key' => '  secret-key-w9Q  '],
        );

        $this->assertSame(['api_key' => 'secret-key-w9Q'], $payload);
        $this->assertSame('…-w9Q', PlatformCredentialSecrets::hint(PlatformCredentialType::ApiKey, $payload));
        $this->assertNotSame('', PlatformCredentialSecrets::fingerprint(PlatformCredentialType::ApiKey, $payload));
    }

    #[Test]
    public function reject_missing_required_key(): void
    {
        $this->expectException(VtualException::class);

        PlatformCredentialSecrets::normalizePayload(
            PlatformCredentialType::OauthApp,
            ['client_id' => 'only-id'],
        );
    }

    #[Test]
    public function reject_unknown_payload_key(): void
    {
        $this->expectException(VtualException::class);

        PlatformCredentialSecrets::normalizePayload(
            PlatformCredentialType::ApiKey,
            ['api_key' => 'abc', 'extra' => 'nope'],
        );
    }

    #[Test]
    public function fingerprint_stable_for_same_payload(): void
    {
        $type = PlatformCredentialType::Bearer;
        $a = PlatformCredentialSecrets::normalizePayload($type, [
            'access_token' => 'tok-1234',
            'refresh_token' => 'ref',
        ]);
        $b = PlatformCredentialSecrets::normalizePayload($type, [
            'refresh_token' => 'ref',
            'access_token' => 'tok-1234',
        ]);

        $this->assertSame(
            PlatformCredentialSecrets::fingerprint($type, $a),
            PlatformCredentialSecrets::fingerprint($type, $b),
        );
    }
}
