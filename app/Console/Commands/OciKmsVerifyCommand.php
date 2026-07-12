<?php

namespace App\Console\Commands;

use App\Support\Oci\Kms\OciKmsClient;
use App\Support\Oci\Kms\OciKmsFailureCategory;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'oci:kms-verify')]
class OciKmsVerifyCommand extends Command
{
    protected $signature = 'oci:kms-verify';

    protected $description = 'Verify connectivity to Oracle Cloud KMS using generateDataEncryptionKey';

    public function __construct(
        private readonly OciKmsClient $client,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $result = $this->client->verifyConnection();

        if ($result->successful) {
            $this->info('OCI KMS connectivity verified successfully.');

            return self::SUCCESS;
        }

        $category = $result->category ?? OciKmsFailureCategory::Unknown;
        $this->error('OCI KMS connectivity check failed ['.$category->value.'].');

        if ($result->message !== null) {
            $this->line($result->message);
        }

        if ($result->ociCode !== null) {
            $this->line('OCI code: '.$result->ociCode);
        }

        if ($result->httpStatus !== null) {
            $this->line('HTTP status: '.$result->httpStatus);
        }

        if ($category === OciKmsFailureCategory::Authorization) {
            $this->newLine();
            $this->warn('Likely IAM policy issue. Review vault/key permissions for the API user before retrying.');
        }

        return $category->exitCode();
    }
}
