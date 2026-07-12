<?php

namespace Tests\Feature\Console;

use App\Support\Config\ConfigSecretCheck;
use App\Support\Config\ConfigSecretVerificationResult;
use App\Support\Config\ConfigSecretVerifier;
use Illuminate\Support\Facades\File;
use Mockery\MockInterface;
use Tests\TestCase;

class ConfigVerifySecretsCommandTest extends TestCase
{
    public function test_fails_when_required_runtime_config_is_missing(): void
    {
        config([
            'app.key' => 'base64:test',
            'jwt.secret' => 'jwt-secret',
            'payment.gateways.ipaymu.api_key' => '',
            'payment.gateways.ipaymu.va' => '',
            'encryption.kms_enabled' => false,
        ]);

        $this->artisan('config:verify-secrets')
            ->assertFailed();
    }

    public function test_passes_when_required_runtime_config_is_present(): void
    {
        config([
            'app.key' => 'base64:test',
            'jwt.secret' => 'jwt-secret',
            'payment.gateways.ipaymu.api_key' => 'sandbox-key',
            'payment.gateways.ipaymu.va' => 'va',
            'encryption.kms_enabled' => false,
        ]);

        $this->artisan('config:verify-secrets')
            ->assertSuccessful();
    }

    public function test_fresh_option_delegates_to_verifier(): void
    {
        $this->mock(ConfigSecretVerifier::class, function (MockInterface $mock): void {
            $mock->shouldReceive('verifyFresh')
                ->once()
                ->andReturn(new ConfigSecretVerificationResult([
                    new ConfigSecretCheck('app.key', 'APP_KEY', 'ok'),
                ], fromFreshBootstrap: true));
        });

        $this->artisan('config:verify-secrets', ['--fresh' => true])
            ->assertSuccessful();
    }
}
