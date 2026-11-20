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
        'Orchestra\\Testbench\\' => 'orchestra/testbench',
        'PragmaRX\\Google2FALaravel\\' => 'pragmarx/google2fa-laravel',
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
            'chillerlan/php-qrcode' => 'TwoFactorService asks `app(\'pragmarx.google2fa\')` for an inline QR image; google2fa-qrcode builds it through this adapter, chosen by `class_exists()` at runtime, never imported',
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
     * `composer.lock` and the package manifests must agree on the version.
     *
     * A path package resolves at whatever version its manifest declares, and the
     * lock records that resolution. Bumping only the manifest leaves the lock
     * pinning the old version: the mirror tag, the published release and the
     * installed copy then disagree, and `composer update` silently offers an
     * "upgrade" of a package that never changed in this checkout. This is the
     * guard that would have caught the bump commit that skipped the lock.
     */
    public function test_locked_versions_match_package_manifests(): void
    {
        $lock = json_decode((string) file_get_contents(base_path('composer.lock')), true);
        $this->assertIsArray($lock, 'composer.lock is not valid JSON.');

        $locked = [];

        foreach (['packages', 'packages-dev'] as $section) {
            foreach ((array) ($lock[$section] ?? []) as $entry) {
                $name = $entry['name'] ?? null;

                if (is_string($name) && str_starts_with($name, 'silverspoonmedia/')) {
                    $locked[$name] = (string) ($entry['version'] ?? '');
                }
            }
        }

        $violations = [];

        foreach ($this->packages() as $slug => $package) {
            $manifest = $this->manifest($slug);
            $version = (string) ($manifest['version'] ?? '');

            if (! isset($locked[$package])) {
                $violations[] = $package.' is declared in config/domains.php but does not appear in composer.lock';

                continue;
            }

            if ($locked[$package] !== $version) {
                $violations[] = $package.': composer.lock pins '.$locked[$package].' but packages/'.$slug.'/composer.json declares '.$version.' — run `composer update silverspoonmedia/* --no-scripts`';
            }
        }

        $this->assertSame([], $violations, implode("\n", $violations));
    }

    /**
     * The host must require a path package by a range, never `*`.
     *
     * `*` binds nothing: a local resolution and a registry resolution become
     * indistinguishable, `composer validate` warns on every one, and a consumer of
     * the host cannot tell a package pinned to this checkout from a released one.
     * `pdf-tools` is the documented exemption — it is a VCS dependency pinned to
     * a commit hash, not a path package.
     */
    public function test_root_requirement_is_a_caret_constraint(): void
    {
        $root = json_decode((string) file_get_contents(base_path('composer.json')), true);
        $this->assertIsArray($root, 'composer.json is not valid JSON.');

        $exempt = ['silverspoonmedia/pdf-tools'];
        $violations = [];

        foreach ($this->packages() as $package) {
            if (in_array($package, $exempt, true)) {
                continue;
            }

            $constraint = $root['require'][$package] ?? null;

            if (! is_string($constraint)) {
                $violations[] = 'composer.json does not require '.$package;

                continue;
            }

            if (! preg_match('/^\^1\.\d+$/', $constraint)) {
                $violations[] = $package.' is required as "'.$constraint.'" but must be a caret range ("^1.0")';
            }
        }

        $this->assertSame([], $violations, implode("\n", $violations));
    }

    /**
     * A sibling package must depend on the kernel at the same minor line it
     * publishes on.
     *
     * Every core package consumes the kernel's seams. If `notification` ships
     * `1.2.0` but requires `silverspoonmedia/platform: ^1.0`, then `1.0.0` of the
     * kernel satisfies it even though the seam it uses arrived in `1.2.0` — and
     * the failure appears in a consumer's install, not here.
     */
    public function test_every_package_declares_the_kernel_at_its_own_minor(): void
    {
        $violations = [];

        foreach ($this->packages() as $slug => $package) {
            if ($slug === 'platform') {
                continue;
            }

            $manifest = $this->manifest($slug);
            $version = (string) ($manifest['version'] ?? '');
            $constraint = $manifest['require']['silverspoonmedia/platform'] ?? null;

            if (! preg_match('/^(\d+)\.(\d+)/', $version, $own)) {
                $violations[] = $package.' declares an unparsable version "'.$version.'"';

                continue;
            }

            if (! is_string($constraint)) {
                $violations[] = $package.' does not require silverspoonmedia/platform';

                continue;
            }

            if (! preg_match('/^(\d+)\.(\d+)/', ltrim($constraint, '^~'), $kernel)) {
                $violations[] = $package.' requires silverspoonmedia/platform as "'.$constraint.'" which is not a parsable range';

                continue;
            }

            if ($own[1] !== $kernel[1] || $own[2] !== $kernel[2]) {
                $violations[] = $package.' is version '.$version.' but requires silverspoonmedia/platform '.$constraint.' — they must share the '.$own[1].'.'.$own[2].' minor';
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
        return array_keys((array) ($this->manifest(substr($package, strpos($package, '/') + 1))['require'] ?? []));
    }

    /**
     * Decoded manifest of a package under `packages/{slug}`.
     *
     * @return array<string, mixed>
     */
    private function manifest(string $slug): array
    {
        $path = base_path('packages/'.$slug.'/composer.json');
        $manifest = json_decode((string) file_get_contents($path), true);

        $this->assertIsArray($manifest, $path.' is not valid JSON.');

        return $manifest;
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
