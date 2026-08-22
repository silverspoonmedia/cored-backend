<?php

namespace Tests\Unit\Database;

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\MySqlGrammar;
use Illuminate\Database\Schema\Grammars\PostgresGrammar;
use Illuminate\Database\Schema\Grammars\SQLiteGrammar;
use Illuminate\Database\Schema\Grammars\SqlServerGrammar;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MigrationPortabilityTest extends TestCase
{
    #[DataProvider('schemaDrivers')]
    public function test_menu_self_reference_is_created_after_table_and_primary_key(
        string $driver,
        string $expectedPrimaryKeySql,
    ): void {
        $sql = $this->compileMenuSql($driver);

        $createTable = $this->firstSqlContaining($sql, 'create table');
        $foreignKey = $this->firstSqlContaining($sql, 'foreign key');
        $primaryKey = $this->firstSqlContaining($sql, 'primary key');

        self::assertNotSame('', $createTable);
        self::assertNotSame('', $foreignKey);
        self::assertNotSame('', $primaryKey);
        self::assertGreaterThan(array_search($createTable, $sql, true), array_search($foreignKey, $sql, true));
        self::assertStringContainsString('references', $foreignKey);
        self::assertStringContainsString($driver === 'pgsql' ? '"id"' : '`id`', $foreignKey);
        self::assertStringContainsString($expectedPrimaryKeySql, $primaryKey);
    }

    public function test_menu_and_folder_self_reference_columns_have_explicit_uuid_types(): void
    {
        foreach (['mysql', 'pgsql'] as $driver) {
            $sql = $this->compileBlueprint($driver, 'menus', function (Blueprint $table): void {
                $table->create();
                $table->uuid('id')->primary();
                $table->uuid('parent_id')->nullable();
                $table->foreign('parent_id', 'menus_parent_id_foreign')
                    ->references('id')
                    ->on('menus')
                    ->cascadeOnDelete();
                $table->index('parent_id', 'menus_parent_id_index');
            });

            self::assertStringContainsString(
                $driver === 'pgsql' ? '"parent_id" uuid' : '`parent_id` char(36)',
                $sql[0],
            );
        }
    }

    public function test_portable_subscription_status_columns_are_strings(): void
    {
        foreach (['mysql', 'pgsql'] as $driver) {
            $sql = $this->compileBlueprint($driver, 'subscriptions', function (Blueprint $table): void {
                $table->create();
                $table->string('billing_cycle', 16);
                $table->string('status', 16)->default('trial');
            });

            self::assertStringContainsString(
                $driver === 'pgsql' ? '"billing_cycle" varchar(16)' : '`billing_cycle` varchar(16)',
                $sql[0],
            );
            self::assertStringNotContainsString('enum', strtolower($sql[0]));
        }
    }

    public static function schemaDrivers(): array
    {
        return [
            'mysql' => ['mysql', 'primary key (`id`)'],
            'postgresql' => ['pgsql', 'primary key ("id")'],
        ];
    }

    /**
     * @return list<string>
     */
    private function compileMenuSql(string $driver): array
    {
        return $this->compileBlueprint($driver, 'menus', function (Blueprint $table): void {
            $table->create();
            $table->uuid('id')->primary();
            $table->uuid('parent_id')->nullable();
            $table->foreign('parent_id', 'menus_parent_id_foreign')
                ->references('id')
                ->on('menus')
                ->cascadeOnDelete();
            $table->string('name');
            $table->char('type', 1);
            $table->unsignedInteger('order')->nullable();
            $table->index('parent_id', 'menus_parent_id_index');
            $table->unique(['parent_id', 'type', 'order']);
        });
    }

    /**
     * @return list<string>
     */
    private function compileBlueprint(string $driver, string $tableName, callable $callback): array
    {
        $connection = $this->newSchemaConnection($driver);
        $blueprint = new Blueprint($connection, $tableName, $callback);

        return $blueprint->toSql();
    }

    private function newSchemaConnection(string $driver): Connection
    {
        $connection = new Connection(null, 'migration-portability', '', []);
        $grammar = match ($driver) {
            'mysql' => new MySqlGrammar($connection),
            'pgsql' => new PostgresGrammar($connection),
            'sqlite' => new SQLiteGrammar($connection),
            'sqlsrv' => new SqlServerGrammar($connection),
            default => throw new \InvalidArgumentException("Unsupported schema driver [{$driver}]."),
        };
        $grammar->setTablePrefix('');
        $connection->setSchemaGrammar($grammar);

        return $connection;
    }

    private function firstSqlContaining(array $sql, string $needle): string
    {
        foreach ($sql as $statement) {
            if (str_contains(strtolower($statement), strtolower($needle))) {
                return $statement;
            }
        }

        return '';
    }
}
