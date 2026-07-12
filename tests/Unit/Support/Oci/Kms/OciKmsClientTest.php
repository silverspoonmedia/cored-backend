<?php

namespace Tests\Unit\Support\Oci\Kms;

use App\Support\Oci\Kms\OciKmsClient;
use App\Support\Oci\Kms\OciKmsFailureCategory;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OciKmsClientTest extends TestCase
{
    private string $fixtureDir;

    private string $cryptoEndpoint;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtureDir = base_path('tests/Fixtures/Oci');
        $this->cryptoEndpoint = 'https://example-crypto.kms.ap-singapore-1.oraclecloud.com';

        config([
            'oci.config_file' => $this->fixtureDir.'/config',
            'oci.profile' => 'DEFAULT',
            'oci.kms_key_id' => 'ocid1.key.ap-singapore-1.example',
            'oci.kms_crypto_endpoint' => $this->cryptoEndpoint,
            'oci.http_timeout' => 5,
        ]);
    }

    public function test_verify_connection_returns_configuration_failure_without_http_call(): void
    {
        config(['oci.kms_key_id' => '']);

        Http::fake();

        $result = (new OciKmsClient)->verifyConnection();

        $this->assertFalse($result->successful);
        $this->assertSame(OciKmsFailureCategory::Configuration, $result->category);
        Http::assertNothingSent();
    }

    public function test_verify_connection_succeeds_on_valid_kms_response(): void
    {
        Http::fake([
            $this->cryptoEndpoint.'/20180608/generateDataEncryptionKey' => Http::response([
                'ciphertext' => 'encrypted-dek',
                'id' => 'generated-key-id',
            ], 200),
        ]);

        $result = (new OciKmsClient)->verifyConnection();

        $this->assertTrue($result->successful);
        Http::assertSentCount(1);
    }

    public function test_verify_connection_categorizes_authentication_failure(): void
    {
        Http::fake([
            $this->cryptoEndpoint.'/20180608/generateDataEncryptionKey' => Http::response([
                'code' => 'NotAuthenticated',
                'message' => 'The required information to complete authentication was not provided.',
            ], 401),
        ]);

        $result = (new OciKmsClient)->verifyConnection();

        $this->assertFalse($result->successful);
        $this->assertSame(OciKmsFailureCategory::Authentication, $result->category);
        $this->assertSame('NotAuthenticated', $result->ociCode);
        $this->assertSame(401, $result->httpStatus);
    }

    public function test_verify_connection_categorizes_authorization_failure(): void
    {
        Http::fake([
            $this->cryptoEndpoint.'/20180608/generateDataEncryptionKey' => Http::response([
                'code' => 'NotAuthorizedOrNotFound',
                'message' => 'Authorization failed or requested resource not found.',
            ], 403),
        ]);

        $result = (new OciKmsClient)->verifyConnection();

        $this->assertFalse($result->successful);
        $this->assertSame(OciKmsFailureCategory::Authorization, $result->category);
        $this->assertStringContainsString('IAM policy is missing', (string) $result->message);
    }

    public function test_verify_connection_categorizes_not_found_failure(): void
    {
        Http::fake([
            $this->cryptoEndpoint.'/20180608/generateDataEncryptionKey' => Http::response([
                'code' => 'NotFound',
                'message' => 'Key not found.',
            ], 404),
        ]);

        $result = (new OciKmsClient)->verifyConnection();

        $this->assertFalse($result->successful);
        $this->assertSame(OciKmsFailureCategory::NotFound, $result->category);
        $this->assertStringContainsString('OCI_KMS_KEY_ID', (string) $result->message);
    }
}
