<?php

namespace Tests\Unit\Support\Config;

use App\Support\Config\ConfigSecretVerifier;
use App\Support\Config\FreshConfigurationLoader;
use Illuminate\Config\Repository;
use Tests\TestCase;

class ConfigSecretVerifierTest extends TestCase
{
    public function test_fails_when_oci_endpoint_uses_template_placeholder(): void
    {
        $config = new Repository([
            'app' => ['key' => 'base64:test'],
            'jwt' => ['secret' => 'jwt-secret'],
            'payment' => [
                'gateways' => [
                    'ipaymu' => ['api_key' => 'key', 'va' => 'va'],
                    'midtrans' => ['server_key' => '', 'client_key' => ''],
                ],
            ],
            'encryption' => [
                'kms_enabled' => true,
                'blind_index_pepper' => 'pepper',
            ],
            'oci' => [
                'kms_key_id' => 'ocid1.key.ap-singapore-1.xxxxxxxxx',
                'kms_crypto_endpoint' => 'https://xxxxxxxx-crypto.kms.ap-singapore-1.oraclecloud.com',
            ],
        ]);

        $result = (new ConfigSecretVerifier(new FreshConfigurationLoader))->verify($config);

        $this->assertFalse($result->passed());
        $this->assertGreaterThanOrEqual(2, $result->errorCount());
    }

    public function test_passes_when_required_secrets_are_present(): void
    {
        $config = new Repository([
            'app' => ['key' => 'base64:test'],
            'jwt' => ['secret' => 'jwt-secret'],
            'payment' => [
                'gateways' => [
                    'ipaymu' => ['api_key' => 'key', 'va' => 'va'],
                    'midtrans' => ['server_key' => '', 'client_key' => ''],
                ],
            ],
            'encryption' => [
                'kms_enabled' => true,
                'blind_index_pepper' => 'pepper',
            ],
            'oci' => [
                'kms_key_id' => 'ocid1.key.oc1.ap-singapore-1.realkey',
                'kms_crypto_endpoint' => 'https://realvault-crypto.kms.ap-singapore-1.oci.oraclecloud.com',
            ],
        ]);

        $result = (new ConfigSecretVerifier(new FreshConfigurationLoader))->verify($config);

        $this->assertTrue($result->passed());
    }

    public function test_skips_oci_checks_when_kms_disabled(): void
    {
        $config = new Repository([
            'app' => ['key' => 'base64:test'],
            'jwt' => ['secret' => 'jwt-secret'],
            'payment' => [
                'gateways' => [
                    'ipaymu' => ['api_key' => 'key', 'va' => 'va'],
                    'midtrans' => ['server_key' => '', 'client_key' => ''],
                ],
            ],
            'encryption' => [
                'kms_enabled' => false,
                'blind_index_pepper' => '',
            ],
            'oci' => [
                'kms_key_id' => '',
                'kms_crypto_endpoint' => '',
            ],
        ]);

        $result = (new ConfigSecretVerifier(new FreshConfigurationLoader))->verify($config);

        $this->assertTrue($result->passed());
    }
}
