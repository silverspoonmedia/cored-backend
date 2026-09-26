<?php

namespace Tests\Unit\Documentation;

use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Gate for the brief/pakem split in `scripts/docs-guard.php`.
 *
 * Documentation has two surfaces and each topic has exactly one owner:
 *
 *   docs/**            human brief — one paragraph plus a pointer table
 *   .cursor/rules/*.mdc agent pakem — imperative, glob-scoped, self-sufficient
 *   AGENTS.md          always-on map: scope tag plus one line per rule file
 *
 * The failure modes are silent by nature: a brief nobody indexed, a brief that grew
 * back into an essay, a rule whose `globs:` matches nothing (so it never fires), a
 * rule that `AGENTS.md` forgot, a relative link to a file that moved, and
 * `docs/agents/` creeping back as a second home for agent rules. `DocReferenceIntegrityTest`
 * reads the docs; this test runs the guard that owns the structure.
 *
 * The guard spans both repos, so it needs the sibling `monolith-vue-workers` checkout.
 * When it is absent the test does not silently pass: it asserts the guard *fails
 * loudly*, then skips, so the absence shows up in the PHPUnit summary instead of
 * hiding behind a green run — the convention `LocaleGuardTest` established.
 */
class DocsStructureGuardTest extends TestCase
{
    private const GUARD = 'scripts/docs-guard.php';

    private const GUARD_TIMEOUT = 180;

    public function test_docs_guard_passes(): void
    {
        $path = base_path(self::GUARD);

        $this->assertFileExists($path, 'Docs guard is missing: '.self::GUARD);

        $process = new Process([PHP_BINARY, $path], base_path(), timeout: self::GUARD_TIMEOUT);
        $process->run();

        if (! $this->siblingRepoIsPresent()) {
            // Half the split lives in the sibling repo. A guard that cannot see it must
            // say so and exit 1 — reporting "OK (1 repo)" would be the false green this
            // suite exists to prevent.
            $this->assertSame(
                1,
                $process->getExitCode(),
                self::GUARD.' must fail when the sibling repo is absent, not report success.'
            );
            $this->assertStringContainsString(
                'cannot see the SPA repo',
                $process->getErrorOutput().$process->getOutput(),
                self::GUARD.' must explain that it cannot see the sibling repo.'
            );

            $this->markTestSkipped(
                'Sibling repo monolith-vue-workers is not checked out; '
                .self::GUARD.' was asserted to fail loudly instead of being executed.'
            );
        }

        $this->assertSame(
            0,
            $process->getExitCode(),
            self::GUARD.' reported a finding:'.PHP_EOL.$process->getOutput().$process->getErrorOutput()
        );
    }

    public function test_backend_only_run_stays_green_without_the_sibling(): void
    {
        // `--repo=backend` is the documented escape hatch for a single-repo checkout,
        // so it must work with or without the sibling present.
        $process = new Process(
            [PHP_BINARY, base_path(self::GUARD), '--repo=backend'],
            base_path(),
            timeout: self::GUARD_TIMEOUT
        );
        $process->run();

        $this->assertSame(
            0,
            $process->getExitCode(),
            self::GUARD.' --repo=backend reported a finding:'.PHP_EOL.$process->getOutput().$process->getErrorOutput()
        );
    }

    public function test_unknown_repo_argument_fails_loudly(): void
    {
        $process = new Process(
            [PHP_BINARY, base_path(self::GUARD), '--repo=nope'],
            base_path(),
            timeout: self::GUARD_TIMEOUT
        );
        $process->run();

        $this->assertSame(1, $process->getExitCode(), 'An unknown --repo value must exit 1, not fall back to all.');
    }

    private function siblingRepoIsPresent(): bool
    {
        $root = dirname(base_path()).'/monolith-vue-workers';

        return is_dir($root) && is_dir($root.'/.cursor/rules');
    }
}
