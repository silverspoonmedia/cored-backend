<?php

namespace Tests\Unit\Support\Oci;

use App\Support\Oci\OciConfig;
use App\Support\Oci\OciRequestSigner;
use Tests\TestCase;

class OciRequestSignerTest extends TestCase
{
    private OciRequestSigner $signer;

    private OciConfig $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->signer = new OciRequestSigner;
        $this->config = new OciConfig(
            tenancy: 'ocid1.tenancy.oc1..aaaaaaaaexampletenancy',
            user: 'ocid1.user.oc1..aaaaaaaaexampleuser',
            fingerprint: 'aa:bb:cc:dd:ee:ff:00:11:22:33:44:55:66:77:88:99',
            privateKeyPath: base_path('tests/Fixtures/Oci/test_api_key.pem'),
            region: 'ap-singapore-1',
        );
    }

    public function test_sign_returns_required_headers_for_post_request(): void
    {
        $body = '{"keyId":"ocid1.key.ap-singapore-1.example"}';
        $url = 'https://example-crypto.kms.ap-singapore-1.oraclecloud.com/20180608/generateDataEncryptionKey';

        $headers = $this->signer->sign($this->config, 'POST', $url, $body);

        $this->assertArrayHasKey('authorization', $headers);
        $this->assertArrayHasKey('date', $headers);
        $this->assertArrayHasKey('host', $headers);
        $this->assertArrayHasKey('content-length', $headers);
        $this->assertArrayHasKey('content-type', $headers);
        $this->assertArrayHasKey('x-content-sha256', $headers);
        $this->assertSame('example-crypto.kms.ap-singapore-1.oraclecloud.com', $headers['host']);
        $this->assertSame((string) strlen($body), $headers['content-length']);
        $this->assertStringStartsWith('Signature version="1",keyId="', $headers['authorization']);
        $this->assertStringContainsString('algorithm="rsa-sha256"', $headers['authorization']);
    }

    public function test_build_signing_string_uses_request_target_and_lowercase_method(): void
    {
        $headers = [
            'date' => 'Sun, 12 Jul 2026 02:30:00 GMT',
            'host' => 'example-crypto.kms.ap-singapore-1.oraclecloud.com',
            'content-length' => '10',
            'content-type' => 'application/json',
            'x-content-sha256' => 'abc123',
        ];

        $signingString = $this->signer->buildSigningString(
            'POST',
            '/20180608/generateDataEncryptionKey',
            $headers,
            ['date', '(request-target)', 'host', 'content-length', 'content-type', 'x-content-sha256'],
        );

        $this->assertStringContainsString('(request-target): post /20180608/generateDataEncryptionKey', $signingString);
        $this->assertStringContainsString('host: example-crypto.kms.ap-singapore-1.oraclecloud.com', $signingString);
        $this->assertStringContainsString('x-content-sha256: abc123', $signingString);
    }

    public function test_sign_produces_deterministic_signature_for_fixed_inputs(): void
    {
        $body = '{"keyId":"ocid1.key.ap-singapore-1.example"}';
        $url = 'https://example-crypto.kms.ap-singapore-1.oraclecloud.com/20180608/generateDataEncryptionKey';

        $headers = [
            'date' => 'Sun, 12 Jul 2026 02:30:00 GMT',
            'host' => 'example-crypto.kms.ap-singapore-1.oraclecloud.com',
            'content-length' => (string) strlen($body),
            'content-type' => 'application/json',
            'x-content-sha256' => base64_encode(hash('sha256', $body, true)),
        ];

        $signingString = $this->signer->buildSigningString(
            'POST',
            '/20180608/generateDataEncryptionKey',
            $headers,
            ['date', '(request-target)', 'host', 'content-length', 'content-type', 'x-content-sha256'],
        );

        $reflection = new \ReflectionClass(OciRequestSigner::class);
        $method = $reflection->getMethod('signString');
        $method->setAccessible(true);

        $signatureOne = $method->invoke($this->signer, $signingString, $this->config->privateKeyPath);
        $signatureTwo = $method->invoke($this->signer, $signingString, $this->config->privateKeyPath);

        $this->assertNotSame('', $signatureOne);
        $this->assertSame($signatureOne, $signatureTwo);
    }
}
