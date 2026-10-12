<?php

/**
 * PHPUnit bootstrap — isolate tests from cached config and the dev MySQL database.
 *
 * When bootstrap/cache/config.php exists, Laravel ignores phpunit.xml DB overrides
 * and RefreshDatabase can run migrate:fresh against the real monolith database.
 */
$configCache = dirname(__DIR__).'/bootstrap/cache/config.php';

if (is_file($configCache)) {
    unlink($configCache);
}

foreach ([
    'APP_ENV' => 'testing',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:',
    'DB_URL' => '',
] as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

/*
|--------------------------------------------------------------------------
| One suite at a time
|--------------------------------------------------------------------------
|
| `bootstrap/cache` and the temporary domain fixtures that the `domain:make` /
| `domain:remove` tests create are per-repo, not per-process. Booting (and
| `domain:remove` itself) writes the config cache through `Filesystem::replace()`,
| whose `rename()` fails on Windows with `Access is denied` when a second run is
| touching the same path — and `tempnam(dirname($path), basename($path))` puts the
| temporary file inside `bootstrap/cache` too, so even a rename that succeeds leaves
| the loser's temp files behind.
|
| What that produced was worse than a red gate: six errors in whichever run lost,
| all of them in files the diff under review did not touch (`bootstrap/cache/services.php`
| rename denied, missing `ZzDemo`/`zz-child.php` fixtures), which reads as a broken
| change and is really a broken harness. A second concurrent run stops here instead,
| before any test boots, and says which file holds the lock.
|
| The lock is advisory (`flock`) and released by the OS when the process exits, so a
| killed run cannot wedge the suite. Delete the file only if no run is active.
*/
$phpunitLockPath = dirname(__DIR__).'/bootstrap/cache/.phpunit.lock';
$phpunitLock = @fopen($phpunitLockPath, 'c');

if ($phpunitLock !== false && ! flock($phpunitLock, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, PHP_EOL);
    fwrite(STDERR, 'Another PHPUnit run holds '.$phpunitLockPath.'.'.PHP_EOL);
    fwrite(STDERR, 'The suite is not parallel-safe: `bootstrap/cache` and the domain fixtures are shared,'.PHP_EOL);
    fwrite(STDERR, 'and a concurrent run reports phantom failures in files this one never touched.'.PHP_EOL);
    fwrite(STDERR, 'Wait for the other run to finish (or stop it) and retry.'.PHP_EOL);

    exit(1);
}

if ($phpunitLock !== false) {
    register_shutdown_function(static function () use ($phpunitLock): void {
        flock($phpunitLock, LOCK_UN);
        fclose($phpunitLock);
    });
}

require dirname(__DIR__).'/vendor/autoload.php';
