<?php

namespace Tests\Feature\Console;

use App\Support\Domain\DomainManager;
use App\Support\Domain\UninstallManifest;
use App\Support\Domain\UninstallReferenceScanner;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The drift guard behind `uninstall.json`: every product domain must ship a
 * manifest whose targets still exist, whose ops still remove something, and
 * whose reference scan comes back empty.
 *
 * Without this, a manifest silently rots — a path is renamed, an op stops
 * matching, a new file references the domain — and the next `domain:remove`
 * leaves debris behind while reporting success.
 */
class DomainUninstallManifestTest extends TestCase
{
    /**
     * @return list<string>
     */
    private function productDomains(): array
    {
        $domains = app(DomainManager::class);

        return array_values(array_filter(
            array_keys($domains->all()),
            static fn (string $name): bool => app(DomainManager::class)->kind($name) === 'product'
        ));
    }

    public function test_every_product_domain_ships_a_valid_manifest(): void
    {
        $manifest = app(UninstallManifest::class);
        $domains = app(DomainManager::class);
        $product = $this->productDomains();

        $this->assertNotEmpty($product, 'Expected at least one product domain to guard.');

        foreach ($product as $name) {
            $this->assertTrue(
                $manifest->exists($name),
                "Product domain [{$name}] has no app/Domain/{$name}/uninstall.json."
            );

            // load() validates the schema, the op vocabulary and every reason.
            $loaded = $manifest->load($name);

            $this->assertSame(UninstallManifest::SCHEMA, $loaded['schema']);
            $this->assertSame($name, $loaded['domain']);
            $this->assertSame(
                $domains->normalizeName($name),
                $loaded['domain'],
                "Manifest domain key must match the registry name [{$name}]."
            );

            foreach (UninstallReferenceScanner::REPOS as $repo) {
                $unresolved = $loaded['repos'][$repo]['unresolved'] ?? [];

                $this->assertSame(
                    [],
                    $unresolved,
                    "Product domain [{$name}] still has unclassified {$repo} references: "
                    .implode(', ', array_column($unresolved, 'file'))
                );
            }
        }
    }

    public function test_every_manifest_target_exists_and_every_op_removes_something(): void
    {
        $this->assertManifestTargetsResolve();
    }

    /**
     * The sibling-free half, made deterministic.
     *
     * CI checks out this repo alone on purpose — the sibling is private and a
     * `GITHUB_TOKEN` scoped to this repo cannot fetch it (see the header of
     * `.github/workflows/ci.yml`). On a developer machine both checkouts exist, so
     * the sibling-free path would only ever run in CI; that is exactly how the
     * guard shipped asserting that `spa` and `docs` targets resolve. Binding a
     * scanner whose siblings are absent runs the real assertion logic under the CI
     * condition on any machine.
     */
    public function test_the_target_guard_degrades_loudly_when_a_sibling_is_absent(): void
    {
        $this->app->instance(UninstallReferenceScanner::class, new class extends UninstallReferenceScanner
        {
            public function root(string $repo): ?string
            {
                return $repo === self::REPO_BACKEND ? parent::root($repo) : null;
            }
        });

        $this->assertManifestTargetsResolve();
    }

    /**
     * A fork's sibling is not named after the original repo.
     *
     * `cored-backend` sits beside `cored-frontend`, so the default
     * `monolith-vue-workers` would leave its `spa` section permanently
     * unverifiable — `domain:remove` would report "checked" without ever reading
     * the SPA. The registry's `siblings` key names the directories, and this
     * proves the scanner honours it instead of the hardcoded default.
     */
    public function test_the_scanner_reads_the_configured_sibling_directories(): void
    {
        $parent = dirname(base_path());
        $suffix = 'craft-test-'.bin2hex(random_bytes(4));
        $spa = $parent.DIRECTORY_SEPARATOR.$suffix.'-frontend';
        $docs = $parent.DIRECTORY_SEPARATOR.$suffix.'-docs';

        File::makeDirectory($spa, 0777, true);
        File::makeDirectory($docs, 0777, true);

        try {
            config(['domains.siblings' => ['spa' => $suffix.'-frontend', 'docs' => $suffix.'-docs']]);

            $scanner = app(UninstallReferenceScanner::class);

            $this->assertSame($spa, $scanner->root(UninstallReferenceScanner::REPO_SPA), 'A configured spa sibling must be the directory the scanner reads.');
            $this->assertSame($docs, $scanner->root(UninstallReferenceScanner::REPO_DOCS), 'A configured docs sibling must be the directory the scanner reads.');
            $this->assertTrue($scanner->siblingPresent(UninstallReferenceScanner::REPO_SPA));

            // A configured name with no directory is still unverifiable, not clean.
            config(['domains.siblings.spa' => $suffix.'-absent']);
            $this->assertFalse(app(UninstallReferenceScanner::class)->siblingPresent(UninstallReferenceScanner::REPO_SPA), 'An absent configured sibling must not read as present.');
        } finally {
            File::deleteDirectory($spa);
            File::deleteDirectory($docs);
        }
    }

    /**
     * A non-domain registry key is not a domain.
     *
     * The `siblings` metadata shares `config/domains.php`; a key without a `kind`
     * must never surface as a phantom product or core domain in `domain:list`,
     * or every consumer's registry would report a package that cannot boot.
     */
    public function test_registry_metadata_is_not_a_domain(): void
    {
        $this->assertArrayNotHasKey(
            'siblings',
            app(DomainManager::class)->all(),
            'config/domains.php `siblings` is registry metadata; DomainManager::all() must skip it.'
        );
    }

    /**
     * A manifest target that stopped resolving is the failure this guard exists for,
     * so every finding is collected and asserted at once — an `assertNotSame` in the
     * loop reported only the first and hid the rest.
     *
     * The one thing that is *not* a finding is a section whose checkout is absent:
     * `plan()` marks it unverifiable and resolves nothing, and the plan's own warning
     * is asserted instead, so a sibling-free green run can never be mistaken for
     * cross-repo coverage — the convention `LocaleGuardTest` uses for the same reason.
     */
    private function assertManifestTargetsResolve(): void
    {
        $manifest = app(UninstallManifest::class);
        $scanner = app(UninstallReferenceScanner::class);

        foreach ($this->productDomains() as $name) {
            $plan = $manifest->plan($name);
            $report = [];

            foreach ($plan['deletes'] as $entry) {
                if (! $scanner->siblingPresent($entry['repo'])) {
                    continue;
                }

                if ($entry['matches'] === []) {
                    $report[] = "delete path matches nothing — {$entry['repo']}:{$entry['path']}";
                }
            }

            foreach ($plan['edits'] as $entry) {
                if (! $scanner->siblingPresent($entry['repo'])) {
                    continue;
                }

                if ($entry['matches'] === []) {
                    $report[] = "edit file matches nothing — {$entry['repo']}:{$entry['file']}";

                    continue;
                }

                if ($entry['removed'] < 1) {
                    $report[] = "op [{$entry['op']}] on {$entry['repo']}:{$entry['file']} removes nothing";
                }
            }

            foreach ($plan['data']['migrations'] ?? [] as $migration) {
                if (! File::isFile(base_path($migration))) {
                    $report[] = "migration missing — {$migration}";
                }
            }

            foreach (UninstallReferenceScanner::REPOS as $repo) {
                $root = $scanner->root($repo);

                if ($root === null) {
                    // Asserted rather than skipped silently: the absent checkout has
                    // to be visible in the run, or "0 findings" reads as "checked".
                    $this->assertContains(
                        "sibling checkout absent — section [{$repo}] skipped (unverifiable, not clean)",
                        $plan['warnings'],
                        "{$name}: the plan must report [{$repo}] as unverifiable while its checkout is absent."
                    );

                    continue;
                }

                foreach ($plan['manifest']['repos'][$repo]['ignore'] ?? [] as $entry) {
                    if ($manifest->resolve($root, (string) $entry['path']) === []) {
                        $report[] = "ignore path matches nothing — {$repo}:{$entry['path']}";
                    }
                }
            }

            $this->assertSame(
                [],
                $report,
                "Product domain [{$name}] has manifest targets that no longer resolve:\n  - "
                .implode("\n  - ", $report)
            );
        }
    }

    public function test_no_product_domain_has_an_unlisted_reference(): void
    {
        $manifest = app(UninstallManifest::class);
        $scanner = app(UninstallReferenceScanner::class);

        foreach ($this->productDomains() as $name) {
            $unlisted = $manifest->unlistedReferences($name);
            $report = [];

            foreach ($unlisted as $repo => $files) {
                if (! $scanner->siblingPresent($repo)) {
                    continue;
                }

                foreach ($files as $file => $lines) {
                    $report[] = $repo.':'.$file.' ('.count($lines).' hit(s))';
                }
            }

            $this->assertSame(
                [],
                $report,
                "Product domain [{$name}] has references no manifest entry covers:\n  - ".implode("\n  - ", $report)
            );
        }
    }

    public function test_dry_run_is_clean_and_changes_nothing(): void
    {
        $manifest = app(UninstallManifest::class);

        foreach ($this->productDomains() as $name) {
            $plan = $manifest->plan($name);
            $before = [];

            // Every file the manifest would edit is hashed, so a dry-run that
            // writes anything is caught.
            foreach ($plan['edits'] as $entry) {
                foreach ($entry['matches'] as $absolute) {
                    $before[$absolute] = md5_file($absolute);
                }
            }

            $this->artisan('domain:remove', ['name' => $name, '--dry-run' => true])
                ->assertSuccessful();

            foreach ($before as $absolute => $hash) {
                $this->assertSame($hash, md5_file($absolute), "Dry run modified {$absolute}.");
            }
        }
    }

    public function test_the_ai_skill_documents_every_command_flag(): void
    {
        $skill = base_path('.cursor/skills/domain-remove/SKILL.md');

        $this->assertFileExists($skill, 'The domain-remove skill is what an agent follows; it must exist.');

        $contents = File::get($skill);
        $signature = File::get(app_path('Console/Commands/Domain/RemoveDomainCommand.php'));

        preg_match_all('/\{(--[a-z-]+)/', $signature, $matches);

        $this->assertNotEmpty($matches[1], 'Expected domain:remove to declare flags.');

        foreach (array_unique($matches[1]) as $flag) {
            $this->assertStringContainsString(
                $flag,
                $contents,
                "domain:remove flag [{$flag}] is missing from the domain-remove skill."
            );
        }
    }
}
