<?php

namespace Tests\Domain\Core;

use App\Domain\ActivityLog\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Domain\ActivityLog\Repositories\Eloquent\ActivityLogRepository;
use App\Domain\Ai\Contracts\AiConnectionProbe;
use App\Domain\Ai\Models\AiConnection;
use App\Domain\Ai\Services\Probes\OpenAiCompatibleProbe;
use App\Domain\Currency\Models\Currency;
use App\Domain\Currency\Services\ExchangeRateService;
use App\Domain\ErrorLog\Repositories\Contracts\ErrorLogRepositoryInterface;
use App\Domain\ErrorLog\Repositories\Eloquent\ErrorLogRepository;
use App\Domain\Identity\Models\User;
use App\Domain\Inference\Models\AiModelCard;
use App\Domain\Inference\Services\InferenceCatalogService;
use App\Domain\Navigation\Repositories\Contracts\MenuRepositoryInterface;
use App\Domain\Navigation\Repositories\Eloquent\MenuRepository;
use App\Domain\PaymentGateway\Repositories\Contracts\InvoiceRepositoryInterface;
use App\Domain\PaymentGateway\Repositories\Eloquent\InvoiceRepository;
use App\Domain\Service\Models\Service;
use App\Domain\Service\Services\ServiceEntitlementService;
use App\Support\Ai\AiUsageConsolidatorRegistry;
use App\Support\Ai\Contracts\AiUsageConsolidator;
use App\Support\Ai\Contracts\AiUsageLedger;
use App\Support\Billing\FulfillmentRegistry;
use App\Support\Domain\DomainManager;
use App\Support\Encryption\DecryptedValueCache;
use App\Support\Encryption\DecryptionAuditRecorder;
use App\Support\Encryption\DecryptionContext;
use App\Support\Encryption\EnvelopeEncryptionService;
use App\Support\Encryption\InvoicePayloadDecryptLogger;
use App\Support\ErrorLogging\ExceptionRecorder;
use App\Support\Navigation\PresentedTreeEpoch;
use App\Support\Oci\Kms\Contracts\KmsDekClientInterface;
use App\Support\Oci\Kms\LocalKmsDekClient;
use Illuminate\Container\Container;
use Illuminate\Http\Resources\Json\JsonResource;
use Tests\TestCase;

/**
 * Locks the two invariants that make a core domain package usable from a host.
 *
 * 1. The package is actually installed and resolves — a path package that only
 *    exists on disk but is missing from the autoloader fails silently at runtime.
 * 2. The host still wins the PSR-4 race. Extraction is only useful for a
 *    downstream project if `app/Domain/{Domain}/...` shadows the packaged class,
 *    and that precedence lives in one line of the root composer.json: drop it and
 *    every host override becomes dead code with no error to notice.
 */
class PackageOverrideTest extends TestCase
{
    public function test_packaged_core_domain_resolves_from_the_package_directory(): void
    {
        $manager = app(DomainManager::class);

        $this->assertTrue($manager->isPackaged('Navigation'));
        $this->assertTrue($manager->isPackaged('Notification'));
        $this->assertTrue($manager->isPackaged('ActivityLog'));
        $this->assertTrue($manager->isPackaged('ErrorLog'));
        $this->assertTrue($manager->isPackaged('PaymentGateway'));
        $this->assertTrue($manager->isPackaged('Currency'));
        $this->assertTrue($manager->isPackaged('Service'));
        $this->assertTrue($manager->isPackaged('Inference'));
        $this->assertTrue($manager->isPackaged('Ai'));
        $this->assertTrue($manager->isPackaged('Identity'));

        $this->assertStringContainsString(
            'packages/navigation',
            str_replace('\\', '/', $manager->domainPath('Navigation')),
        );

        $this->assertStringContainsString(
            'packages/platform',
            str_replace('\\', '/', $manager->domainPath('Identity')),
        );

        $this->assertStringContainsString(
            'packages/platform',
            str_replace('\\', '/', (string) (new \ReflectionClass(User::class))->getFileName()),
        );

        $reflection = new \ReflectionClass(MenuRepository::class);
        $this->assertStringContainsString(
            'packages/navigation',
            str_replace('\\', '/', (string) $reflection->getFileName()),
        );
    }

    /**
     * One package, two domains: both namespaces must resolve out of
     * `packages/audit`, and both registry entries must name the same package
     * while keeping their own route file.
     */
    public function test_one_package_can_carry_two_core_domains(): void
    {
        $manager = app(DomainManager::class);

        $this->assertSame('silverspoonmedia/audit', $manager->packageOf('ActivityLog')['package'] ?? null);
        $this->assertSame('silverspoonmedia/audit', $manager->packageOf('ErrorLog')['package'] ?? null);

        $this->assertStringContainsString(
            'packages/audit',
            str_replace('\\', '/', $manager->domainPath('ActivityLog')),
        );
        $this->assertStringContainsString(
            'packages/audit',
            str_replace('\\', '/', $manager->domainPath('ErrorLog')),
        );

        foreach ([ActivityLogRepository::class, ErrorLogRepository::class] as $class) {
            $this->assertStringContainsString(
                'packages/audit',
                str_replace('\\', '/', (string) (new \ReflectionClass($class))->getFileName()),
            );
        }

        // Separate route files, loaded by the same package.
        $routePaths = array_map(
            static fn (string $path): string => str_replace('\\', '/', $path),
            $manager->packageApiRoutePaths(),
        );

        foreach (['activity-log', 'error-log'] as $group) {
            $expected = str_replace('\\', '/', base_path('packages/audit/routes/'.$group.'.php'));
            $this->assertContains($expected, $routePaths);
        }
    }

    public function test_billing_package_carries_payment_gateway_currency_and_service(): void
    {
        $manager = app(DomainManager::class);

        $this->assertSame('silverspoonmedia/billing', $manager->packageOf('PaymentGateway')['package'] ?? null);
        $this->assertSame('silverspoonmedia/billing', $manager->packageOf('Currency')['package'] ?? null);
        $this->assertSame('silverspoonmedia/billing', $manager->packageOf('Service')['package'] ?? null);

        foreach (['PaymentGateway', 'Currency', 'Service'] as $domain) {
            $this->assertStringContainsString(
                'packages/billing',
                str_replace('\\', '/', $manager->domainPath($domain)),
            );
        }

        foreach ([InvoiceRepository::class, Currency::class, ExchangeRateService::class, Service::class, ServiceEntitlementService::class] as $class) {
            $this->assertStringContainsString(
                'packages/billing',
                str_replace('\\', '/', (string) (new \ReflectionClass($class))->getFileName()),
            );
        }

        // One provider boots every namespace: the repository binding proves the
        // gateway half is registered by the same package that owns Currency and
        // Service.
        $this->assertInstanceOf(InvoiceRepository::class, app(InvoiceRepositoryInterface::class));

        $routePaths = array_map(
            static fn (string $path): string => str_replace('\\', '/', $path),
            $manager->packageApiRoutePaths(),
        );

        foreach (['payment-gateway', 'currency', 'service'] as $group) {
            $this->assertContains(
                str_replace('\\', '/', base_path('packages/billing/routes/'.$group.'.php')),
                $routePaths,
            );
        }

        // Route files load in registry order, so `/currencies` keeps the URI
        // position it held when it shared `service.php` — a loader that
        // alphabetizes would reorder the v1 surface.
        $currencyRoute = str_replace('\\', '/', base_path('packages/billing/routes/currency.php'));
        $serviceRoute = str_replace('\\', '/', base_path('packages/billing/routes/service.php'));
        $this->assertLessThan(
            array_search($serviceRoute, $routePaths, true),
            array_search($currencyRoute, $routePaths, true),
            'The currency route file must load before the service route file.',
        );

        // The public return-URL route is package-owned too, and loaded through
        // the registry rather than a literal require in routes/web.php.
        $this->assertContains(
            str_replace('\\', '/', base_path('packages/billing/routes/web-ipaymu-return.php')),
            array_map(static fn (string $path): string => str_replace('\\', '/', $path), $manager->packageWebRoutePaths()),
        );
    }

    public function test_ai_package_carries_inference_and_ai(): void
    {
        $manager = app(DomainManager::class);

        $this->assertSame('silverspoonmedia/ai', $manager->packageOf('Inference')['package'] ?? null);
        $this->assertSame('silverspoonmedia/ai', $manager->packageOf('Ai')['package'] ?? null);

        foreach (['Inference', 'Ai'] as $domain) {
            $this->assertStringContainsString(
                'packages/ai',
                str_replace('\\', '/', $manager->domainPath($domain)),
            );
        }

        foreach ([AiConnection::class, AiModelCard::class, InferenceCatalogService::class] as $class) {
            $this->assertStringContainsString(
                'packages/ai',
                str_replace('\\', '/', (string) (new \ReflectionClass($class))->getFileName()),
            );
        }

        // One provider boots both namespaces: resolving a connection model proves
        // the transport half is registered by the package that owns Inference.
        $this->assertInstanceOf(OpenAiCompatibleProbe::class, app(AiConnectionProbe::class));

        $routePaths = array_map(
            static fn (string $path): string => str_replace('\\', '/', $path),
            $manager->packageApiRoutePaths(),
        );

        foreach (['ai', 'inference'] as $group) {
            $this->assertContains(
                str_replace('\\', '/', base_path('packages/ai/routes/'.$group.'.php')),
                $routePaths,
            );
        }
    }

    /**
     * The AI package never imports the product ledger: it asks the kernel for the
     * ledger contract and the consolidator registry, and a product provider is the
     * only place that names itself. Like the billing seam test, this names no
     * product — it asserts whatever booted is consumable.
     */
    public function test_ai_package_consumes_the_product_ledger_through_kernel_seams(): void
    {
        $this->assertTrue(app()->bound(AiUsageLedger::class));
        $this->assertNotSame('', app(AiUsageLedger::class)->usageEventModel());
        $this->assertNotSame([], app(AiUsageLedger::class)->usageEventRelations());

        $registry = app(AiUsageConsolidatorRegistry::class);
        $this->assertNotSame([], $registry->providerSlugs(), 'No product registered an AI usage consolidator.');

        foreach ($registry->providerSlugs() as $slug) {
            $consolidator = $registry->forProvider($slug);

            $this->assertNotNull($consolidator, "Provider slug {$slug} resolved no consolidator.");
            $this->assertInstanceOf(AiUsageConsolidator::class, $consolidator);
        }

        $this->assertNull($registry->forProvider('no-such-provider'));

        // The dashboard row resource is published by the owner, never imported.
        $resource = (string) config('ai.usage_event_resource');
        $this->assertNotSame('', $resource);
        $this->assertTrue(class_exists($resource));
        $this->assertTrue(is_subclass_of($resource, JsonResource::class));
    }

    /**
     * The billing package never imports a product domain: it asks the kernel
     * registry which handler owns a fulfillment target, and the product
     * provider is the only place that names itself. This test deliberately
     * names no product — the point is that the seam holds for whatever set of
     * handlers booted.
     */
    public function test_product_fulfillment_handlers_register_on_the_kernel_registry(): void
    {
        $registry = app(FulfillmentRegistry::class);

        $targets = $registry->targets();
        $this->assertNotSame([], $targets, 'No product registered a fulfillment handler.');

        foreach ($targets as $target) {
            $handler = $registry->forTarget($target);

            $this->assertNotNull($handler, "Target {$target} resolved no handler.");
            $this->assertContains($target, $handler->targets());
        }

        // A retired free SKU is catalog data, not an entitlement: the registry
        // aggregates it so the package can filter paid probes without knowing
        // which product retired what.
        foreach ($registry->freeCodes() as $code) {
            $this->assertNotSame('', $code);
        }

        $this->assertNull($registry->forTarget('no-such-target'));
    }

    public function test_kernel_contract_is_bound_to_the_package_implementation(): void
    {
        $this->assertTrue(app()->bound(PresentedTreeEpoch::class));
        $this->assertInstanceOf(MenuRepositoryInterface::class, app(PresentedTreeEpoch::class));

        // Kernel owns the contract, the audit package owns the implementation, so
        // neither `EnvelopeEncryptionService` nor `bootstrap/app.php` imports a
        // package class.
        $this->assertInstanceOf(DecryptionAuditRecorder::class, app(DecryptionAuditRecorder::class));
        $this->assertInstanceOf(ExceptionRecorder::class, app(ExceptionRecorder::class));
        $this->assertInstanceOf(ActivityLogRepositoryInterface::class, app(ActivityLogRepositoryInterface::class));
        $this->assertInstanceOf(ErrorLogRepositoryInterface::class, app(ErrorLogRepositoryInterface::class));
    }

    public function test_host_file_shadows_the_bundled_package_directory(): void
    {
        // The PSR-4 race, proven per domain: a single-domain package and both
        // namespaces of the bundled package each have their own explicit host
        // prefix in the root manifest, so an override at app/Domain/{Domain}/...
        // must win against packages/*/src/{Domain}/.
        foreach (['Navigation', 'Notification', 'ActivityLog', 'ErrorLog', 'PaymentGateway', 'Currency', 'Service', 'Inference', 'Ai', 'Identity'] as $domain) {
            $this->assertHostPrefixWins('App\\Domain\\'.$domain.'\\Support', 'app/Domain/'.$domain.'/Support');
        }
    }

    /**
     * The kernel's own override hooks: `App\Support`, `App\Http`, the shared
     * repositories, traits/rules/exceptions, and the host `database/` roots.
     *
     * `silverspoonmedia/platform` ships these namespaces, so the root manifest
     * declares each host directory *before* the package copy. The prefixes exist
     * for exactly one reason — a host that needs to replace a kernel class drops
     * the file in `app/` and the autoloader must prefer it — and an empty
     * directory makes that hook look like dead configuration. Probe one file per
     * prefix: if the host copy resolves, the hook is alive; if the package copy
     * resolves, the consumer silently loses the override with no error.
     */
    public function test_kernel_override_prefixes_resolve_host_first(): void
    {
        foreach ([
            'App\\Support\\' => 'app/Support',
            'App\\Http\\' => 'app/Http',
            'App\\Repositories\\' => 'app/Repositories',
            'App\\Traits\\' => 'app/Traits',
            'App\\Rules\\' => 'app/Rules',
            'App\\Exceptions\\' => 'app/Exceptions',
            'Database\\Factories\\' => 'database/factories',
            'Database\\Seeders\\' => 'database/seeders',
        ] as $namespace => $hostPath) {
            $this->assertHostPrefixWins($namespace, $hostPath);
        }
    }

    /**
     * Write one probe class into the host directory and assert the autoloader
     * resolves that path, not a packaged copy of the same namespace.
     *
     * The probe is unique per directory and removed in `finally`, including the
     * directories this test had to create — the repository never sees it.
     */
    private function assertHostPrefixWins(string $namespace, string $hostPath): void
    {
        $hostDir = base_path($hostPath);
        $hostFile = $hostDir.'/PackageOverrideProbe.php';

        // Every ancestor that does not exist yet, deepest first: the probe is a
        // temporary artifact, so the cleanup removes exactly what it created.
        $createdDirs = [];
        for ($dir = $hostDir; ! is_dir($dir); $dir = dirname($dir)) {
            $createdDirs[] = $dir;
        }

        $this->assertFileDoesNotExist($hostFile);

        $loader = require base_path('vendor/autoload.php');

        $fileNamespace = rtrim($namespace, '\\');
        $probe = $fileNamespace.'\\PackageOverrideProbe';

        $forgetMissing = function () use ($loader): void {
            $cache = new \ReflectionProperty($loader, 'missingClasses');
            $cache->setValue($loader, []);
        };

        try {
            $this->assertFalse((bool) $loader->findFile($probe));

            if ($createdDirs !== []) {
                mkdir($hostDir, 0777, true);
            }

            file_put_contents(
                $hostFile,
                "<?php\n\nnamespace {$fileNamespace};\n\nfinal class PackageOverrideProbe\n{\n    public const SOURCE = 'host';\n}\n"
            );

            clearstatcache();
            $forgetMissing();

            $this->assertSame(
                str_replace('\\', '/', (string) realpath($hostFile)),
                str_replace('\\', '/', (string) realpath((string) $loader->findFile($probe))),
                "A host class in the [{$fileNamespace}] namespace must resolve from {$hostPath}/, not from a package copy."
            );
        } finally {
            if (is_file($hostFile)) {
                @unlink($hostFile);
            }

            foreach ($createdDirs as $dir) {
                if (is_dir($dir) && count(scandir($dir) ?: []) <= 2) {
                    @rmdir($dir);
                }
            }

            $forgetMissing();
            clearstatcache();
        }
    }

    public function test_kernel_boots_without_the_audit_package(): void
    {
        // The acceptance clause behind the two new kernel contracts: with the
        // audit implementations unbound, the decrypt choke point still builds
        // and simply stops auditing. A bare container is the honest simulation —
        // a host that never installed `silverspoonmedia/audit`.
        $container = new Container;
        $container->instance(KmsDekClientInterface::class, new LocalKmsDekClient);
        $container->instance(DecryptedValueCache::class, new DecryptedValueCache);
        $container->instance(InvoicePayloadDecryptLogger::class, new InvoicePayloadDecryptLogger);

        $this->assertFalse($container->bound(DecryptionAuditRecorder::class));

        $service = $container->make(EnvelopeEncryptionService::class);

        $this->assertInstanceOf(EnvelopeEncryptionService::class, $service);
        $this->assertSame(
            'secret',
            $service->decrypt($service->encrypt('secret'), new DecryptionContext('users', 'user-1', 'field')),
        );
    }
}
