<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseMigrationDiagnoseCommand extends Command
{
    protected $signature = 'db:migration-diagnose {table=menus : Table to inspect}';

    protected $description = 'Inspect database context, migration history, indexes, and foreign keys for a migration failure';

    public function handle(): int
    {
        $table = (string) $this->argument('table');
        $connection = DB::connection();
        config(['cache.default' => 'array']);

        $this->line('driver: '.(string) $connection->getDriverName());
        $this->line('database: '.(string) $connection->getDatabaseName());
        $this->line('schema: '.$this->currentSchema($connection->getDriverName()));
        $this->line('table_exists: '.(Schema::hasTable($table) ? 'yes' : 'no'));

        if (! Schema::hasTable($table)) {
            $this->line('migration_rows: '.$this->migrationRows($table));

            return self::SUCCESS;
        }

        $this->table(['index', 'columns', 'unique', 'primary'], array_map(
            static fn (array $index): array => [
                (string) ($index['name'] ?? ''),
                implode(',', (array) ($index['columns'] ?? [])),
                ($index['unique'] ?? false) ? 'yes' : 'no',
                ($index['primary'] ?? false) ? 'yes' : 'no',
            ],
            Schema::getIndexes($table),
        ));

        $this->table(['name', 'columns', 'referenced_table', 'referenced_columns'], array_map(
            static fn (array $foreign): array => [
                (string) ($foreign['name'] ?? ''),
                implode(',', (array) ($foreign['columns'] ?? [])),
                (string) ($foreign['foreign_table'] ?? ''),
                implode(',', (array) ($foreign['foreign_columns'] ?? [])),
            ],
            Schema::getForeignKeys($table),
        ));

        $this->line('migration_rows: '.$this->migrationRows($table));

        return self::SUCCESS;
    }

    private function currentSchema(string $driver): string
    {
        return match ($driver) {
            'pgsql' => (string) DB::selectOne('select current_schema() as db_schema')->db_schema,
            'mysql', 'mariadb' => (string) DB::selectOne('select database() as db_schema')->db_schema,
            default => 'n/a',
        };
    }

    private function migrationRows(string $table): string
    {
        if (! Schema::hasTable('migrations')) {
            return 'migration repository missing';
        }

        $needle = '%'.$table.'%';
        $rows = DB::table('migrations')
            ->where('migration', 'like', $needle)
            ->orderBy('batch')
            ->orderBy('migration')
            ->pluck('migration');

        return $rows->isEmpty() ? 'none' : $rows->implode(', ');
    }
}
