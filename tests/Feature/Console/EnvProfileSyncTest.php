<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Ensures every committed env template exposes the same variable keys as the
 * master catalog (.env.example). Prevents profile drift when adding/removing vars.
 */
class EnvProfileSyncTest extends TestCase
{
    /** @var array<int, string> */
    private const PROFILE_FILES = [
        '.env.local.example',
        '.env.staging.example',
        '.env.production.example',
    ];

    public function test_profile_templates_have_the_same_keys_as_master_catalog(): void
    {
        $masterKeys = $this->extractEnvKeys(base_path('.env.example'));

        $this->assertNotEmpty($masterKeys, 'Master catalog .env.example has no active keys.');

        foreach (self::PROFILE_FILES as $profileFile) {
            $path = base_path($profileFile);
            $this->assertFileExists($path, "Missing profile template: {$profileFile}");

            $profileKeys = $this->extractEnvKeys($path);

            $missingInProfile = array_diff($masterKeys, $profileKeys);
            $extraInProfile = array_diff($profileKeys, $masterKeys);

            $this->assertSame(
                [],
                $missingInProfile,
                "{$profileFile} is missing keys from .env.example: ".implode(', ', $missingInProfile),
            );

            $this->assertSame(
                [],
                $extraInProfile,
                "{$profileFile} has keys not in .env.example: ".implode(', ', $extraInProfile),
            );
        }
    }

    /**
     * @return array<int, string>
     */
    private function extractEnvKeys(string $path): array
    {
        $keys = [];

        foreach (File::lines($path) as $line) {
            $line = trim((string) $line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (! preg_match('/^([A-Z][A-Z0-9_]*)=/', $line, $matches)) {
                continue;
            }

            $keys[] = $matches[1];
        }

        $keys = array_values(array_unique($keys));
        sort($keys);

        return $keys;
    }
}
