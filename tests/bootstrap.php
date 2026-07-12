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

require dirname(__DIR__).'/vendor/autoload.php';
