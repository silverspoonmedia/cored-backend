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
 *     variant.
 *
 * Those live in `scripts/`, not in the test suite, because they are cross-repo and
 * because a curation batch wants to run them directly. This test shells out to them
 * and asserts their exit code, so `composer test` covers them without duplicating the
 * logic — one implementation, two entry points.
 *
 * The cross-repo guards need the sibling `monolith-vue-workers` checkout. When it is
 * absent the test does not silently pass: it asserts the guard *fails loudly*. That is
 * the "green by absence" bug this suite was written to close, and asserting it here is
 * what keeps it closed.
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

            return;
        }

        $this->assertSame(
            0,
            $process->getExitCode(),
            $script.' reported a finding:'.PHP_EOL.$process->getOutput().$process->getErrorOutput()
        );
    }

    public function test_sibling_repo_detection_matches_the_guard_helper(): void
    {
        // The helper the guards use is the single definition of "can I see the SPA?".
        // If it drifts from this test's check, one of the two is lying about the
        // environment and the assertions above stop meaning anything.
        $helper = base_path('scripts/lib/locale-scan-support.php');

        $this->assertFileExists($helper);

        $process = $this->runGuard('-r', 'require '.var_export($helper, true).'; echo is_dir(locale_scan_spa_root()) ? "yes" : "no";');

        $this->assertSame(
            $this->siblingRepoIsPresent() ? 'yes' : 'no',
            trim($process->getOutput()),
            'locale_scan_spa_root() disagrees with the test about whether the sibling repo exists.'
        );
    }

    private function siblingRepoIsPresent(): bool
    {
        return is_file(dirname(base_path()).'/monolith-vue-workers/src/i18n/locales/en.json');
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
