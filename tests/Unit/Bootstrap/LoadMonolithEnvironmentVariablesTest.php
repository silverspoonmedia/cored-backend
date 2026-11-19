<?php

namespace Tests\Unit\Bootstrap;

use Illuminate\Contracts\Console\Kernel;
use PHPUnit\Framework\TestCase as BaseTestCase;

class LoadMonolithEnvironmentVariablesTest extends BaseTestCase
{
    private const PROCESS_KEYS = ['APP_ENV', 'APP_KEY', 'IPAYMU_API_KEY', 'IPAYMU_VA_KEY'];

    private ?string $envBackup = null;

    private ?string $envLocalBackup = null;

    /** @var array<string, array{env: mixed, server: mixed, putenv: string|false, had_env: bool, had_server: bool}> */
    private array $processEnvSnapshot = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (self::PROCESS_KEYS as $key) {
            $this->processEnvSnapshot[$key] = [
                'env' => $_ENV[$key] ?? null,
                'server' => $_SERVER[$key] ?? null,
                'putenv' => getenv($key),
                'had_env' => array_key_exists($key, $_ENV),
                'had_server' => array_key_exists($key, $_SERVER),
            ];
        }
    }

    protected function tearDown(): void
    {
        $this->restoreFile($this->envPath(), $this->envBackup);
        $this->restoreFile($this->envLocalPath(), $this->envLocalBackup);
        $this->restoreProcessEnv();

        parent::tearDown();
    }

    public function test_it_loads_active_env_when_app_env_is_preset(): void
    {
        $this->envBackup = $this->backupFile($this->envPath());
        $this->envLocalBackup = $this->backupFile($this->envLocalPath());

        file_put_contents($this->envPath(), implode("\n", [
            'APP_ENV="local"',
            'APP_KEY="base64:'.base64_encode(random_bytes(32)).'"',
            'IPAYMU_VA_KEY="va-from-active-env"',
            'IPAYMU_API_KEY="api-key-from-active-env"',
        ])."\n");

        file_put_contents($this->envLocalPath(), implode("\n", [
            'APP_ENV="local"',
            'APP_KEY="base64:'.base64_encode(random_bytes(32)).'"',
            'IPAYMU_VA_KEY=""',
            'IPAYMU_API_KEY=""',
        ])."\n");

        $_SERVER['APP_ENV'] = 'local';
        putenv('APP_ENV=local');
        $_ENV['APP_ENV'] = 'local';

        foreach (['IPAYMU_API_KEY', 'IPAYMU_VA_KEY'] as $key) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }

        require $this->basePath('vendor/autoload.php');

        $app = require $this->basePath('bootstrap/app.php');
        $app->make(Kernel::class)->bootstrap();

        $this->assertSame('api-key-from-active-env', $app['config']->get('payment.gateways.ipaymu.api_key'));
        $this->assertSame('va-from-active-env', $app['config']->get('payment.gateways.ipaymu.va'));
    }

    private function basePath(string $relative = ''): string
    {
        $root = dirname(__DIR__, 3);

        return $relative === '' ? $root : $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }

    private function envPath(): string
    {
        return $this->basePath('.env');
    }

    private function envLocalPath(): string
    {
        return $this->basePath('.env.local');
    }

    private function backupFile(string $path): ?string
    {
        return is_file($path) ? (string) file_get_contents($path) : null;
    }

    private function restoreFile(string $path, ?string $backup): void
    {
        if ($backup !== null) {
            file_put_contents($path, $backup);

            return;
        }

        if (is_file($path)) {
            unlink($path);
        }
    }

    private function restoreProcessEnv(): void
    {
        foreach ($this->processEnvSnapshot as $key => $snapshot) {
            if ($snapshot['putenv'] === false) {
                putenv($key);
            } else {
                putenv("{$key}={$snapshot['putenv']}");
            }

            if ($snapshot['had_env']) {
                $_ENV[$key] = $snapshot['env'];
            } else {
                unset($_ENV[$key]);
            }

            if ($snapshot['had_server']) {
                $_SERVER[$key] = $snapshot['server'];
            } else {
                unset($_SERVER[$key]);
            }
        }
    }
}
