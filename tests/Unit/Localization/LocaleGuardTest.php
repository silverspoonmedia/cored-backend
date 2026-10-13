<?php

namespace Tests\Unit\Localization;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Gate for the permanent locale guards in `scripts/`.
 *
 * `LocaleParityTest` and `LocaleUsageTest` verify what a test can verify by reading
 * files: key sets, placeholders, duplicate literal keys, and literal call sites. The
 * classes they cannot see are the ones that shipped real defects:
 *
 *   - English left inside translated prose (`id` `management` in 26 keys, `id` `Test`
 *     in 10 SPA keys, `zh_CN` `Test` in 6) — invisible to parity, because the value
 *     differs from English, and invisible to a duplicate check, because it is not an
 *     exact copy;
 *   - a value that is itself a dotted key, rendered verbatim to the user;
 *   - a runtime-assembled key that resolves in no locale;
 *   - a settled glossary term that a later batch re-translated back to a rejected
 *     variant;
 *   - a `// curated at` flag whose key was deleted, left behind as a comment about
 *     nothing (132 of them after the 2026-10-07 product-domain sunset).
 *
 * Those live in `scripts/`, not in the test suite, because they are cross-repo and
 * because a curation batch wants to run them directly. This test shells out to them
 * and asserts their exit code, so `composer test` covers them without duplicating the
 * logic — one implementation, two entry points.
 *
 * The cross-repo guards need the sibling `monolith-vue-workers` checkout. When it is
 * absent the test does not silently pass: it asserts the guard *fails loudly*, then
 * marks the case skipped, so the absence shows up in the PHPUnit summary instead of
 * hiding behind a green run. That matters because CI runs without the sibling by
 * design — see `.github/workflows/ci.yml` and the coverage table in
 * `docs/platform/reference-localization.md`.
 */
class LocaleGuardTest extends TestCase
{
    private const GUARD_TIMEOUT = 180;

    /**
     * @return array<string, array{0: string, 1: bool}> script => [relative path, needs sibling repo]
     */
    public static function guardProvider(): array
    {
        return [
            'prose scan (English left in prose)' => ['scripts/locale-prose-scan.php', true],
            'raw key scan (value is a dotted key)' => ['scripts/locale-rawkey-scan.php', true],
            'dynamic key check (runtime-assembled keys)' => ['scripts/locale-dynamic-key-check.php', false],
            'pack parity (framework packs vs SPA)' => ['scripts/locale-pack-parity.php', true],
            'flat pack parity (pack vs its PHP owners)' => ['scripts/locale-flat-pack-parity.php', false],
            'curation flag scan (orphaned curated comments)' => ['scripts/locale-curation-flag-scan.php', false],
            'glossary scan (settled terms and banned variants)' => ['scripts/locale-glossary-scan.php', true],
        ];
    }

    #[DataProvider('guardProvider')]
    public function test_locale_guard_passes(string $script, bool $needsSibling): void
    {
        $path = base_path($script);

        $this->assertFileExists($path, "Locale guard is missing: {$script}");

        $process = $this->runGuard($path);

        if ($needsSibling && ! $this->siblingRepoIsPresent()) {
            // A cross-repo guard cannot see half its input here. It must say so and
            // exit 1 — reporting `0 divergent` would be the false green this suite
            // exists to prevent.
            $this->assertSame(
                1,
                $process->getExitCode(),
                $script.' must fail when the sibling repo is absent, not report success.'
            );
            $this->assertStringContainsString(
                'cannot see the SPA repo',
                $process->getErrorOutput().$process->getOutput(),
                $script.' must explain that it cannot see the sibling repo.'
            );

            // The assertions above prove the guard degrades loudly, but a green result
            // would still imply it ran. It did not: without the sibling there is
            // nothing to compare against, so the guard is skipped rather than passing.
            // CI runs without the sibling on purpose, which makes this the difference
            // between "4 guards passed" and "4 guards never executed".
            $this->markTestSkipped(
                'Sibling repo monolith-vue-workers is not checked out; '
                .$script.' was asserted to fail loudly instead of being executed.'
            );
        }

        $this->assertSame(
            0,
            $process->getExitCode(),
            $script.' reported a finding:'.PHP_EOL.$process->getOutput().$process->getErrorOutput()
        );
    }

    /**
     * The scan must not be vacuously green, and the shape it has to catch is the one
     * the first version of it deliberately ignored: a flag stacked on another flag.
     *
     * A stack is what a deleted key leaves behind. The flags sit above the next key
     * that survived, so the "is there a key line below me?" test — which skips blank
     * lines and stacked flags — judged the whole stack by its last member and passed.
     * Twenty-five flags outlived the 2026-10-07 product-domain sunset that way, in
     * seven files, while every gate stayed green.
     */
    public function test_curation_flag_scan_reports_a_stacked_flag(): void
    {
        $fixture = base_path('lang/en/_guard-flag-fixture.php');

        file_put_contents($fixture, implode(PHP_EOL, [
            '<?php',
            '',
            'return [',
            '    // curated at 2026-09-21T00:00:00+07:00 — fixture',
            '    // curated at 2026-09-22T00:00:00+07:00 — fixture',
            "    'stacked' => 'value',",
            '    // curated at 2026-09-23T00:00:00+07:00 — fixture',
            "    'owned' => 'value',",
            '];',
            '',
        ]));

        try {
            $process = $this->runGuard('scripts/locale-curation-flag-scan.php', 'en', '--verbose');

            $this->assertSame(
                1,
                $process->getExitCode(),
                'A curation flag stacked on another flag must fail the scan, not pass silently.'
            );
            $this->assertStringContainsString(
                'stacked flag',
                $process->getOutput(),
                'The scan must say why the flag is a finding.'
            );
            // Exactly one of the two flags above `stacked` is orphaned; the flag above
            // `owned` is the one that owns it. Precision matters as much as the exit code.
            $this->assertStringContainsString(
                'total orphaned curation flags: 1',
                $process->getOutput(),
                'The scan must report the surplus flag alone, not the whole stack.'
            );
        } finally {
            @unlink($fixture);
        }
    }

    public function test_sibling_repo_detection_matches_the_guard_helper(): void
    {
        // The helper the guards use is the single definition of "can I see the SPA?".
        // If it drifts from this test's check, one of the two is lying about the
        // environment and the assertions above stop meaning anything. The predicate is
        // deliberately the same one the guard uses — checkout present **and** reference
        // pack readable — because `is_dir()` alone reports a partial checkout as usable.
        $helper = base_path('scripts/lib/locale-scan-support.php');

        $this->assertFileExists($helper);

        $process = $this->runGuard('-r', 'require '.var_export($helper, true).'; echo locale_scan_spa_present() ? "yes" : "no";');

        $this->assertSame(
            $this->siblingRepoIsPresent() ? 'yes' : 'no',
            trim($process->getOutput()),
            'locale_scan_spa_present() disagrees with the test about whether the sibling repo is usable.'
        );
    }

    private function siblingRepoIsPresent(): bool
    {
        $root = dirname(base_path()).'/monolith-vue-workers';

        return is_dir($root) && is_file($root.'/src/i18n/locales/en.json');
    }

    /**
     * PHPUnit already declares `run()`, so the helper is named to avoid overriding it.
     */
    private function runGuard(string ...$arguments): Process
    {
        $process = new Process([PHP_BINARY, ...$arguments], base_path(), timeout: self::GUARD_TIMEOUT);
        $process->run();

        return $process;
    }
}
