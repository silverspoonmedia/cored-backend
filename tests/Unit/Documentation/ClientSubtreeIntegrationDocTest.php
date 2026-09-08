<?php

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

class ClientSubtreeIntegrationDocTest extends TestCase
{
    private string $docPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->docPath = dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'docs'.DIRECTORY_SEPARATOR.'client-subtree-integration.md';
    }

    public function test_documentation_file_exists(): void
    {
        $this->assertFileExists($this->docPath);
    }

    public function test_documentation_has_required_sections(): void
    {
        $content = file_get_contents($this->docPath);

        foreach ([
            '# Client Project Integration via Git Subtree',
            '## 1. Architecture Overview',
            '## 2. Client Project Folder Structure',
            '## 3. Integration Strategy',
            '## 4. Setup Commands',
            '## 5. Daily Workflow Commands',
            '## 6. Script: update-core.sh',
            '## 7. Script: push-core.sh',
            '## 8. Quick Reference Card',
        ] as $section) {
            $this->assertStringContainsString($section, $content);
        }
    }

    public function test_documentation_contains_required_subtree_workflow_terms(): void
    {
        $content = file_get_contents($this->docPath);

        foreach ([
            'https://github.com/silverspoonmedia/monolith.git',
            'core/',
            'Git Subtree',
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
            'artisan',
            'public/index.php',
            'Application::configure',
            'withRouting',
            'core/routes/',
            'core/config/',
            'php artisan migrate',
            'php artisan test',
        ] as $term) {
            $this->assertStringContainsString($term, $content);
        }
    }

    public function test_documentation_explains_psr4_overlay_strategy(): void
    {
        $content = file_get_contents($this->docPath);

        $this->assertStringContainsString('PSR-4 Overlay', $content);
        $this->assertStringContainsString('"App\\\\": ["app/", "core/app/"]', $content);
        $this->assertMatchesRegularExpression('/Composer.*searches paths in order/i', $content);
    }

    public function test_documentation_includes_client_project_tree(): void
    {
        $content = file_get_contents($this->docPath);

        $this->assertStringContainsString('client-project/', $content);
        $this->assertStringContainsString('├── core/', $content);
        $this->assertStringContainsString('├── app/', $content);
        $this->assertStringContainsString('└── README.md', $content);
    }

    public function test_documentation_includes_update_core_script_requirements(): void
    {
        $content = file_get_contents($this->docPath);

        foreach ([
            'update-core.sh',
            'set -euo pipefail',
            'MONOLITH_URL',
            'git remote add',
            'git fetch',
            'git subtree pull',
            '--squash',
            'Checking for config differences',
        ] as $term) {
            $this->assertStringContainsString($term, $content);
        }
    }

    public function test_documentation_includes_push_core_script_requirements(): void
    {
        $content = file_get_contents($this->docPath);

        foreach ([
            'push-core.sh',
            'git diff-index',
            'git subtree push',
            'Continue? [y/N]',
            'WARNING',
            'Push complete',
        ] as $term) {
            $this->assertStringContainsString($term, $content);
        }
    }

    public function test_documentation_explains_operational_decisions(): void
    {
        $content = file_get_contents($this->docPath);

        $this->assertMatchesRegularExpression('/read[-\s]?only.*upstream|read[-\s]?only.*core/i', $content);
        $this->assertMatchesRegularExpression('/config.*diff|diff.*config/i', $content);
        $this->assertMatchesRegularExpression('/override.*contribute|contribute.*upstream/i', $content);
        $this->assertMatchesRegularExpression('/squash.*history|history.*squash/i', $content);
    }

    public function test_documentation_is_english_and_laravel_13_specific(): void
    {
        $content = file_get_contents($this->docPath);

        foreach ([
            'Laravel 13',
            'Architecture Overview',
            'Integration Strategy',
            'Setup Commands',
            'Daily Workflow',
        ] as $term) {
            $this->assertStringContainsString($term, $content);
        }
    }
}
