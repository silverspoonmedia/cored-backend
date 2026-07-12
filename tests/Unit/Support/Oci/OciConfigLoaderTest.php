<?php

namespace Tests\Unit\Support\Oci;

use App\Support\Oci\OciConfigLoader;
use Tests\TestCase;

class OciConfigLoaderTest extends TestCase
{
    private string $fixtureDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtureDir = base_path('tests/Fixtures/Oci');
    }

    public function test_is_configured_returns_true_with_valid_fixture(): void
    {
        config([
            'oci.config_file' => $this->fixtureDir.'/config',
            'oci.profile' => 'DEFAULT',
            'oci.kms_key_id' => 'ocid1.key.ap-singapore-1.example',
            'oci.kms_crypto_endpoint' => 'https://example-crypto.kms.ap-singapore-1.oraclecloud.com',
        ]);

        $loader = new OciConfigLoader;

        $this->assertTrue($loader->isConfigured());
        $this->assertNull($loader->configurationError());
    }

    public function test_load_resolves_relative_key_file_path(): void
    {
        config([
            'oci.config_file' => $this->fixtureDir.'/config',
            'oci.profile' => 'DEFAULT',
            'oci.kms_key_id' => 'ocid1.key.ap-singapore-1.example',
            'oci.kms_crypto_endpoint' => 'https://example-crypto.kms.ap-singapore-1.oraclecloud.com',
        ]);

        $config = (new OciConfigLoader)->load();

        $this->assertSame('ocid1.tenancy.oc1..aaaaaaaaexampletenancy', $config->tenancy);
        $this->assertSame('ocid1.user.oc1..aaaaaaaaexampleuser', $config->user);
        $this->assertSame('aa:bb:cc:dd:ee:ff:00:11:22:33:44:55:66:77:88:99', $config->fingerprint);
        $this->assertSame('ap-singapore-1', $config->region);
        $this->assertSame($this->fixtureDir.DIRECTORY_SEPARATOR.'test_api_key.pem', $config->privateKeyPath);
        $this->assertSame(
            'ocid1.tenancy.oc1..aaaaaaaaexampletenancy/ocid1.user.oc1..aaaaaaaaexampleuser/aa:bb:cc:dd:ee:ff:00:11:22:33:44:55:66:77:88:99',
            $config->keyId(),
        );
    }

    public function test_is_configured_returns_false_when_kms_key_id_missing(): void
    {
        config([
            'oci.config_file' => $this->fixtureDir.'/config',
            'oci.profile' => 'DEFAULT',
            'oci.kms_key_id' => '',
            'oci.kms_crypto_endpoint' => 'https://example-crypto.kms.ap-singapore-1.oraclecloud.com',
        ]);

        $loader = new OciConfigLoader;

        $this->assertFalse($loader->isConfigured());
        $this->assertSame('OCI_KMS_KEY_ID is not set.', $loader->configurationError()['message']);
    }

    public function test_load_throws_when_profile_missing(): void
    {
        config([
            'oci.config_file' => $this->fixtureDir.'/config',
            'oci.profile' => 'MISSING',
            'oci.kms_key_id' => 'ocid1.key.ap-singapore-1.example',
            'oci.kms_crypto_endpoint' => 'https://example-crypto.kms.ap-singapore-1.oraclecloud.com',
        ]);

        $this->expectExceptionMessage('OCI profile [MISSING] was not found in the config file.');

        (new OciConfigLoader)->load();
    }
}
