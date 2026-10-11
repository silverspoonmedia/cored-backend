<?php

namespace Tests\Unit\Support\Oci;

use App\Support\Oci\OciConfigPath;
use Tests\TestCase;

class OciConfigPathTest extends TestCase
{
    private const SEP = DIRECTORY_SEPARATOR;

    public function test_production_resolves_the_production_folder(): void
    {
        $base = 'C:'.self::SEP.'base';
        $directory = OciConfigPath::directory('production', $base);

        $this->assertSame($base.self::SEP.'.oci'.self::SEP.'production', $directory);
        $this->assertSame($directory.self::SEP.'config', OciConfigPath::configFile('production', $base));
    }

    public function test_every_non_production_profile_resolves_the_testing_folder(): void
    {
        foreach (['local', 'staging', 'testing', null, ''] as $appEnv) {
            $this->assertSame(
                'testing',
                OciConfigPath::directoryName($appEnv),
                'APP_ENV ['.var_export($appEnv, true).'] must fall back to the testing folder.',
            );
        }
    }

    public function test_config_file_is_derived_from_the_active_app_env(): void
    {
        $this->assertSame(
            base_path('.oci').self::SEP.'testing'.self::SEP.'config',
            OciConfigPath::configFile('local'),
        );
        $this->assertSame(
            base_path('.oci').self::SEP.'production'.self::SEP.'config',
            OciConfigPath::configFile('production'),
        );
    }

    public function test_configured_path_follows_the_running_environment(): void
    {
        $appEnv = env('APP_ENV');

        $this->assertSame(
            OciConfigPath::configFile(is_string($appEnv) ? $appEnv : null),
            config('oci.config_file'),
        );

        // The path always sits inside a profile folder — never the retired root .oci/config.
        $this->assertSame(base_path('.oci'), dirname(dirname((string) config('oci.config_file'))));
    }
}
