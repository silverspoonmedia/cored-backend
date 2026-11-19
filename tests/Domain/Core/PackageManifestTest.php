<?php

namespace Tests\Domain\Core;

use Tests\TestCase;

/**
 * Keeps each package's `composer.json` honest about what its source imports.
 *
 * A path package resolves every dependency from the *host* vendor directory, so
 * a missing `require` entry is invisible here: the monolith has the package
 * installed twice over, and nothing fails. It fails downstream, at the moment a
 * consumer installs `silverspoonmedia/billing` without `midtrans/midtrans-php`
 * and the class autoload dies on the first webhook. Extraction is only useful if
 * the manifest describes the package, not the checkout it was carved out of.
 *
 * The map below is namespace prefix => Composer package, because Composer
 * package names are not derivable from namespaces (`Spatie\Activitylog` is
 * `spatie/laravel-activitylog`, `Symfony\Component\Yaml` is `symfony/yaml`).
 * A new third-party import needs a map entry in the same task — an unmapped
 * prefix fails loudly rather than silently passing, which is the point.
 *
 * Host-owned roots are exempt and documented: `App\*` (the kernel the package
 * ships beside), `Database\*` (host seeders the host's own suite seeds), and
 * PHP's global namespace (`\Throwable`, `\Closure`, …) which has no package.
 */
class PackageManifestTest extends TestCase
{
    /**
     * Namespace prefix => Composer package that provides it.
     *
     * `Illuminate\*` maps to `laravel/framework`, not the split `illuminate/*`
     * components: the framework `replace`s all 36 split packages, so a host
     * installs exactly one of the two shapes, and this host uses the framework.
     * A consumer that installs the split packages satisfies the same requirement
     * — Composer treats `replace` as a requirement of that name.
     *
     * @var array<string, string>
     */
    private const NAMESPACE_PACKAGES = [
        'Illuminate\\' => 'laravel/framework',
        'Symfony\\Component\\Yaml\\' => 'symfony/yaml',
        'Symfony\\Component\\Console\\' => 'symfony/console',
        'Symfony\\Component\\HttpKernel\\' => 'symfony/http-kernel',
        'Symfony\\Component\\HttpFoundation\\' => 'symfony/http-foundation',
        'Spatie\\Activitylog\\' => 'spatie/laravel-activitylog',
        'Spatie\\Translatable\\' => 'spatie/laravel-translatable',
        'Spatie\\Permission\\' => 'spatie/laravel-permission',
        'Midtrans\\' => 'midtrans/midtrans-php',
        'iPaymu\\' => 'ipaymu/ipaymu-php-api',
        'Carbon\\' => 'nesbot/carbon',
        'Aws\\' => 'aws/aws-sdk-php',
        'Fruitcake\\Cors\\' => 'fruitcake/php-cors',
        'PHPOpenSourceSaver\\JWTAuth\\' => 'php-open-source-saver/jwt-auth',
        'Symfony\\Component\\Mime\\' => 'symfony/mime',
    ];

    /**
     * Requirements a source file never `use`s but the package needs at runtime.
     *
     * Only for a dependency reached through a framework class that resolves it
     * late: the adapter, the driver or the facade, never a direct import. A new
     * silent requirement is a defect, so it is declared here with its reason.
     *
     * @var array<string, array<string, string>>
     */
    private const RUNTIME_ONLY_REQUIREMENTS = [
        'silverspoonmedia/platform' => [
            'league/flysystem-aws-s3-v3' => 'S3DirectUploadEngine drives an AWS S3 disk through Illuminate\\Filesystem\\AwsS3V3Adapter, whose driver class lives in this package',
        ],
    ];

    /**
     * Roots owned by the host application, not by any Composer package.
     *
     * @var list<string>
     */
    private const HOST_OWNED_ROOTS = ['App\\', 'Database\\', 'Tests\\'];

    public function test_every_third_party_import_is_declared_in_the_package_manifest(): void
    {
        $violations = [];

        foreach ($this->packages() as $slug => $package) {
            $required = $this->requirements($package);

            foreach ($this->imports($slug) as $prefix => $files) {
                $dependency = $this->dependencyFor($prefix);

                if ($dependency === null) {
                    $violations[] = $package.' imports '.$prefix.'* ('.implode(', ', array_slice($files, 0, 3)).') but the prefix has no entry in PackageManifestTest::NAMESPACE_PACKAGES';

                    continue;
                }

                if (! in_array($dependency, $required, true)) {
                    $violations[] = $package.' imports '.$prefix.'* ('.implode(', ', array_slice($files, 0, 3)).') but does not require '.$dependency;
                }
            }
        }

        $this->assertSame([], $violations, implode("\n", $violations));
    }

    public function test_every_declared_third_party_requirement_is_used(): void
    {
        $violations = [];

        foreach ($this->packages() as $slug => $package) {
            $used = [];

            foreach ($this->imports($slug) as $prefix => $files) {
                $dependency = $this->dependencyFor($prefix);

                if ($dependency !== null) {
                    $used[$dependency] = true;
                }
            }

            foreach ($this->requirements($package) as $dependency) {
                if ($this->isPlatformRequirement($dependency, $package)) {
                    continue;
                }

                if (! isset($used[$dependency])) {
                    $violations[] = $package.' requires '.$dependency.' but no source file imports it';
                }
            }
        }

        $this->assertSame([], $violations, implode("\n", $violations));
    }

    /**
     * Imports in `packages/{slug}/src`, grouped by namespace prefix.
     *
     * @return array<string, list<string>>
     */
    private function imports(string $slug): array
    {
        $imports = [];
        $root = base_path('packages/'.$slug.'/src');

        if (! is_dir($root)) {
            return [];
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $source = (string) file_get_contents($file->getPathname());
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen(base_path()) + 1));

            if (! preg_match_all('/^use ([A-Za-z0-9_\\\\]+);/m', $source, $matches)) {
                continue;
            }

            foreach ($matches[1] as $symbol) {
                if (str_contains($symbol, '\\') === false) {
                    continue;
                }

                if ($this->isHostOwned($symbol)) {
                    continue;
                }

                $prefix = $this->knownPrefix($symbol);

                if ($prefix === null) {
                    continue;
                }

                $imports[$prefix][] = $relative;
            }
        }

        foreach ($imports as $prefix => $files) {
            $imports[$prefix] = array_values(array_unique($files));
        }

        ksort($imports);

        return $imports;
    }

    /**
     * The longest mapped prefix that owns this symbol, or null when unmapped.
     */
    private function dependencyFor(string $prefix): ?string
    {
        return self::NAMESPACE_PACKAGES[$prefix] ?? null;
    }

    private function knownPrefix(string $symbol): ?string
    {
        foreach (self::NAMESPACE_PACKAGES as $prefix => $package) {
            if (str_starts_with($symbol, $prefix)) {
                return $prefix;
            }
        }

        // An import no map entry claims still has to be reported, so fall back
        // to the two-segment prefix (`Vendor\Package`) as the group key.
        $parts = explode('\\', $symbol);

        return count($parts) > 1 ? $parts[0].'\\'.$parts[1].'\\' : null;
    }

    private function isHostOwned(string $symbol): bool
    {
        foreach (self::HOST_OWNED_ROOTS as $root) {
            if (str_starts_with($symbol, $root)) {
                return true;
            }
        }

        return false;
    }

    /**
     * `require` keys of a package manifest.
     *
     * @return list<string>
     */
    private function requirements(string $package): array
    {
        $manifest = json_decode((string) file_get_contents(base_path('packages/'.substr($package, strpos($package, '/') + 1).'/composer.json')), true);

        $this->assertIsArray($manifest, $package.'/composer.json is not valid JSON.');

        return array_keys((array) ($manifest['require'] ?? []));
    }

    private function isPlatformRequirement(string $dependency, string $package): bool
    {
        return in_array($dependency, ['php', 'laravel/framework'], true)
            || str_starts_with($dependency, 'ext-')
            // A path-package sibling (`silverspoonmedia/platform`) is a runtime
            // seam, not a namespace this package imports: it is required by
            // DependencyDirectionTest instead.
            || str_starts_with($dependency, 'silverspoonmedia/')
            || isset(self::RUNTIME_ONLY_REQUIREMENTS[$package][$dependency]);
    }

    /**
     * @return array<string, string>
     */
    private function packages(): array
    {
        $packages = [];

        /** @var array<string, array<string, mixed>> $domains */
        $domains = config('domains', []);

        foreach ($domains as $entry) {
            $package = $entry['package'] ?? null;

            if (is_string($package)) {
                $packages[substr($package, strpos($package, '/') + 1)] = $package;
            }
        }

        return $packages;
    }
}
