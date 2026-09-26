<?php

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Guards the client-project subtree how-to.
 *
 * The page is a human how-to (brief: purpose + pointer table + commands), so the
 * test asserts the shape and the load-bearing commands rather than prose. The two
 * scripts it documents are asserted separately by `CoreScriptTest`.
 */
class ClientSubtreeIntegrationDocTest extends TestCase
{
    private string $docPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->docPath = dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'docs'.DIRECTORY_SEPARATOR.'integration'.DIRECTORY_SEPARATOR.'client-subtree.md';
    }

    public function test_documentation_file_exists_in_the_integration_bucket(): void
    {
        $this->assertFileExists($this->docPath);
        $this->assertFileDoesNotExist(
            dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'docs'.DIRECTORY_SEPARATOR.'client-subtree-integration.md',
            'Subject files do not live at the docs/ root — see .cursor/rules/docs-pakem.mdc.'
        );
    }

    public function test_documentation_has_required_sections(): void
    {
        $content = file_get_contents($this->docPath);

        foreach ([
            '# Client project integration via git subtree',
            '## 1. Architecture overview',
            '## 2. Folder structure',
            '## 3. Integration strategy',
            '## 4. Setup (new client project)',
            '## 5. Daily workflow',
            '## 6. Quick reference',
        ] as $section) {
            $this->assertStringContainsString($section, $content);
        }
    }

    public function test_documentation_contains_the_subtree_workflow_commands(): void
    {
        $content = file_get_contents($this->docPath);

        foreach ([
            'https://github.com/silverspoonmedia/monolith.git',
            'core/',
            'git subtree add',
            'git subtree pull',
            'git subtree push',
            '--squash',
            '--prefix=core/',
        ] as $term) {
            $this->assertStringContainsString($term, $content);
        }
    }

    public function test_documentation_explains_laravel_integration_points(): void
    {
        $content = file_get_contents($this->docPath);

        foreach ([
            'composer.json',
            'bootstrap/app.php',
            'core/routes/',
            'core/config/',
            'php artisan migrate',
            'php artisan route:list',
            'php artisan db:seed',
        ] as $term) {
            $this->assertStringContainsString($term, $content);
        }
    }

    public function test_documentation_explains_the_psr4_overlay(): void
    {
        $content = file_get_contents($this->docPath);

        $this->assertStringContainsString('PSR-4 overlay', $content);
        $this->assertStringContainsString('"App\\\\": ["app/", "core/app/"]', $content);
        $this->assertMatchesRegularExpression('/resolves `app\/` before `core\/app\/`/', $content);
    }

    public function test_documentation_references_both_core_scripts(): void
    {
        $content = file_get_contents($this->docPath);

        foreach (['update-core.sh', 'push-core.sh'] as $script) {
            $this->assertStringContainsString($script, $content);
            $this->assertFileExists(dirname(__DIR__, 3).DIRECTORY_SEPARATOR.$script);
        }
    }

    public function test_documentation_explains_operational_decisions(): void
    {
        $content = file_get_contents($this->docPath);

        $this->assertMatchesRegularExpression('/read[-\s]?only.*upstream/i', $content);
        $this->assertMatchesRegularExpression('/config.*diff|diff.*config/i', $content);
        $this->assertMatchesRegularExpression('/override vs contribute/i', $content);
        $this->assertMatchesRegularExpression('/squash/i', $content);
    }

    public function test_documentation_stays_a_brief(): void
    {
        // Same definition of "line count" as scripts/docs-guard.php: a trailing
        // newline terminates the last line, it does not start a new one.
        $contents = rtrim((string) file_get_contents($this->docPath), "\r\n");
        $lines = substr_count($contents, "\n") + 1;

        $this->assertLessThanOrEqual(
            90,
            $lines,
            'The subtree how-to is a human brief; long design notes belong in docs/archive/.'
        );
    }
}
