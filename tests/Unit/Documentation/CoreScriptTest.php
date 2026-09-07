<?php

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

class CoreScriptTest extends TestCase
{
    private string $basePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->basePath = dirname(__DIR__, 3);
    }

    public function test_update_core_script_exists_with_required_workflow(): void
    {
        $path = $this->basePath.DIRECTORY_SEPARATOR.'update-core.sh';

        $this->assertFileExists($path);

        $content = file_get_contents($path);

        foreach ([
            '#!/usr/bin/env bash',
            'set -euo pipefail',
            'MONOLITH_URL="${MONOLITH_URL:-https://github.com/silverspoonmedia/monolith.git}"',
            'git remote add',
            'git fetch',
            'git subtree pull',
            '--squash',
            'diff -rq config/',
            'php artisan migrate',
        ] as $term) {
            $this->assertStringContainsString($term, $content);
        }
    }

    public function test_push_core_script_exists_with_required_workflow(): void
    {
        $path = $this->basePath.DIRECTORY_SEPARATOR.'push-core.sh';

        $this->assertFileExists($path);

        $content = file_get_contents($path);

        foreach ([
            '#!/usr/bin/env bash',
            'set -euo pipefail',
            'MONOLITH_URL="${MONOLITH_URL:-https://github.com/silverspoonmedia/monolith.git}"',
            'git diff-index --quiet HEAD --',
            'git subtree push',
            'read -r -p "Continue? [y/N] " CONFIRM',
            'WARNING',
        ] as $term) {
            $this->assertStringContainsString($term, $content);
        }
    }

    public function test_scripts_do_not_contain_emoji_status_prefixes(): void
    {
        foreach (['update-core.sh', 'push-core.sh'] as $file) {
            $content = file_get_contents($this->basePath.DIRECTORY_SEPARATOR.$file);

            $this->assertDoesNotMatchRegularExpression(
                '/[🔍📡✅📥🔎📊📋🚀📝⚙️⚠️📦🔄🏁❌📁]/u',
                $content,
                "{$file} must avoid emoji status prefixes."
            );
        }
    }
}
