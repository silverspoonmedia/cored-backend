<?php

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * The git subtree path is retired.
 *
 * Core domains ship as Composer packages (`silverspoonmedia/platform`,
 * `…/navigation`, …), and `client-scaffold/` is the consumer template. The two
 * shell scripts that mirrored the monorepo into a client checkout — and the
 * subtree guide that documented them — must not come back: a reintroduced script
 * would silently re-create a second distribution path that no longer works with
 * the package registry. This test is the regression guard, not a style check.
 */
class CoreScriptTest extends TestCase
{
    private string $basePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->basePath = dirname(__DIR__, 3);
    }

    public function test_subtree_scripts_are_gone(): void
    {
        foreach (['update-core.sh', 'push-core.sh'] as $script) {
            $path = $this->basePath.DIRECTORY_SEPARATOR.$script;

            $this->assertFileDoesNotExist(
                $path,
                "{$script} belongs to the retired git subtree model; core domains are Composer packages now."
            );
        }
    }

    public function test_subtree_guide_is_gone(): void
    {
        $path = $this->basePath.DIRECTORY_SEPARATOR.'docs'.DIRECTORY_SEPARATOR.'integration'.DIRECTORY_SEPARATOR.'client-subtree.md';

        $this->assertFileDoesNotExist(
            $path,
            'The client guide is docs/integration/client-project.md; a subtree guide would document a distribution path the packages do not support.'
        );
    }
}
