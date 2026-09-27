<?php

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Coverage for the half of the docs guard that CI cannot run.
 *
 * The guard treats a link that leaves the repo as *unverifiable* when the sibling
 * checkout is absent, so a runner with one repo proves nothing about them. Wave F
 * measured 38 monolith -> `monolith-vue-workers` links and a handful into `gateway`
 * that were checked only on a machine with both checkouts.
 *
 * `scripts/docs-cross-repo-links.php` is the check for the present-sibling case, and
 * this test proves it fails closed — by driving `docs_cross_repo_report()` against a
 * throwaway fixture tree, so the assertion holds on the runner where the real sibling
 * does not exist.
 */
class DocsCrossRepoLinkTest extends TestCase
{
    private const SUPPORT = 'scripts/lib/docs-cross-repo-support.php';

    private string $root;

    /** @var list<string> */
    private array $fixtures = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = str_replace('\\', '/', dirname(__DIR__, 3));

        require_once $this->root.'/'.self::SUPPORT;
    }

    protected function tearDown(): void
    {
        foreach ($this->fixtures as $fixture) {
            $this->deleteTree($fixture);
        }

        $this->fixtures = [];

        parent::tearDown();
    }

    /**
     * The real corpus, on the machine that has both checkouts. Skipped in CI, where
     * the sibling is absent and every link would report as skipped.
     */
    public function test_the_real_corpus_resolves_when_the_sibling_is_present(): void
    {
        $report = docs_cross_repo_report($this->root);

        if ($report['verified'] === 0) {
            $this->markTestSkipped('No sibling checkout is present, so there is nothing to verify.');
        }

        $this->assertSame(
            [],
            $report['findings'],
            "Broken cross-repo links:\n".implode("\n", $report['findings']),
        );

        $this->assertGreaterThan(0, $report['verified']);
    }

    public function test_an_absent_sibling_is_skipped_not_broken(): void
    {
        $fixture = $this->fixture([
            'docs/README.md' => "# Index\n\n| Doc |\n|---|\n| [Remote](../../absent/docs/gone.md) |\n",
        ]);

        $report = docs_cross_repo_report($fixture);

        $this->assertSame([], $report['findings'], 'An absent sibling must not be reported as a break');
        $this->assertSame(0, $report['verified']);
        $this->assertSame(1, $report['skipped']);
    }

    public function test_a_present_sibling_with_a_missing_target_is_reported(): void
    {
        $fixture = $this->fixture(
            ['docs/README.md' => "# Index\n\n[Remote](../../monolith-vue-workers/docs/gone.md)\n"],
            ['docs/.keep' => ''],
        );

        $report = docs_cross_repo_report($fixture);

        $this->assertCount(1, $report['findings']);
        $this->assertStringContainsString('does not exist', $report['findings'][0]);
        $this->assertSame(1, $report['verified']);
        $this->assertSame(0, $report['skipped']);
    }

    public function test_a_present_sibling_with_a_dead_anchor_is_reported(): void
    {
        $fixture = $this->fixture(
            ['docs/README.md' => "# Index\n\n[Remote](../../monolith-vue-workers/docs/target.md#no-such-heading)\n"],
            ['docs/target.md' => "# Target\n\n## A real heading\n"],
        );

        $report = docs_cross_repo_report($fixture);

        $this->assertCount(1, $report['findings']);
        $this->assertStringContainsString('no heading in that file has that anchor', $report['findings'][0]);
    }

    public function test_a_present_sibling_with_a_live_anchor_verifies(): void
    {
        $fixture = $this->fixture(
            ['docs/README.md' => "# Index\n\n[Remote](../../monolith-vue-workers/docs/target.md#a-real-heading)\n"],
            ['docs/target.md' => "# Target\n\n## A real heading\n"],
        );

        $report = docs_cross_repo_report($fixture);

        $this->assertSame([], $report['findings']);
        $this->assertSame(1, $report['verified']);
        $this->assertSame(0, $report['skipped']);
    }

    /**
     * Rule files and the root README are link sources too — the same set the guard
     * walks. A fixture without them would let a regression drop them silently.
     */
    public function test_rules_and_the_root_readme_are_link_sources(): void
    {
        $fixture = $this->fixture(
            [
                'docs/README.md' => "# Index\n",
                'AGENTS.md' => "# Agents\n\n[Remote](../monolith-vue-workers/docs/a.md)\n",
                'README.md' => "# Root\n\n[Remote](../monolith-vue-workers/docs/b.md)\n",
                '.cursor/rules/x.mdc' => "# X\n\n[Remote](../../../monolith-vue-workers/docs/c.md)\n",
            ],
            [
                'docs/a.md' => "# A\n",
                'docs/b.md' => "# B\n",
                'docs/c.md' => "# C\n",
            ],
        );

        $report = docs_cross_repo_report($fixture);

        $this->assertSame([], $report['findings']);
        $this->assertSame(3, $report['verified'], 'AGENTS.md, README.md and .cursor/rules/*.mdc must all be checked');
    }

    /**
     * `docs/archive/**` is a frozen snapshot: its outbound links are deliberately not
     * rewritten, so they must not be pulled into this check either.
     */
    public function test_archive_is_not_a_link_source(): void
    {
        $fixture = $this->fixture(
            [
                'docs/README.md' => "# Index\n",
                'docs/archive/old.md' => "# Old\n\n[Remote](../../../monolith-vue-workers/docs/gone.md)\n",
            ],
            ['docs/.keep' => ''],
        );

        $report = docs_cross_repo_report($fixture);

        $this->assertSame([], $report['findings']);
        $this->assertSame(0, $report['verified']);
        $this->assertSame(0, $report['skipped']);
    }

    /**
     * Builds a two-repo layout, because that is the shape the check depends on: the
     * sibling must sit *beside* the repo root, not inside it. A fixture that nested it
     * would make `docs_path_within()` report the link as internal and the test would
     * pass while checking nothing.
     *
     * @param  array<string, string>  $monolithFiles  relative to the repo root
     * @param  array<string, string>  $siblingFiles  relative to the sibling root
     * @return string the fixture repo root
     */
    private function fixture(array $monolithFiles, array $siblingFiles = []): string
    {
        $base = sys_get_temp_dir().'/docs-cross-repo-'.bin2hex(random_bytes(6));

        foreach ([['monolith', $monolithFiles], ['monolith-vue-workers', $siblingFiles]] as [$repo, $files]) {
            foreach ($files as $relative => $contents) {
                $path = $base.'/'.$repo.'/'.$relative;
                $directory = dirname($path);

                if (! is_dir($directory)) {
                    mkdir($directory, 0777, true);
                }

                file_put_contents($path, $contents);
            }
        }

        $this->fixtures[] = $base;

        return str_replace('\\', '/', $base.'/monolith');
    }

    private function deleteTree(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }

        rmdir($path);
    }
}
