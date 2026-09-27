<?php

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Gate for the `#anchor` half of `scripts/docs-guard.php`.
 *
 * The guard's link check used to resolve only the *path*, so a heading that was
 * reworded left every deep link into it pointing at nothing — the same silent
 * failure mode the path check exists to catch, one level down. Wave C/D moved
 * roughly sixty headings between files and every one of them had to be repaired by
 * hand, because nothing failed when one was missed.
 *
 * `docs-guard.php` now resolves the fragment too, using `scripts/lib/docs-anchor-support.php`
 * as the single slugger. `DocsStructureGuardTest` runs the guard; this test owns the
 * slugger's contract and the corpus-wide anchor sweep, so a regression names the
 * link instead of only reporting "guard exited 1".
 */
class DocsAnchorGuardTest extends TestCase
{
    private const SUPPORT = 'scripts/lib/docs-anchor-support.php';

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = str_replace('\\', '/', dirname(__DIR__, 3));

        require_once $this->root.'/'.self::SUPPORT;
    }

    /**
     * The GitHub slugger, pinned against real headings from this corpus — including
     * the em dash, which is the case a naive `\s+` collapse gets wrong. Removing the
     * em dash leaves the two spaces that flanked it, and GitHub renders both as
     * hyphens (`archivd--product-domain`), which is the anchor the docs already link
     * to. Collapsing runs would produce `archivd-product-domain` and silently break
     * every link written against the real anchor.
     *
     * @return list<array{0: string, 1: string}>
     */
    public static function headingProvider(): array
    {
        return [
            ['Concepts', 'concepts'],
            ['Archivd — product domain', 'archivd--product-domain'],
            ['API (`/api/v1`)', 'api-apiv1'],
            ['Tier B — Settings → Archivd', 'tier-b--settings--archivd'],
            ['`display_mode`', 'display_mode'],
            ['Doc kinds and size budgets (enforced by `scripts/docs-guard.php`)', 'doc-kinds-and-size-budgets-enforced-by-scriptsdocs-guardphp'],
            ['Action surface parity (single — bulk)', 'action-surface-parity-single--bulk'],
            ['Production URL minting (`SPA_URL`)', 'production-url-minting-spa_url'],
            ['6-8. No superglobal writes, no manual output buffering, no blocking `sleep` on request path', '6-8-no-superglobal-writes-no-manual-output-buffering-no-blocking-sleep-on-request-path'],
            ['[Foo](bar.md) label', 'foo-label'],
            ['Cache policy — forever + invalidate', 'cache-policy--forever--invalidate'],
            ['Tier A — env / artisan only (not in Web UI)', 'tier-a--env--artisan-only-not-in-web-ui'],
            ['vTual — product domain', 'vtual--product-domain'],
            ['Auth (`log_name: auth`)', 'auth-log_name-auth'],
            ['VA bank catalog (`VaBankCatalog`)', 'va-bank-catalog-vabankcatalog'],
        ];
    }

    #[DataProvider('headingProvider')]
    public function test_slugger_matches_github_for_a_heading(string $heading, string $expected): void
    {
        // Exercised through the pipeline, so heading-text extraction (link labels,
        // inline code) is part of what is under test.
        $slugs = docs_anchor_slugs_in('## '.$heading."\n");

        $this->assertSame([$expected], $slugs);
    }

    public function test_duplicate_headings_get_the_numeric_suffix(): void
    {
        $slugs = docs_anchor_slugs_in("# A\n\n## Same\n\n## Same\n\n## Same\n");

        $this->assertSame(['a', 'same', 'same-1', 'same-2'], $slugs);
    }

    public function test_fenced_code_does_not_contribute_headings(): void
    {
        // `# Full reset only when intentional:` is a shell comment inside a fence, not
        // a heading — treating it as one invents an anchor nothing can match.
        $slugs = docs_anchor_slugs_in("# Real\n\n```sh\n# Not a heading\n```\n\n## Also real\n");

        $this->assertSame(['real', 'also-real'], $slugs);
    }

    public function test_tilde_fences_are_recognised(): void
    {
        $this->assertSame(['a', 'b'], docs_anchor_slugs_in("# A\n\n~~~\n# nope\n~~~\n\n## B\n"));
    }

    public function test_fence_marker_must_match_to_close(): void
    {
        // A backtick fence is not closed by a tilde line, so the heading inside stays
        // fenced.
        $this->assertSame(['a'], docs_anchor_slugs_in("# A\n\n```\n# nope\n~~~\n# still nope\n```\n"));
    }

    /**
     * The corpus-wide sweep. This is the assertion that would have caught the two
     * stale SPA anchors Wave C/D left behind, and it is deliberately duplicated from
     * the guard: the guard proves the *gate* works, this proves the *docs* are clean,
     * and a failure here names the offending link.
     */
    public function test_every_anchor_in_living_docs_and_rules_resolves(): void
    {
        $failures = [];

        foreach ($this->markdownSources() as $source) {
            $contents = (string) file_get_contents($source);
            preg_match_all('/\[[^\]]*\]\(([^)\s]+)\)/', $contents, $links);

            foreach (array_unique($links[1] ?? []) as $link) {
                if (preg_match('~^(https?:|mailto:|tel:)~', $link) === 1) {
                    continue;
                }

                [$path, $anchor] = array_pad(explode('#', $link, 2), 2, null);

                if ($anchor === null) {
                    continue;
                }

                if ($path === '') {
                    if (! in_array(urldecode($anchor), docs_anchor_slugs($source), true)) {
                        $failures[] = $this->rel($source)." -> {$link} (same file)";
                    }

                    continue;
                }

                $resolved = dirname($source).'/'.urldecode($path);

                // A link that escapes this repo points at the sibling SPA checkout,
                // which a single-repo run cannot see. Normalise before comparing: the
                // raw path still *starts* with the repo root when it is written as
                // `docs/x/../../../monolith-vue-workers/...`, so a string prefix test
                // would treat an unverifiable link as a missing one.
                if (! docs_path_within($resolved, $this->root)
                    && ! is_dir(docs_path_sibling_root($resolved, $this->root))) {
                    continue;
                }

                if (! is_file($resolved)) {
                    $failures[] = $this->rel($source)." -> {$link} (target missing)";

                    continue;
                }

                if (preg_match('/\.mdc?$/i', $resolved) !== 1) {
                    continue;
                }

                if (! in_array(urldecode($anchor), docs_anchor_slugs($resolved), true)) {
                    $failures[] = $this->rel($source)." -> {$link} (no such heading)";
                }
            }
        }

        $this->assertSame([], $failures, "Broken anchors:\n".implode("\n", $failures));
    }

    /**
     * Living markdown only: `docs/archive/**` is a historical snapshot, exempt from
     * the guard's budgets and deliberately not rewritten.
     *
     * @return list<string> absolute paths
     */
    private function markdownSources(): array
    {
        $sources = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->root.'/docs', \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || strtolower($file->getExtension()) !== 'md') {
                continue;
            }

            $relative = $this->rel(str_replace('\\', '/', $file->getPathname()));

            if (str_starts_with($relative, 'docs/archive/')) {
                continue;
            }

            $sources[] = $file->getPathname();
        }

        foreach (glob($this->root.'/.cursor/rules/*.mdc') ?: [] as $rule) {
            $sources[] = $rule;
        }

        $sources[] = $this->root.'/AGENTS.md';

        sort($sources);

        return $sources;
    }

    private function rel(string $path): string
    {
        return str_replace('\\', '/', str_replace($this->root.'/', '', $path));
    }
}
