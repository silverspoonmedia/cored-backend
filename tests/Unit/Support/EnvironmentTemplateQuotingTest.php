<?php

namespace Tests\Unit\Support;

use App\Support\EnvironmentTemplateParser;
use App\Support\EnvironmentVariableCatalog;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EnvironmentTemplateQuotingTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function templatePathsProvider(): array
    {
        return [
            'master catalog' => ['.env.example'],
            'local profile' => ['.env.local.example'],
            'staging profile' => ['.env.staging.example'],
            'production profile' => ['.env.production.example'],
        ];
    }

    #[DataProvider('templatePathsProvider')]
    public function test_committed_templates_follow_typed_quoting_rules(string $relativePath): void
    {
        $path = base_path($relativePath);
        $assignments = EnvironmentTemplateParser::parseUniqueAssignments(File::get($path));
        $catalog = EnvironmentVariableCatalog::all();

        $this->assertNotEmpty($assignments, "No assignments parsed from {$relativePath}");

        foreach ($assignments as $name => $rawValue) {
            $this->assertArrayHasKey(
                $name,
                $catalog,
                "Unknown env key [{$name}] in {$relativePath} — add it to EnvironmentVariableCatalog.",
            );

            $this->assertTrue(
                EnvironmentVariableCatalog::valueMatchesType($catalog[$name], $rawValue),
                "Invalid {$catalog[$name]} value for [{$name}] in {$relativePath}: {$rawValue}",
            );
        }
    }

    public function test_catalog_covers_every_assignment_in_master_template(): void
    {
        $assignments = EnvironmentTemplateParser::parseUniqueAssignments(File::get(base_path('.env.example')));
        $catalogKeys = array_keys(EnvironmentVariableCatalog::all());
        $assignmentKeys = array_keys($assignments);

        sort($catalogKeys);
        sort($assignmentKeys);

        $this->assertSame($catalogKeys, $assignmentKeys);
    }
}
