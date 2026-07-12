<?php

namespace App\Support\Database;

use RuntimeException;

class DestructiveDatabaseCommandGuard
{
    /** @var list<string> */
    private const GUARDED_COMMANDS = [
        'migrate:fresh',
        'migrate:refresh',
        'db:wipe',
    ];

    public function assertAllowed(string $commandName): void
    {
        if ($this->shouldBlock(
            $commandName,
            (string) config('database.default'),
            $this->destructiveOperationsAllowed(),
        )) {
            throw new RuntimeException($this->blockedMessage($commandName));
        }
    }

    public function shouldBlock(
        string $commandName,
        string $driver,
        bool $allowDestructive,
    ): bool {
        if (! in_array($commandName, self::GUARDED_COMMANDS, true)) {
            return false;
        }

        if ($driver === 'sqlite') {
            return false;
        }

        if ($allowDestructive) {
            return false;
        }

        return in_array($driver, ['mysql', 'pgsql', 'mariadb', 'sqlsrv'], true);
    }

    public function destructiveOperationsAllowed(): bool
    {
        return (bool) config('database.allow_destructive_db', false);
    }

    private function blockedMessage(string $commandName): string
    {
        $database = (string) config('database.connections.'.config('database.default').'.database', '');

        return sprintf(
            'Blocked %s: this command wipes the configured database (%s / %s). '
            .'Set ALLOW_DESTRUCTIVE_DB=true in .env to allow intentional resets, '
            .'or run composer test (sqlite) for the test suite. '
            .'To restore users after accidental data loss: php artisan db:seed.',
            $commandName,
            config('database.default'),
            $database !== '' ? $database : 'unknown',
        );
    }
}
