<?php

namespace Tests\Unit\Support;

use App\Support\EnvironmentVariableCatalog;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EnvironmentVariableCatalogFormatTest extends TestCase
{
    /**
     * @return array<string, array{0: ?string, 1: string, 2: string}>
     */
    public static function formatCases(): array
    {
        return [
            'string unquoted becomes quoted' => [
                EnvironmentVariableCatalog::TYPE_STRING,
                'HS256',
                '"HS256"',
            ],
            'string already quoted stays quoted' => [
                EnvironmentVariableCatalog::TYPE_STRING,
                '"local"',
                '"local"',
            ],
            'string empty stays empty quoted' => [
                EnvironmentVariableCatalog::TYPE_STRING,
                '""',
                '""',
            ],
            'string with escaped quotes' => [
                EnvironmentVariableCatalog::TYPE_STRING,
                '"say \\"hi\\""',
                '"say \\"hi\\""',
            ],
            'string interpolation stays quoted' => [
                EnvironmentVariableCatalog::TYPE_STRING,
                '"${APP_NAME}"',
                '"${APP_NAME}"',
            ],
            'bool quoted becomes unquoted' => [
                EnvironmentVariableCatalog::TYPE_BOOL,
                '"true"',
                'true',
            ],
            'bool already unquoted' => [
                EnvironmentVariableCatalog::TYPE_BOOL,
                'false',
                'false',
            ],
            'int quoted becomes unquoted' => [
                EnvironmentVariableCatalog::TYPE_INT,
                '"3306"',
                '3306',
            ],
            'null empty becomes null' => [
                EnvironmentVariableCatalog::TYPE_NULL,
                '""',
                'null',
            ],
            'null literal' => [
                EnvironmentVariableCatalog::TYPE_NULL,
                'null',
                'null',
            ],
            'null typed domain escape hatch' => [
                EnvironmentVariableCatalog::TYPE_NULL,
                '".localhost"',
                '".localhost"',
            ],
            'unknown orphan string gets quoted' => [
                null,
                'orphan-value',
                '"orphan-value"',
            ],
            'unknown orphan bool kept' => [
                null,
                'true',
                'true',
            ],
            'unknown orphan int kept' => [
                null,
                '42',
                '42',
            ],
        ];
    }

    #[DataProvider('formatCases')]
    public function test_format_raw_value(?string $type, string $raw, string $expected): void
    {
        $this->assertSame($expected, EnvironmentVariableCatalog::formatRawValue($type, $raw));
    }

    public function test_string_type_requires_quotes(): void
    {
        $this->assertTrue(EnvironmentVariableCatalog::valueMatchesType(
            EnvironmentVariableCatalog::TYPE_STRING,
            '"HS256"',
        ));
        $this->assertFalse(EnvironmentVariableCatalog::valueMatchesType(
            EnvironmentVariableCatalog::TYPE_STRING,
            'HS256',
        ));
    }

    public function test_semantic_value_unescapes(): void
    {
        $this->assertSame('My "App"', EnvironmentVariableCatalog::semanticValue('"My \\"App\\""'));
        $this->assertSame('a\\b', EnvironmentVariableCatalog::semanticValue('"a\\\\b"'));
        $this->assertSame('plain', EnvironmentVariableCatalog::semanticValue('plain'));
    }
}
