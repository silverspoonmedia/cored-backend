<?php

namespace Tests\Unit\Support;

use App\Support\EnvironmentVariableCatalog;
use Tests\TestCase;

class CorsExtraOriginsConfigTest extends TestCase
{
    private ?string $corsBackup = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->corsBackup = (string) getenv('CORS_EXTRA_ORIGINS');
    }

    protected function tearDown(): void
    {
        if ($this->corsBackup === '') {
            putenv('CORS_EXTRA_ORIGINS');
        } else {
            putenv("CORS_EXTRA_ORIGINS={$this->corsBackup}");
        }

        parent::tearDown();
    }

    public function test_catalog_registers_cors_extra_origins_as_string(): void
    {
        $types = EnvironmentVariableCatalog::types();

        $this->assertArrayHasKey('CORS_EXTRA_ORIGINS', $types);
        $this->assertSame(
            EnvironmentVariableCatalog::TYPE_STRING,
            $types['CORS_EXTRA_ORIGINS'],
        );
    }

    public function test_empty_cors_extra_origins_adds_no_extras(): void
    {
        putenv('CORS_EXTRA_ORIGINS=');

        $origins = $this->reloadCorsAllowedOrigins();

        // APP_URL + SPA_URL only; no stray empty-string origin.
        $this->assertNotContains('', $origins);
        $this->assertNotContains('://', $origins);
    }

    public function test_single_extra_origin_is_appended(): void
    {
        putenv('CORS_EXTRA_ORIGINS=https://preview.example');

        $origins = $this->reloadCorsAllowedOrigins();

        $this->assertContains('https://preview.example', $origins);
    }

    public function test_multiple_extra_origins_are_split(): void
    {
        putenv('CORS_EXTRA_ORIGINS=https://a.example,https://b.example');

        $origins = $this->reloadCorsAllowedOrigins();

        $this->assertContains('https://a.example', $origins);
        $this->assertContains('https://b.example', $origins);
    }

    public function test_origins_are_trimmed(): void
    {
        putenv('CORS_EXTRA_ORIGINS=  https://a.example  ,  https://b.example  ');

        $origins = $this->reloadCorsAllowedOrigins();

        $this->assertContains('https://a.example', $origins);
        $this->assertContains('https://b.example', $origins);
        $this->assertNotContains('  https://a.example  ', $origins);
    }

    public function test_trailing_slash_is_normalized(): void
    {
        putenv('CORS_EXTRA_ORIGINS=https://a.example/');

        $origins = $this->reloadCorsAllowedOrigins();

        $this->assertContains('https://a.example', $origins);
        $this->assertNotContains('https://a.example/', $origins);
    }

    public function test_duplicates_are_collapsed(): void
    {
        putenv('CORS_EXTRA_ORIGINS=https://a.example,https://a.example');

        $origins = $this->reloadCorsAllowedOrigins();

        $this->assertSame(1, array_count_values($origins)['https://a.example']);
    }

    public function test_empty_segments_between_commas_are_filtered(): void
    {
        putenv('CORS_EXTRA_ORIGINS=https://a.example,,https://b.example,');

        $origins = $this->reloadCorsAllowedOrigins();

        $this->assertContains('https://a.example', $origins);
        $this->assertContains('https://b.example', $origins);
        $this->assertNotContains('', $origins);
    }

    /**
     * @return array<int, string>
     */
    private function reloadCorsAllowedOrigins(): array
    {
        $config = require config_path('cors.php');

        config(['cors.allowed_origins' => $config['allowed_origins']]);

        return $config['allowed_origins'];
    }
}
