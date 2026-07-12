<?php

namespace Tests\Unit\Database;

use App\Support\Database\DestructiveDatabaseCommandGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DestructiveDatabaseCommandGuardTest extends TestCase
{
    private DestructiveDatabaseCommandGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();

        $this->guard = new DestructiveDatabaseCommandGuard;
    }

    #[DataProvider('allowedCommandMatrix')]
    public function test_should_not_block_allowed_commands(
        string $command,
        string $driver,
        bool $allowDestructive,
    ): void {
        $this->assertFalse($this->guard->shouldBlock($command, $driver, $allowDestructive));
    }

    public static function allowedCommandMatrix(): array
    {
        return [
            'sqlite test suite' => ['migrate:fresh', 'sqlite', false],
            'mysql with allow flag' => ['migrate:fresh', 'mysql', true],
            'unrelated command' => ['migrate', 'mysql', false],
        ];
    }

    #[DataProvider('blockedCommandMatrix')]
    public function test_should_block_destructive_commands_on_non_sqlite(
        string $command,
        string $driver,
        bool $allowDestructive,
    ): void {
        $this->assertTrue($this->guard->shouldBlock($command, $driver, $allowDestructive));
    }

    public static function blockedCommandMatrix(): array
    {
        return [
            'migrate fresh local mysql' => ['migrate:fresh', 'mysql', false],
            'db wipe local mysql' => ['db:wipe', 'mysql', false],
            'migrate refresh local mysql' => ['migrate:refresh', 'mysql', false],
            'migrate refresh local pgsql' => ['migrate:refresh', 'pgsql', false],
            'migrate fresh production mysql' => ['migrate:fresh', 'mysql', false],
        ];
    }
}
