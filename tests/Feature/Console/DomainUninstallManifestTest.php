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
        $manifest = app(UninstallManifest::class);

        foreach ($this->productDomains() as $name) {
            $plan = $manifest->plan($name);

            foreach ($plan['deletes'] as $entry) {
                $this->assertNotSame(
                    [],
                    $entry['matches'],
                    "{$name}: delete path matches nothing — {$entry['repo']}:{$entry['path']}"
                );
            }

            foreach ($plan['edits'] as $entry) {
                $this->assertNotSame(
                    [],
                    $entry['matches'],
                    "{$name}: edit file matches nothing — {$entry['repo']}:{$entry['file']}"
                );

                $this->assertGreaterThan(
                    0,
                    $entry['removed'],
                    "{$name}: op [{$entry['op']}] on {$entry['repo']}:{$entry['file']} removes nothing."
                );
            }

            foreach ($plan['data']['migrations'] ?? [] as $migration) {
                $this->assertFileExists(base_path($migration), "{$name}: migration missing — {$migration}");
            }

            $loaded = $plan['manifest'];

            foreach (UninstallReferenceScanner::REPOS as $repo) {
                $root = app(UninstallReferenceScanner::class)->root($repo);

                if ($root === null) {
                    continue;
                }

                foreach ($loaded['repos'][$repo]['ignore'] ?? [] as $entry) {
                    $this->assertNotSame(
                        [],
                        $manifest->resolve($root, (string) $entry['path']),
                        "{$name}: ignore path matches nothing — {$repo}:{$entry['path']}"
                    );
                }
            }
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
