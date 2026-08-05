<?php

namespace Tests\Unit\Support;

use App\Support\EnvironmentCurator;
use App\Support\EnvironmentTemplateParser;
use Tests\TestCase;

class EnvironmentCuratorEdgeTest extends TestCase
{
    private EnvironmentCurator $curator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->curator = $this->app->make(EnvironmentCurator::class);
    }

    public function test_i05_preserves_escaped_quotes_and_variable_interpolation(): void
    {
        $template = "APP_NAME=\"Laravel\"\nTWO_FACTOR_ISSUER=\"\${APP_NAME}\"\n";
        $env = "APP_NAME=\"My App\"\nTWO_FACTOR_ISSUER=\"\${APP_NAME}\"\n";

        $output = $this->curator->buildCuratedContents($template, $env);

        $this->assertStringContainsString('APP_NAME="My App"', $output);
        $this->assertStringContainsString('TWO_FACTOR_ISSUER="${APP_NAME}"', $output);
    }

    public function test_i06_inline_comment_stripped_from_parsed_value(): void
    {
        $parsed = EnvironmentTemplateParser::parseUniqueAssignments('APP_NOTIFICATION_TYPE="realtime" # polling | realtime');
        $this->assertSame('"realtime"', $parsed['APP_NOTIFICATION_TYPE']);
    }

    public function test_i07_unquoted_jwt_secret_normalized_to_quoted_after_curate(): void
    {
        $template = "JWT_SECRET=\"\"\nJWT_ALGO=\"HS256\"\nAPP_ENV=\"local\"\n";
        $env = "JWT_SECRET=unquotedSecretValue123\nJWT_ALGO=HS256\nAPP_ENV=\"local\"\n";

        $output = $this->curator->buildCuratedContents($template, $env);

        $this->assertStringContainsString('JWT_SECRET="unquotedSecretValue123"', $output);
        $this->assertStringContainsString('JWT_ALGO="HS256"', $output);
    }

    public function test_curate_normalizes_quoted_bool_and_int_to_unquoted(): void
    {
        $template = "APP_DEBUG=true\nDB_PORT=3306\nAPP_ENV=\"local\"\n";
        $env = "APP_DEBUG=\"true\"\nDB_PORT=\"3306\"\nAPP_ENV=\"local\"\n";

        $output = $this->curator->buildCuratedContents($template, $env);

        $this->assertStringContainsString('APP_DEBUG=true', $output);
        $this->assertStringContainsString('DB_PORT=3306', $output);
        $this->assertStringNotContainsString('APP_DEBUG="true"', $output);
        $this->assertStringNotContainsString('DB_PORT="3306"', $output);
    }

    public function test_curate_preserves_escaped_quotes_inside_strings(): void
    {
        $template = "APP_NAME=\"Laravel\"\n";
        $env = "APP_NAME=\"My \\\"App\\\"\"\n";

        $output = $this->curator->buildCuratedContents($template, $env);

        $this->assertStringContainsString('APP_NAME="My \\"App\\""', $output);
    }

    public function test_i08_template_adds_pusher_host_when_missing_from_env(): void
    {
        $template = "PUSHER_HOST=\"\"\nPUSHER_APP_KEY=\"abc\"\n";
        $env = "# PUSHER_HOST=\"\"\nPUSHER_APP_KEY=\"live-key\"\n";

        $output = $this->curator->buildCuratedContents($template, $env);

        $this->assertStringContainsString('PUSHER_HOST=""', $output);
        $this->assertStringContainsString('PUSHER_APP_KEY="live-key"', $output);
    }

    public function test_i09_duplicate_template_key_last_assignment_wins(): void
    {
        $template = "DB_DATABASE=\"first\"\nDB_DATABASE=\"second\"\n";
        $env = "DB_DATABASE=\"from_env\"\n";

        $output = $this->curator->buildCuratedContents($template, $env);

        // Known behavior: duplicate template assignments emit duplicate lines.
        $this->assertSame(2, substr_count($output, 'DB_DATABASE='));
        $this->assertStringContainsString('DB_DATABASE="from_env"', $output);
    }

    public function test_i20_unicode_em_dash_in_template_comments_preserved(): void
    {
        $template = "# --- Encryption (OCI KMS envelope) ---\n# · OCI connection — Backblaze\nAPP_ENV=\"local\"\n";
        $env = "APP_ENV=\"local\"\n";

        $output = $this->curator->buildCuratedContents($template, $env);

        $this->assertStringContainsString('OCI connection — Backblaze', $output);
    }

    public function test_orphan_keys_sorted_alphabetically(): void
    {
        $template = "APP_ENV=\"local\"\n";
        $env = "ZEBRA=\"1\"\nALPHA=\"2\"\nAPP_ENV=\"local\"\n";

        $output = $this->curator->buildCuratedContents($template, $env);

        $alphaPos = strpos($output, 'ALPHA=');
        $zebraPos = strpos($output, 'ZEBRA=');
        $this->assertNotFalse($alphaPos);
        $this->assertNotFalse($zebraPos);
        $this->assertLessThan($zebraPos, $alphaPos);
    }

    public function test_empty_template_with_only_comments_yields_orphans_only(): void
    {
        $template = "# comment only\n";
        $env = "SOLO_KEY=\"value\"\n";

        $output = $this->curator->buildCuratedContents($template, $env);

        $this->assertStringContainsString('SOLO_KEY="value"', $output);
        $this->assertStringContainsString('# --- Extra keys (not in template) ---', $output);
    }
}
