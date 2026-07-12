<?php

namespace Tests\Feature\Oci;

use App\Support\Oci\Kms\OciKmsClient;
use App\Support\Oci\Kms\OciKmsFailureCategory;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('oci')]
class OciKmsConnectionIntegrationTest extends TestCase
{
    public function test_verify_connection_against_real_oci_kms_when_enabled(): void
    {
        if (env('OCI_KMS_INTEGRATION_TEST') !== 'true') {
            $this->markTestSkipped('Set OCI_KMS_INTEGRATION_TEST=true to run the live OCI KMS connectivity test.');
        }

        $result = (new OciKmsClient)->verifyConnection();

        if (! $result->successful && $result->category === OciKmsFailureCategory::Authorization) {
            $this->fail(
                'OCI KMS authorization failed. Review IAM policy for the API user before retrying. '
                .'Message: '.($result->message ?? 'n/a')
                .($result->ociCode ? ' Code: '.$result->ociCode : ''),
            );
        }

        $this->assertTrue(
            $result->successful,
            $result->message ?? 'OCI KMS connectivity test failed.',
        );
    }
}
