<?php

namespace App\Support\Domain;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * Path helpers and source-file edits for domain:list / domain:make / domain:remove.
 *
 * Keeps config/domains.php, bootstrap/providers.php, and route loaders in sync.
 * Does not touch migrations (DB history stays intact on remove).
 *
 * Platform core is hardcoded — flipping core in config cannot unlock remove.
 * Product domains use locked => true|false; unlock only by editing config by hand.
 */
class DomainManager
{
    /**
     * Boilerplate domains that domain:remove always refuses.
     * Config cannot override this list.
     *
     * @var list<string>
     */
    public const PLATFORM_CORE = [
        'Identity',
        'Navigation',
        'Notification',
        'ActivityLog',
        'Approval',
        'PaymentGateway',
        'Service',
    ];

    /** @var list<string> */
    private const RESERVED = [
        'Kernel',
        'Support',
        'Http',
        'Providers',
        'Console',
        'Exceptions',
        'Repositories',
        'Rules',
        'View',
        'Domain',
    ];

    public function normalizeName(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            throw new InvalidArgumentException('Domain name cannot be empty.');
        }

        if (str_contains($name, '-') || str_contains($name, '_')) {
            return Str::studly($name);
        }

        if (preg_match('/^[A-Z][A-Za-z0-9]*$/', $name) === 1) {
            return $name;
        }

        if (preg_match('/^[a-z][a-z0-9]*$/', $name) === 1) {
            return Str::studly($name);
        }

        throw new InvalidArgumentException(
            "Invalid domain name [{$name}]. Use StudlyCase (e.g. Billing) or kebab-case (e.g. activity-log)."
        );
    }

    public function slug(string $name): string
    {
        return Str::kebab($this->normalizeName($name));
    }

    public function isReserved(string $name): bool
    {
        return in_array($this->normalizeName($name), self::RESERVED, true);
    }

    public function isRegistered(string $name): bool
    {
        $name = $this->normalizeName($name);

        return array_key_exists($name, $this->all());
    }

    /**
     * Hardcoded platform core — config cannot override.
     */
    public function isPlatformCore(string $name): bool
    {
        return in_array($this->normalizeName($name), self::PLATFORM_CORE, true);
    }

    /**
     * Config `core` flag (informational for list/docs). Prefer isPlatformCore() for remove guards.
     */
    public function isCore(string $name): bool
    {
        if ($this->isPlatformCore($name)) {
            return true;
        }

        $name = $this->normalizeName($name);
        $all = $this->all();

        if (! array_key_exists($name, $all)) {
            return false;
        }

        return (bool) ($all[$name]['core'] ?? false);
    }

    /**
     * Product lock from config. Platform core is always treated as locked for display/guards.
     */
    public function isLocked(string $name): bool
    {
        if ($this->isPlatformCore($name)) {
            return true;
        }

        $name = $this->normalizeName($name);
        $all = $this->all();

        if (! array_key_exists($name, $all)) {
            return false;
        }

        return (bool) ($all[$name]['locked'] ?? false);
    }

    /**
     * Kind label for domain:list — core | locked | removable.
     */
    public function kind(string $name): string
    {
        if ($this->isPlatformCore($name)) {
            return 'core';
        }

        if ($this->isLocked($name)) {
            return 'locked';
        }

        return 'removable';
    }

    public function directoryExists(string $name): bool
    {
        return File::isDirectory($this->domainPath($name));
    }

    /**
     * @return array<string, array{core: bool, locked: bool}>
     */
    public function all(): array
    {
        /** @var array<string, array{core?: bool, locked?: bool}> $raw */
        $raw = (array) config('domains', []);
        $out = [];

        foreach ($raw as $name => $meta) {
            if (! is_string($name) || $name === '') {
                continue;
            }

            $platform = in_array($name, self::PLATFORM_CORE, true);
            $out[$name] = [
                'core' => $platform || (bool) ($meta['core'] ?? false),
                'locked' => $platform || (bool) ($meta['locked'] ?? false),
            ];
        }

        return $out;
    }

    public function domainPath(string $name): string
    {
        return app_path('Domain/'.$this->normalizeName($name));
    }

    public function providerPath(string $name): string
    {
        return app_path('Providers/Domain/'.$this->normalizeName($name).'ServiceProvider.php');
    }

    public function providerShortClass(string $name): string
    {
        return $this->normalizeName($name).'ServiceProvider';
    }

    public function apiRoutePath(string $name): string
    {
        $name = $this->normalizeName($name);

        if ($name === 'Playground') {
            return base_path('routes/api/playground.php');
        }

        return base_path('routes/api/v1/'.$this->slug($name).'.php');
    }

    public function webRoutePath(string $name): string
    {
        return base_path('routes/web/'.$this->slug($name).'.php');
    }

    public function testsPath(string $name): string
    {
        return base_path('tests/Domain/'.$this->normalizeName($name));
    }

    public function scaffold(string $name, bool $locked = true): void
    {
        $name = $this->normalizeName($name);

        if ($this->isReserved($name)) {
            throw new InvalidArgumentException("[{$name}] is reserved for the application kernel.");
        }

        if ($this->isPlatformCore($name)) {
            throw new InvalidArgumentException("[{$name}] is platform core and cannot be scaffolded again.");
        }

        if ($this->isRegistered($name) || $this->directoryExists($name)) {
            throw new InvalidArgumentException("Domain [{$name}] already exists.");
        }

        $domainPath = $this->domainPath($name);
        File::ensureDirectoryExists($domainPath.'/Http/Controllers/API/V1');
        File::ensureDirectoryExists($domainPath.'/Http/Controllers/Web');
        File::ensureDirectoryExists($domainPath.'/Http/Requests');
        File::ensureDirectoryExists($domainPath.'/Http/Resources');
        File::ensureDirectoryExists($domainPath.'/Services');
        File::ensureDirectoryExists($domainPath.'/Repositories/Contracts');
        File::ensureDirectoryExists($domainPath.'/Repositories/Eloquent');
        File::ensureDirectoryExists($domainPath.'/Models');

        File::put($this->providerPath($name), $this->providerStub($name));
        File::put($this->apiRoutePath($name), $this->routeStub($name, 'API'));
        File::put($this->webRoutePath($name), $this->routeStub($name, 'Web'));

        $testsFeature = $this->testsPath($name).'/Feature';
        File::ensureDirectoryExists($testsFeature);
        File::put($testsFeature.'/.gitkeep', '');

        $this->registerInConfig($name, core: false, locked: $locked);
        $this->registerProvider($name);
        $this->registerApiV1Require($name);
        $this->registerWebRequire($name);
    }

    /**
     * @return list<string> Human-readable leftover warnings
     */
    public function remove(string $name): array
    {
        $name = $this->normalizeName($name);

        if (! $this->isRegistered($name) && ! $this->directoryExists($name)) {
            throw new InvalidArgumentException(
                "Domain [{$name}] is not registered and has no app/Domain/{$name} directory."
            );
        }

        if ($this->isPlatformCore($name)) {
            throw new InvalidArgumentException(
                "Domain [{$name}] is platform core and cannot be removed. Config cannot override this."
            );
        }

        if ($this->isLocked($name)) {
            throw new InvalidArgumentException(
                "Domain [{$name}] is locked. Set 'locked' => false in config/domains.php manually, then re-run domain:remove."
            );
        }

        $warnings = $this->leftoverWarnings($name);

        $this->unregisterFromConfig($name);
        $this->unregisterProvider($name);
        $this->unregisterApiRequires($name);
        $this->unregisterWebRequire($name);
        $this->stripBootstrapDiscoveries($name);

        if (File::isDirectory($this->domainPath($name))) {
            File::deleteDirectory($this->domainPath($name));
        }

        if (File::isDirectory($this->testsPath($name))) {
            File::deleteDirectory($this->testsPath($name));
        }

        if (File::isFile($this->providerPath($name))) {
            File::delete($this->providerPath($name));
        }

        foreach (array_unique([
            $this->apiRoutePath($name),
            base_path('routes/api/v1/'.$this->slug($name).'.php'),
            base_path('routes/api/'.$this->slug($name).'.php'),
        ]) as $routeFile) {
            if (File::isFile($routeFile)) {
                File::delete($routeFile);
            }
        }

        if (File::isFile($this->webRoutePath($name))) {
            File::delete($this->webRoutePath($name));
        }

        return $warnings;
    }

    public function registerInConfig(string $name, bool $core = false, bool $locked = true): void
    {
        $name = $this->normalizeName($name);
        $path = config_path('domains.php');
        $contents = File::get($path);

        if (preg_match("/['\"]".preg_quote($name, '/')."['\"]\\s*=>/", $contents) === 1) {
            return;
        }

        $coreLiteral = $core ? 'true' : 'false';
        $lockedLiteral = $locked ? 'true' : 'false';
        $entry = "    '{$name}' => ['core' => {$coreLiteral}, 'locked' => {$lockedLiteral}],\n];";

        $updated = preg_replace('/\];\s*$/', $entry, $contents, 1);
        if ($updated === null || $updated === $contents) {
            throw new RuntimeException('Could not update config/domains.php — closing ]; not found.');
        }

        File::put($path, $updated);
        config(["domains.{$name}" => ['core' => $core, 'locked' => $locked]]);
    }

    public function unregisterFromConfig(string $name): void
    {
        $name = $this->normalizeName($name);
        $path = config_path('domains.php');
        $contents = File::get($path);

        $updated = preg_replace(
            "/\n\s*['\"]".preg_quote($name, '/')."['\"]\\s*=>\\s*\\[[^\\]]*\\],?/",
            '',
            $contents,
            1
        );

        if ($updated === null) {
            throw new RuntimeException("Could not remove [{$name}] from config/domains.php.");
        }

        File::put($path, $updated);

        $all = config('domains', []);
        if (is_array($all)) {
            unset($all[$name]);
            config(['domains' => $all]);
        }
    }

    public function registerProvider(string $name): void
    {
        $name = $this->normalizeName($name);
        $short = $this->providerShortClass($name);
        $path = base_path('bootstrap/providers.php');
        $contents = File::get($path);

        if (str_contains($contents, $short.'::class')) {
            return;
        }

        if (! str_contains($contents, "use App\\Providers\\Domain\\{$short};")) {
            if (preg_match('/use App\\\\Providers\\\\Domain\\\\[A-Za-z0-9_]+;/m', $contents) === 1) {
                $contents = preg_replace(
                    '/(use App\\\\Providers\\\\Domain\\\\[A-Za-z0-9_]+;\n)(?!use App\\\\Providers\\\\Domain\\\\)/',
                    "$1use App\\Providers\\Domain\\{$short};\n",
                    $contents,
                    1
                ) ?? $contents;
            } else {
                $contents = preg_replace(
                    '/(use App\\\\Providers\\\\AppServiceProvider;\n)/',
                    "$1use App\\Providers\\Domain\\{$short};\n",
                    $contents,
                    1
                ) ?? $contents;
            }
        }

        $classLine = "    {$short}::class,\n];";
        $updated = preg_replace('/\];\s*$/', $classLine, $contents, 1);
        if ($updated === null || $updated === $contents) {
            throw new RuntimeException('Could not register provider in bootstrap/providers.php.');
        }

        File::put($path, $updated);
    }

    public function unregisterProvider(string $name): void
    {
        $name = $this->normalizeName($name);
        $short = $this->providerShortClass($name);
        $path = base_path('bootstrap/providers.php');
        $contents = File::get($path);

        $contents = str_replace("\nuse App\\Providers\\Domain\\{$short};", '', $contents);
        $contents = str_replace("\n    {$short}::class,", '', $contents);

        File::put($path, $contents);
    }

    public function registerApiV1Require(string $name): void
    {
        $slug = $this->slug($name);
        $path = base_path('routes/api/v1.php');
        $line = "require __DIR__.'/v1/{$slug}.php';";
        $contents = File::get($path);

        if (str_contains($contents, $line)) {
            return;
        }

        File::put($path, rtrim($contents)."\n{$line}\n");
    }

    public function registerWebRequire(string $name): void
    {
        $slug = $this->slug($name);
        $path = base_path('routes/web.php');
        $line = "    require __DIR__.'/web/{$slug}.php';";
        $contents = File::get($path);

        if (str_contains($contents, "require __DIR__.'/web/{$slug}.php';")) {
            return;
        }

        $updated = preg_replace(
            "/(Route::middleware\\('web\\.auth'\\)->group\\(function \\(\\): void \\{[\\s\\S]*?)(\\n\\}\\);)/",
            "$1\n{$line}$2",
            $contents,
            1
        );

        if ($updated === null || $updated === $contents) {
            throw new RuntimeException('Could not register web route require in routes/web.php.');
        }

        File::put($path, $updated);
    }

    public function unregisterApiRequires(string $name): void
    {
        $name = $this->normalizeName($name);
        $slug = $this->slug($name);

        $v1 = base_path('routes/api/v1.php');
        if (File::isFile($v1)) {
            $this->removeLinesContaining($v1, "require __DIR__.'/v1/{$slug}.php';");
        }

        if ($name === 'Playground') {
            $api = base_path('routes/api.php');
            $this->removeLinesContaining($api, "require __DIR__.'/api/playground.php';");
            $this->removeLinesContaining($api, 'use App\\Domain\\Playground\\Http\\Controllers\\API\\PlaygroundController;');
            $this->removeLinesContaining($api, "Route::get('ping', [PlaygroundController::class, 'ping']);");
        }
    }

    public function unregisterWebRequire(string $name): void
    {
        $slug = $this->slug($name);
        $this->removeLinesContaining(
            base_path('routes/web.php'),
            "require __DIR__.'/web/{$slug}.php';"
        );
    }

    public function stripBootstrapDiscoveries(string $name): void
    {
        $name = $this->normalizeName($name);
        $path = base_path('bootstrap/app.php');
        $contents = File::get($path);

        $needles = [
            "__DIR__.'/../app/Domain/{$name}/Listeners'",
            "__DIR__.'/../app/Domain/{$name}/Console/Commands'",
        ];

        $lines = preg_split("/\r\n|\n|\r/", $contents) ?: [];
        $filtered = [];

        foreach ($lines as $line) {
            $trim = trim($line);
            $drop = false;
            foreach ($needles as $needle) {
                if (str_contains($trim, $needle)) {
                    $drop = true;
                    break;
                }
            }
            if (! $drop) {
                $filtered[] = $line;
            }
        }

        File::put($path, implode("\n", $filtered));
    }

    /**
     * @return list<string>
     */
    public function leftoverWarnings(string $name): array
    {
        $name = $this->normalizeName($name);
        $warnings = [];

        foreach ([database_path('factories'), database_path('seeders')] as $dir) {
            if (! File::isDirectory($dir)) {
                continue;
            }
            foreach (File::allFiles($dir) as $file) {
                $text = File::get($file->getPathname());
                if (str_contains($text, "App\\Domain\\{$name}\\") || str_contains($text, "Domain\\{$name}\\")) {
                    $warnings[] = 'Possible leftover reference in '.$file->getRelativePathname();
                }
            }
        }

        $warnings[] = 'Migrations were not deleted — review database/migrations for '.$name.' tables if you no longer need them.';
        $warnings[] = 'If menus or permissions referenced this domain, clean them up manually.';

        return $warnings;
    }

    private function removeLinesContaining(string $path, string $needle): void
    {
        $contents = File::get($path);
        $lines = preg_split("/\r\n|\n|\r/", $contents) ?: [];
        $filtered = array_values(array_filter(
            $lines,
            static fn (string $line): bool => ! str_contains($line, $needle)
        ));

        File::put($path, implode("\n", $filtered).(str_ends_with($contents, "\n") ? "\n" : ''));
    }

    private function providerStub(string $name): string
    {
        $short = $this->providerShortClass($name);

        return <<<PHP
<?php

namespace App\\Providers\\Domain;

use Illuminate\\Support\\ServiceProvider;

class {$short} extends ServiceProvider
{
    public function register(): void
    {
        //
    }
}

PHP;
    }

    private function routeStub(string $name, string $surface): string
    {
        return <<<PHP
<?php

use Illuminate\\Support\\Facades\\Route;

/*
|--------------------------------------------------------------------------
| {$name} {$surface} routes
|--------------------------------------------------------------------------
*/

PHP;
    }
}
