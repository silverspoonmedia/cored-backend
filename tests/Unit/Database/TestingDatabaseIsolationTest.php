<?php

namespace Tests\Unit\Database;

use Tests\TestCase;

class TestingDatabaseIsolationTest extends TestCase
{
    public function test_default_connection_is_sqlite(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }
}
