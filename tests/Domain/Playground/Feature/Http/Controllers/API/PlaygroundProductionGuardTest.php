<?php

namespace Tests\Domain\Playground\Feature\Http\Controllers\API;

use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Playground routes are wrapped in `if (! app()->environment('production'))`.
 * Verified via a subprocess so route registration sees APP_ENV before boot.
 */
class PlaygroundProductionGuardTest extends TestCase
{
    public function test_playground_routes_are_not_registered_in_production(): void
    {
        $output = $this->artisanRouteList('production', 'api/playground');

        $this->assertStringContainsString(
            "doesn't have any routes matching",
            $output,
            'Playground routes must not register when APP_ENV=production.',
        );
    }

    public function test_playground_routes_are_registered_outside_production(): void
    {
        $output = $this->artisanRouteList('local', 'api/playground');

        $this->assertStringContainsString('api/playground/users', $output);
        $this->assertStringContainsString('api/playground/orders', $output);
    }

    public function test_ping_remains_available_in_production(): void
    {
        $output = $this->artisanRouteList('production', 'api/ping');

        $this->assertStringContainsString('api/ping', $output);
    }

    private function artisanRouteList(string $environment, string $path): string
    {
        $process = new Process(
            [PHP_BINARY, 'artisan', 'route:list', '--path='.$path],
            base_path(),
            $this->subprocessEnvironment($environment),
        );

        $process->run();

        $this->assertTrue(
            $process->isSuccessful(),
            'artisan route:list failed: '.$process->getErrorOutput(),
        );

        return $process->getOutput().$process->getErrorOutput();
    }

    /**
     * @return array<string, string>
     */
    private function subprocessEnvironment(string $environment): array
    {
        return [
            'APP_ENV' => $environment,
            'APP_KEY' => config('app.key'),
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'JWT_SECRET' => 'testing-jwt-secret-key-with-enough-length',
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'MAIL_MAILER' => 'array',
            'BROADCAST_CONNECTION' => 'null',
            'HCAPTCHA_ENABLED' => 'false',
        ];
    }
}
