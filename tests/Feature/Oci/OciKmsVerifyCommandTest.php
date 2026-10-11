<?php

namespace Tests\Feature\Oci;

use App\Support\Oci\Kms\OciKmsFailureCategory;
use Tests\TestCase;

class OciKmsVerifyCommandTest extends TestCase
{
    public function test_fails_with_configuration_exit_code_when_oci_config_is_unparsable(): void
    {
        config([
            'oci.config_file' => base_path('tests/Fixtures/Oci/config-malformed'),
            'oci.profile' => 'DEFAULT',
            'oci.kms_key_id' => 'ocid1.key.ap-singapore-1.example',
            'oci.kms_crypto_endpoint' => 'https://example-crypto.kms.ap-singapore-1.oraclecloud.com',
        ]);

        $this->artisan('oci:kms-verify')
            ->expectsOutputToContain('OCI KMS connectivity check failed [configuration].')
            ->assertExitCode(OciKmsFailureCategory::Configuration->exitCode());
    }
}
