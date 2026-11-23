<?php

/**
 * Prove a consumer install resolves the **published tags**, not this checkout.
 *
 * `run-consumer-scaffold-check.php` rewrites the scaffold's repositories to
 * `path`, which needs no org token and can therefore never notice a scaffold
 * whose caret floor lags the released tag — the path package answers whatever
 * this checkout pins. This is the missing half: the scaffold keeps its six
 * `{"type": "vcs", ...}` mirror entries and installs from GitHub.
 *
 * Steps, all inside `{tmp}/silverspoon-vcs-consumer/`:
 *
 *  1. copy `client-scaffold` (no `vendor`, no `node_modules`);
 *  2. `composer update silverspoonmedia/{platform,navigation,notification,audit,billing,ai}`
 *     with a GitHub token from `GITHUB_TOKEN`, then `gh auth token` when unset —
 *     the mirrors are private;
 *  3. assert `composer.lock` pins a `v1.1.x` release for all six and every
 *     reference is a `github.com/silverspoonmedia/*` commit;
 *  4. `domain:list` reports the ten core domains and no product domain,
 *     `route:list --json` mounts the pinned core URIs;
 *  5. boot one real request: an empty `POST /api/v1/auth/login` answers 422
 *     with a translated sentence, not a raw key.
 *
 * Network-bound, so it is a release gate, not part of the in-memory suite:
 *
 * ```
 * php -d memory_limit=1G scripts/run-vcs-consumer-check.php
 * php -d memory_limit=1G scripts/run-vcs-consumer-check.php --keep
 * ```
 *
 * `--keep` leaves the temp tree in place for inspection. Exit 0 only when every
 * step holds.
 */
$root = dirname(__DIR__);

if (! is_file($root.'/vendor/autoload.php')) {
    fwrite(STDERR, "Run `composer install` in the monolith first.\n");
    exit(1);
}

/** The core packages a consumer installs by tag. */
const VCS_CONSUMER_PACKAGES = [
    'silverspoonmedia/platform',
    'silverspoonmedia/navigation',
    'silverspoonmedia/notification',
    'silverspoonmedia/audit',
    'silverspoonmedia/billing',
    'silverspoonmedia/ai',
];

/** The release line this check holds the mirrors to. */
const VCS_CONSUMER_RELEASE = 'v1.1';

/** @var list<string> */
const VCS_CONSUMER_URIS = [
    'api/v1/auth/login',
    'api/v1/users',
    'api/v1/navigation/menus',
    'api/v1/activity-logs',
    'api/v1/error-logs',
    'api/v1/currencies',
    'api/v1/services',
    'api/v1/invoices',
    'api/v1/inference/providers',
    'api/v1/ai/connections',
    'api/v1/admin/settings/groups',
    'api/v1/admin/domain-access',
    'api/v1/product-activations',
    'api/v1/batch',
];

$keep = in_array('--keep', $argv, true);
$work = sys_get_temp_dir().DIRECTORY_SEPARATOR.'silverspoon-vcs-consumer';

vcs_consumer_remove($work);
vcs_consumer_copy($root.'/client-scaffold', $work);

/** @var list<array{string, bool, string}> $steps */
$steps = [];

try {
    $steps[] = ['copy', true, $work];

    $token = vcs_consumer_token();

    if ($token === null) {
        $steps[] = ['github token', false, 'Set GITHUB_TOKEN or authenticate `gh` — the mirrors are private.'];
    } else {
        $steps[] = ['github token', true, ''];

        $composer = vcs_consumer_composer_arguments();
        $auth = (string) getenv('COMPOSER_AUTH');

        if ($auth === '') {
            $auth = (string) json_encode(['github-oauth' => ['github.com' => $token]]);
        }

        $steps[] = ['composer update', ...vcs_consumer_update($work, $composer, $auth)];

        $steps[] = ['composer.lock', ...vcs_consumer_lock($work)];

        $steps[] = ['domain:list', ...vcs_consumer_domains($work)];

        $steps[] = ['route:list', ...vcs_consumer_routes($work)];

        $steps[] = ['validation envelope', ...vcs_consumer_validation_probe($work)];

        $steps[] = ['extension seam', ...vcs_consumer_extension_probe($work)];
    }
} finally {
    if (! $keep) {
        vcs_consumer_remove($work);
    }
}

$failed = [];

foreach ($steps as $step) {
    [$name, $ok, $detail] = $step;

    echo ($ok ? '  ok   ' : '  FAIL ').$name."\n";

    if (! $ok) {
        echo '       '.str_replace("\n", "\n       ", trim((string) $detail))."\n";
        $failed[] = $name;
    }
}

echo "\n";

if ($failed !== []) {
    fwrite(STDERR, count($failed).' VCS consumer step(s) failed: '.implode(', ', $failed)."\n");

    if ($keep) {
        fwrite(STDERR, 'The work tree is kept at '.$work."\n");
    }

    exit(1);
}

echo 'A consumer install resolves the published '.VCS_CONSUMER_RELEASE." tags and boots.\n";
exit(0);

/**
 * The Composer entry point, as an argv prefix for `proc_open`.
 *
 * @return list<string>
 */
function vcs_consumer_composer_arguments(): array
{
    $phar = (string) getenv('COMPOSER_PHAR');

    if ($phar === '' || ! is_file($phar)) {
        $phar = '';

        foreach (explode(PATH_SEPARATOR, (string) getenv('PATH')) as $directory) {
            if ($directory === '') {
                continue;
            }

            $candidate = rtrim($directory, '\\/').DIRECTORY_SEPARATOR.'composer.phar';

            if (is_file($candidate)) {
                $phar = $candidate;

                break;
            }
        }
    }

    if ($phar === '') {
        fwrite(STDERR, "Could not locate composer.phar — set COMPOSER_PHAR.\n");
        exit(1);
    }

    return [PHP_BINARY, $phar];
}

/**
 * A GitHub token that can read the private mirrors: the environment first, then
 * the `gh` CLI, which is how a developer is already authenticated.
 */
function vcs_consumer_token(): ?string
{
    foreach (['GITHUB_TOKEN', 'GH_TOKEN'] as $name) {
        $value = (string) getenv($name);

        if ($value !== '') {
            return $value;
        }
    }

    $process = proc_open(
        ['gh', 'auth', 'token'],
        [1 => ['pipe', 'w'], 2 => ['redirect', 1]],
        $pipes,
    );

    if (! is_resource($process)) {
        return null;
    }

    $output = trim((string) stream_get_contents($pipes[1]));
    fclose($pipes[1]);

    return proc_close($process) === 0 && $output !== '' ? $output : null;
}

/**
 * Install from the mirrors. A full `update` (not a partial one) is deliberate:
 * the copy has no lock file, and Composer refuses a subset update without one.
 * The constraint under test is the scaffold's own caret range, so a full resolve
 * is exactly the consumer's first install.
 *
 * The scaffold's `pdf-tools` VCS requirement and `spatie/laravel-pdf` are
 * product-only dependencies of the monolith, not of the six core packages; the
 * temporary manifest drops them so the run needs no product stack.
 *
 * @param  list<string>  $composer
 * @return array{bool, string}
 */
function vcs_consumer_update(string $work, array $composer, string $auth): array
{
    $manifestPath = $work.DIRECTORY_SEPARATOR.'composer.json';
    $manifest = json_decode((string) file_get_contents($manifestPath), true);

    if (! is_array($manifest)) {
        return [false, 'composer.json is not valid JSON.'];
    }

    unset($manifest['require']['silverspoonmedia/pdf-tools'], $manifest['require']['spatie/laravel-pdf']);

    $repositories = [];

    foreach ((array) ($manifest['repositories'] ?? []) as $repository) {
        if (($repository['url'] ?? '') === 'https://github.com/silverspoonmedia/pdf-tools.git') {
            continue;
        }

        $repositories[] = $repository;
    }

    $manifest['repositories'] = $repositories;
    $manifest['config'] = array_merge((array) ($manifest['config'] ?? []), ['allow-plugins' => true]);

    file_put_contents(
        $manifestPath,
        json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n",
    );

    return vcs_consumer_process(
        array_merge(
            $composer,
            ['--working-dir='.$work, 'update', '--no-scripts', '--no-interaction', '--prefer-dist', '--no-progress'],
        ),
        $work,
        ['COMPOSER_AUTH' => $auth, 'COMPOSER_NO_INTERACTION' => '1'],
    );
}

/**
 * Every core package is locked at the release line, sourced from its mirror.
 *
 * @return array{bool, string}
 */
function vcs_consumer_lock(string $work): array
{
    $path = $work.DIRECTORY_SEPARATOR.'composer.lock';
    $lock = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

    if (! is_array($lock)) {
        return [false, 'composer.lock is missing or not valid JSON.'];
    }

    $locked = [];

    foreach ((array) ($lock['packages'] ?? []) as $entry) {
        $name = $entry['name'] ?? null;

        if (is_string($name)) {
            $locked[$name] = $entry;
        }
    }

    $violations = [];

    foreach (VCS_CONSUMER_PACKAGES as $package) {
        $entry = $locked[$package] ?? null;

        if (! is_array($entry)) {
            $violations[] = $package.' is absent from composer.lock';

            continue;
        }

        $version = (string) ($entry['version'] ?? '');
        $reference = (string) ($entry['reference'] ?? $entry['dist']['reference'] ?? '');
        $url = (string) ($entry['dist']['url'] ?? $entry['source']['url'] ?? '');

        if (! preg_match('/^'.preg_quote(VCS_CONSUMER_RELEASE, '/').'\.\d+$/', $version)) {
            $violations[] = $package.' locked at "'.$version.'" — expected the '.VCS_CONSUMER_RELEASE.' release line';
        }

        // Composer normalizes a VCS dist to its API form
        // (`api.github.com/repos/{org}/{slug}/zipball/{sha}`), so the org and slug
        // are what identify the mirror, not the URL shape.
        $slug = substr($package, strpos($package, '/') + 1);

        if (! str_contains($url, 'silverspoonmedia/'.$slug) && ! str_contains($url, 'repos/silverspoonmedia/'.$slug)) {
            $violations[] = $package.' resolves from "'.$url.'" — expected its private mirror';
        }

        if ($reference === '') {
            $violations[] = $package.' locks without a commit reference';
        }
    }

    if ($violations !== []) {
        return [false, implode("\n", $violations)];
    }

    return [true, ''];
}

/**
 * `domain:list` must report the ten core domains and no product domain.
 *
 * @return array{bool, string}
 */
function vcs_consumer_domains(string $work): array
{
    vcs_consumer_sqlite($work);

    [$ok, $output] = vcs_consumer_artisan($work, ['domain:list', '--no-ansi']);

    if (! $ok) {
        return [false, $output];
    }

    $missing = [];
    $product = [];

    foreach (['Identity', 'Navigation', 'Notification', 'ActivityLog', 'ErrorLog', 'PaymentGateway', 'Currency', 'Service', 'Inference', 'Ai'] as $domain) {
        if (! str_contains($output, $domain)) {
            $missing[] = $domain;
        }
    }

    // Product names are read from this checkout's registry, never spelled here:
    // the manifest's reference scan treats a literal product name in a shipped
    // backend file as a reference, and a consumer check must survive a fork that
    // removed the product. `config/domains.php` returns a plain array, so the
    // script reads it without booting the framework.
    foreach (vcs_consumer_product_domains() as $domain) {
        if (str_contains($output, $domain)) {
            $product[] = $domain;
        }
    }

    if ($missing !== []) {
        return [false, 'domain:list does not report: '.implode(', ', $missing)."\n".vcs_consumer_tail($output)];
    }

    if ($product !== []) {
        return [false, 'a core-only install reports a product domain: '.implode(', ', $product)];
    }

    return [true, ''];
}

/**
 * Every pinned core URI mounts.
 *
 * @return array{bool, string}
 */
function vcs_consumer_routes(string $work): array
{
    vcs_consumer_sqlite($work);

    [$ok, $output] = vcs_consumer_artisan($work, ['route:list', '--json']);

    if (! $ok) {
        return [false, $output];
    }

    // An artisan warning may precede the JSON payload.
    $json = substr($output, (int) strpos($output, '['));
    $routes = json_decode($json, true);

    if (! is_array($routes)) {
        return [false, "route:list did not return JSON.\n".vcs_consumer_tail($output)];
    }

    $uris = array_map(static fn (array $route): string => ltrim((string) ($route['uri'] ?? ''), '/'), $routes);
    $missing = array_values(array_diff(VCS_CONSUMER_URIS, $uris));

    if ($missing !== []) {
        return [false, 'these core URIs did not mount: '."\n  - ".implode("\n  - ", $missing)];
    }

    return [true, ''];
}

/**
 * One real request: an empty login body answers 422 with a sentence.
 *
 * @return array{bool, string}
 */
function vcs_consumer_validation_probe(string $work): array
{
    vcs_consumer_sqlite($work);

    $script = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::create('/api/v1/auth/login', 'POST', [], [], [], [
    'HTTP_ACCEPT' => 'application/json',
    'CONTENT_TYPE' => 'application/json',
], json_encode([]));
$response = $kernel->handle($request);
echo "\n__STATUS__".$response->getStatusCode()."__BODY__".(string) $response->getContent();
PHP;

    $path = $work.DIRECTORY_SEPARATOR.'validation-probe.php';
    file_put_contents($path, "<?php\n\n".$script."\n");

    [$ok, $output] = vcs_consumer_process(['php', 'validation-probe.php'], $work, vcs_consumer_environment($work));
    @unlink($path);

    if (! preg_match('/__STATUS__(\d+)__BODY__(.*)$/s', $output, $matches)) {
        return [false, "the validation probe produced no response.\n".vcs_consumer_tail($output)];
    }

    $status = (int) $matches[1];
    $body = trim($matches[2]);

    if ($status !== 422) {
        return [false, "POST /api/v1/auth/login with an empty body answered {$status}, expected 422.\n".vcs_consumer_tail($body)];
    }

    $decoded = json_decode($body, true);
    $message = is_array($decoded) ? (string) ($decoded['message'] ?? '') : '';

    if ($message === '' || str_contains($message, 'validation.') || str_starts_with($message, 'api.')) {
        return [false, 'the 422 message is a raw translation key, not a sentence: '.($message !== '' ? $message : $body)];
    }

    return [true, ''];
}

/**
 * The extension seam: a fork adds behavior **beside** the packages, never inside
 * one, and two forks adding the same field keys do not collide.
 *
 * Written into the temp consumer's own `app/` — exactly what a downstream project
 * owns. Three seams and one override:
 *
 *  - `SettingContributor` (`Acme`, then `Globex` with the same config paths) is
 *    registered on `SettingCatalog`; the groups must both appear and the catalog
 *    must resolve each key to the contributor that owns it.
 *  - `MenuContributor` nodes are appended after the core nodes, in registration
 *    order.
 *  - `ai.consumers` rows are read by `silverspoonmedia/ai` with no package edit.
 *  - `App\Support\Auth\UserPresenter` is overridden in the host `app/`, which is
 *    the PSR-4 race a package consumer must win.
 *
 * @return array{bool, string}
 */
function vcs_consumer_extension_probe(string $work): array
{
    $files = [
        'app/Providers/AcmeServiceProvider.php' => <<<'PHP'
<?php

namespace App\Providers;

use App\Support\Navigation\MenuContributorRegistry;
use App\Support\Settings\SettingCatalog;
use Illuminate\Support\ServiceProvider;

class AcmeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if ($this->app->bound(SettingCatalog::class)) {
            $this->app->make(SettingCatalog::class)->register(
                \Acme\Support\AcmeSettingContributor::class,
                \Globex\Support\GlobexSettingContributor::class,
            );
        }

        if ($this->app->bound(MenuContributorRegistry::class)) {
            $this->app->make(MenuContributorRegistry::class)->register(
                \Acme\Support\AcmeMenuContributor::class,
                \Globex\Support\GlobexMenuContributor::class,
            );
        }

        config([
            'ai.consumers' => array_merge((array) config('ai.consumers', []), [
                [
                    'slug' => 'acme.vision',
                    'label' => 'Acme vision',
                    'connection_setting' => 'acme.ai.vision.connection',
                    'model_setting' => 'acme.ai.vision.model',
                    'endpoint_env' => 'ACME_AI_ENDPOINT',
                    'api_key_env' => 'ACME_AI_KEY',
                ],
            ]),
        ]);
    }
}
PHP,
        'app/Support/AcmeSettingContributor.php' => <<<'PHP'
<?php

namespace Acme\Support;

use App\Support\Settings\Contracts\SettingContributor;
use App\Support\Settings\SettingType;

final class AcmeSettingContributor implements SettingContributor
{
    public function definitions(): array
    {
        return [
            'acme.api.base_url' => ['group' => 'acme', 'type' => SettingType::String, 'config' => 'acme.api.base_url'],
            'acme.api.timeout_seconds' => ['group' => 'acme', 'type' => SettingType::Int, 'config' => 'acme.api.timeout_seconds'],
        ];
    }
}
PHP,
        'app/Support/GlobexSettingContributor.php' => <<<'PHP'
<?php

namespace Globex\Support;

use App\Support\Settings\Contracts\SettingContributor;
use App\Support\Settings\SettingType;

final class GlobexSettingContributor implements SettingContributor
{
    public function definitions(): array
    {
        return [
            'globex.api.base_url' => ['group' => 'globex', 'type' => SettingType::String, 'config' => 'globex.api.base_url'],
            'globex.api.timeout_seconds' => ['group' => 'globex', 'type' => SettingType::Int, 'config' => 'globex.api.timeout_seconds'],
        ];
    }
}
PHP,
        'app/Support/AcmeMenuContributor.php' => <<<'PHP'
<?php

namespace Acme\Support;

use App\Support\Navigation\Contracts\MenuContributor;

final class AcmeMenuContributor implements MenuContributor
{
    public function contributions(): array
    {
        return [
            'products' => [
                'nodes' => [[
                    'key' => 'products.acme',
                    'name' => array_fill_keys((array) config('localization.supported_locales', ['en']), 'Acme'),
                    'icon' => 'bi bi-box',
                    'route' => 'products.index',
                    'type' => 'c',
                    'roles' => ['Root', 'Admin'],
                ]],
            ],
        ];
    }
}
PHP,
        'app/Support/GlobexMenuContributor.php' => <<<'PHP'
<?php

namespace Globex\Support;

use App\Support\Navigation\Contracts\MenuContributor;

final class GlobexMenuContributor implements MenuContributor
{
    public function contributions(): array
    {
        return [
            'products' => [
                'after' => 'products.acme',
                'nodes' => [[
                    'key' => 'products.globex',
                    'name' => array_fill_keys((array) config('localization.supported_locales', ['en']), 'Globex'),
                    'icon' => 'bi bi-box-seam',
                    'route' => 'products.index',
                    'type' => 'c',
                    'roles' => ['Root', 'Admin'],
                ]],
            ],
        ];
    }
}
PHP,
        // A host override of a kernel class: the PSR-4 race a consumer wins.
        'app/Support/Auth/UserPresenter.php' => <<<'PHP'
<?php

namespace App\Support\Auth;

use App\Domain\Identity\Models\User;
use App\Support\Presentation\DatePresenter;

class UserPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function present(User $user): array
    {
        [$iso, $formatted, $human] = DatePresenter::pack($user->email_verified_at);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $iso,
            'email_verified_at_formatted' => $formatted,
            'email_verified_at_human' => $human,
            'two_factor_enabled' => $user->hasTwoFactorSecret() && $user->two_factor_confirmed_at !== null,
            'roles' => $user->getRoleNames()->values()->all(),
            'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
            'origin' => 'acme-host-override',
        ];
    }
}
PHP,
    ];

    foreach ($files as $relative => $contents) {
        @mkdir(dirname($work.DIRECTORY_SEPARATOR.$relative), 0777, true);
        file_put_contents($work.DIRECTORY_SEPARATOR.$relative, $contents);
    }

    $manifestPath = $work.DIRECTORY_SEPARATOR.'composer.json';
    $manifest = json_decode((string) file_get_contents($manifestPath), true);

    if (! is_array($manifest)) {
        return [false, 'composer.json is not valid JSON.'];
    }

    // The probe namespaces are the fork's own; the scaffold declares App\* only.
    foreach (['Acme\\Support\\' => 'app/Support/', 'Globex\\Support\\' => 'app/Support/'] as $namespace => $path) {
        $manifest['autoload']['psr-4'][$namespace] = $path;
    }

    file_put_contents(
        $manifestPath,
        json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n",
    );

    $providersPath = $work.DIRECTORY_SEPARATOR.'bootstrap/providers.php';
    $providers = (string) file_get_contents($providersPath);

    if (! str_contains($providers, 'AcmeServiceProvider')) {
        $providers = preg_replace(
            '/use App\\\\Providers\\\\KernelServiceProvider;/',
            "use App\\Providers\\AcmeServiceProvider;\nuse App\\Providers\\KernelServiceProvider;",
            $providers,
            1,
        );

        $providers = preg_replace(
            '/KernelServiceProvider::class,/',
            "KernelServiceProvider::class,\n    AcmeServiceProvider::class,",
            $providers,
            1,
        );

        file_put_contents($providersPath, $providers);
    }

    [$dumpOk, $dumpOutput] = vcs_consumer_process(
        array_merge(vcs_consumer_composer_arguments(), ['--working-dir='.$work, 'dump-autoload', '--no-scripts', '--no-interaction']),
        $work,
        vcs_consumer_environment($work),
    );

    if (! $dumpOk) {
        return [false, "autoload dump failed after adding the host files.\n".vcs_consumer_tail($dumpOutput)];
    }

    vcs_consumer_sqlite($work);

    $script = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$settings = $app->make(App\Support\Settings\SettingCatalog::class);
$groups = $settings->groups();
$definitions = $settings->definitions();

$menu = $app->make(App\Support\Navigation\MenuContributorRegistry::class);
$productNodes = [];
foreach ($menu->contributions()['products'] ?? [] as $entry) {
    foreach ($entry['nodes'] as $node) {
        $productNodes[] = $node['key'];
    }
}

$consumerSlugs = array_map(
    static fn ($row) => is_array($row) ? ($row['slug'] ?? '') : '',
    (array) config('ai.consumers', []),
);

$presenter = app(App\Support\Auth\UserPresenter::class);
$presenterFile = (new ReflectionClass(App\Support\Auth\UserPresenter::class))->getFileName();

echo "\n__REPORT__".json_encode([
    'groups' => $groups,
    'acme_key' => $definitions['acme.api.base_url']['group'] ?? null,
    'globex_key' => $definitions['globex.api.base_url']['group'] ?? null,
    'product_nodes' => $productNodes,
    'consumer_slugs' => $consumerSlugs,
    'presenter_file' => $presenterFile,
], JSON_UNESCAPED_SLASHES);
PHP;

    $path = $work.DIRECTORY_SEPARATOR.'extension-probe.php';
    file_put_contents($path, "<?php\n\n".$script."\n");

    [$ok, $output] = vcs_consumer_process(['php', 'extension-probe.php'], $work, vcs_consumer_environment($work));
    @unlink($path);

    if (! preg_match('/__REPORT__(.*)$/s', $output, $matches)) {
        return [false, "the extension probe produced no report.\n".vcs_consumer_tail($output)];
    }

    $report = json_decode(trim($matches[1]), true);

    if (! is_array($report)) {
        return [false, 'the extension probe report is not JSON: '.vcs_consumer_tail($matches[1])];
    }

    $violations = [];
    $groups = (array) ($report['groups'] ?? []);

    foreach (['acme', 'globex'] as $group) {
        if (! in_array($group, $groups, true)) {
            $violations[] = "the [{$group}] settings group did not join the catalog (groups: ".implode(', ', $groups).')';
        }
    }

    if (($report['acme_key'] ?? null) !== 'acme') {
        $violations[] = 'acme.api.base_url did not resolve to its contributor group';
    }

    if (($report['globex_key'] ?? null) !== 'globex') {
        $violations[] = 'globex.api.base_url did not resolve to its contributor group (the two forks collided)';
    }

    $nodes = (array) ($report['product_nodes'] ?? []);

    if ($nodes !== ['products.acme', 'products.globex']) {
        $violations[] = 'menu contributions landed as ['.implode(', ', $nodes).'], expected the core order to end with acme then globex';
    }

    if (! in_array('acme.vision', (array) ($report['consumer_slugs'] ?? []), true)) {
        $violations[] = 'the ai.consumers row a fork published was not read by the package';
    }

    $presenter = (string) ($report['presenter_file'] ?? '');

    if (! str_contains($presenter, DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Support')) {
        $violations[] = 'the host override of UserPresenter did not win the PSR-4 race (resolved '.$presenter.')';
    }

    if ($violations !== []) {
        return [false, implode("\n", $violations)];
    }

    return [true, ''];
}

/**
 * The product domains this checkout's registry names, read off disk so the
 * check needs no booted framework.
 *
 * @return list<string>
 */
function vcs_consumer_product_domains(): array
{
    $path = dirname(__DIR__).DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'domains.php';
    $registry = is_file($path) ? require $path : [];
    $products = [];

    foreach ((array) $registry as $name => $meta) {
        if (is_string($name) && is_array($meta) && ($meta['kind'] ?? null) === 'product') {
            $products[] = $name;
        }
    }

    return $products;
}

/**
 * `php artisan` inside the copy, with the environment a consumer would have.
 *
 * @param  list<string>  $arguments
 * @return array{bool, string}
 */
function vcs_consumer_artisan(string $work, array $arguments): array
{
    return vcs_consumer_process(array_merge(['php', 'artisan'], $arguments), $work, vcs_consumer_environment($work));
}

/**
 * @return array<string, string>
 */
function vcs_consumer_environment(string $work): array
{
    return [
        'APP_ENV' => 'testing',
        'APP_KEY' => 'base64:2fl+KtvkdphvQyEfmNqD2aE7nKj8vR3xW5yZ1aB4cD8=',
        'APP_DEBUG' => 'true',
        'APP_URL' => 'http://localhost',
        'APP_LOCALE' => 'en',
        'APP_FALLBACK_LOCALE' => 'en',
        'DB_CONNECTION' => 'sqlite',
        'DB_DATABASE' => $work.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'consumer.sqlite',
        'JWT_SECRET' => 'testing-jwt-secret-key-with-enough-length',
        'AUTH_GUARD' => 'api',
        'AUTH_DEFAULT_ROLE' => 'User',
        'BCRYPT_ROUNDS' => '4',
        'CACHE_STORE' => 'array',
        'SESSION_DRIVER' => 'array',
        'QUEUE_CONNECTION' => 'sync',
        'MAIL_MAILER' => 'array',
        'BROADCAST_CONNECTION' => 'null',
        'HCAPTCHA_SECRET' => '',
        'HCAPTCHA_SITEKEY' => '',
        'ENCRYPTION_KMS_ENABLED' => 'false',
        'ENCRYPTION_BLIND_INDEX_PEPPER' => 'consumer-scaffold-pepper',
        'ENCRYPTION_DECRYPTED_CACHE_MAX_TTL' => '60',
        'ENCRYPTION_SESSION_ENABLED' => 'false',
        'LOG_CHANNEL' => 'single',
    ];
}

/**
 * Run a command with the inherited environment plus overrides. stderr is
 * redirected into the stdout pipe: reading the two descriptors one after the
 * other deadlocks as soon as the child fills the buffer of the second.
 *
 * @param  list<string>  $command
 * @param  array<string, string>  $environment
 * @return array{bool, string}
 */
function vcs_consumer_process(array $command, string $directory, array $environment = []): array
{
    $process = proc_open(
        $command,
        [1 => ['pipe', 'w'], 2 => ['redirect', 1]],
        $pipes,
        $directory,
        array_merge(getenv() ?: [], $environment),
    );

    if (! is_resource($process)) {
        return [false, 'could not start '.implode(' ', $command)];
    }

    $output = (string) stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    return [proc_close($process) === 0, $output];
}

function vcs_consumer_sqlite(string $work): void
{
    $path = $work.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'consumer.sqlite';

    if (! is_file($path)) {
        touch($path);
    }
}

/**
 * Copy a tree, skipping an installed `vendor`, build output and caches.
 */
function vcs_consumer_copy(string $source, string $destination): void
{
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST,
    );

    @mkdir($destination, 0777, true);

    foreach ($iterator as $item) {
        $relative = substr($item->getPathname(), strlen($source) + 1);
        $top = strtok($relative, DIRECTORY_SEPARATOR);

        if (in_array($top, ['vendor', 'node_modules', 'storage'], true)) {
            continue;
        }

        if ($item->isDir()) {
            @mkdir($destination.DIRECTORY_SEPARATOR.$relative, 0777, true);

            continue;
        }

        @mkdir(dirname($destination.DIRECTORY_SEPARATOR.$relative), 0777, true);
        copy($item->getPathname(), $destination.DIRECTORY_SEPARATOR.$relative);
    }

    foreach (['logs', 'framework'.DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR.'data', 'framework'.DIRECTORY_SEPARATOR.'sessions', 'framework'.DIRECTORY_SEPARATOR.'views'] as $directory) {
        @mkdir($destination.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.$directory, 0777, true);
    }

    @mkdir($destination.DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'cache', 0777, true);
    @mkdir($destination.DIRECTORY_SEPARATOR.'database', 0777, true);
    touch($destination.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'consumer.sqlite');
}

function vcs_consumer_remove(string $directory): void
{
    if (! is_dir($directory)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($iterator as $item) {
        if ($item->isDir() && ! $item->isLink()) {
            @rmdir($item->getPathname());
        } else {
            @unlink($item->getPathname());
        }
    }

    @rmdir($directory);
}

function vcs_consumer_tail(string $output, int $lines = 30): string
{
    $split = preg_split('/\r?\n/', trim($output)) ?: [];

    return implode("\n", array_slice($split, -$lines));
}
