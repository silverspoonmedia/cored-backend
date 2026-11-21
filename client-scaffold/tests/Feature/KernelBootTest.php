<?php

namespace Tests\Feature;

use App\Providers\KernelServiceProvider;
use App\Repositories\Contracts\EloquentCrudRepositoryInterface;
use App\Support\Billing\FulfillmentRegistry;
use App\Support\Broadcasting\ChannelRegistrarRegistry;
use Fruitcake\Cors\CorsService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * A consumer install has no `AppServiceProvider`: `KernelServiceProvider` is the
 * only source of the bindings the kernel code assumes exist. This test boots
 * `bootstrap/app.php` the way a request does and asserts that surface, so a
 * scaffold that drops the provider fails here rather than at the first request
 * that injects the CORS service.
 *
 * Database-free on purpose: the scaffold's own suite runs inside the smoke copy
 * against the copy's `vendor/`, so a test that needed a host schema would tie
 * the template to the monolith's migration order.
 */
class KernelBootTest extends TestCase
{
    public function test_the_kernel_provider_is_registered_explicitly(): void
    {
        $this->assertArrayHasKey(
            KernelServiceProvider::class,
            $this->app->getLoadedProviders(),
            'bootstrap/providers.php must list KernelServiceProvider: it is not auto-discovered, and a consumer has no AppServiceProvider to answer for it.',
        );
    }

    public function test_the_kernel_bindings_resolve(): void
    {
        $this->assertInstanceOf(EloquentCrudRepositoryInterface::class, $this->app->make(EloquentCrudRepositoryInterface::class));

        // `HandleCors` injects this service on every request that reaches the
        // CORS middleware, so an unresolved binding is a fatal, not a fallback.
        $this->assertInstanceOf(CorsService::class, $this->app->make(CorsService::class));

        $this->assertInstanceOf(FulfillmentRegistry::class, $this->app->make(FulfillmentRegistry::class));
        $this->assertInstanceOf(ChannelRegistrarRegistry::class, $this->app->make(ChannelRegistrarRegistry::class));
    }

    public function test_the_registry_mounts_the_core_routes(): void
    {
        // One Identity route, one kernel cross-domain route, and one package
        // route: the three loaders a consumer assembles, each proved once.
        $this->assertTrue(Route::has('api.v1.auth.login'), 'The Identity package routes did not mount — check config/domains.php and routes/api/v1.php.');
        $this->assertTrue(Route::has('api.v1.batch'), 'The kernel cross-domain routes did not mount — packageApiRoutePaths() must include the platform package kernel-*.php files.');
        $this->assertTrue(Route::has('api.v1.navigation.menus.index'), 'The Navigation package routes did not mount.');
    }

    public function test_the_domain_list_command_reports_the_ten_core_domains(): void
    {
        $this->artisan('domain:list')
            ->expectsOutputToContain('Identity')
            ->expectsOutputToContain('PaymentGateway')
            ->expectsOutputToContain('Ai')
            ->assertSuccessful();
    }

    public function test_the_kernel_ships_the_context_scoped_cache_flush_command(): void
    {
        // The command lives in the kernel package because the cache it forgets
        // does; a consumer has no `app/Console/Commands` to carry it.
        $this->assertArrayHasKey(
            'encryption:flush-decrypted-cache',
            Artisan::all(),
            'KernelServiceProvider::boot() must register the cache flush command.',
        );
    }
}
