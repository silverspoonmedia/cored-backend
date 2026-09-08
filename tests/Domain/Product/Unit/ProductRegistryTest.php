<?php

namespace Tests\Domain\Product\Unit;

use App\Support\Product\Enums\ProductActivationType;
use App\Support\Product\ProductRegistry;
use Tests\TestCase;

class ProductRegistryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('products', [
            'Archivd' => [
                'activation_type' => 'implicit',
                'fulfillment_target' => 'archivd',
                'menu_routes' => ['archivd.index'],
            ],
            'Billing' => [
                'activation_type' => 'explicit',
                'fulfillment_target' => null,
            ],
        ]);
    }

    public function test_has_returns_true_for_known_products(): void
    {
        $this->assertTrue(ProductRegistry::has('Archivd'));
        $this->assertTrue(ProductRegistry::has('Billing'));
        $this->assertFalse(ProductRegistry::has('Unknown'));
    }

    public function test_activation_type_resolves_enum(): void
    {
        $this->assertSame(ProductActivationType::Implicit, ProductRegistry::activationType('Archivd'));
        $this->assertSame(ProductActivationType::Explicit, ProductRegistry::activationType('Billing'));
    }

    public function test_is_implicit_and_is_explicit(): void
    {
        $this->assertTrue(ProductRegistry::isImplicit('Archivd'));
        $this->assertFalse(ProductRegistry::isExplicit('Archivd'));

        $this->assertTrue(ProductRegistry::isExplicit('Billing'));
        $this->assertFalse(ProductRegistry::isImplicit('Billing'));
    }

    public function test_fulfillment_target_returns_value(): void
    {
        $this->assertSame('archivd', ProductRegistry::fulfillmentTarget('Archivd'));
        $this->assertNull(ProductRegistry::fulfillmentTarget('Billing'));
    }

    public function test_product_key_by_fulfillment_target_resolves(): void
    {
        $this->assertSame('Archivd', ProductRegistry::productKeyByFulfillmentTarget('archivd'));
        $this->assertNull(ProductRegistry::productKeyByFulfillmentTarget('unknown'));
    }

    public function test_implicit_product_keys(): void
    {
        $this->assertSame(['Archivd'], ProductRegistry::implicitProductKeys());
    }

    public function test_explicit_product_keys(): void
    {
        $this->assertSame(['Billing'], ProductRegistry::explicitProductKeys());
    }

    public function test_activation_type_throws_on_unknown(): void
    {
        $this->expectException(\RuntimeException::class);
        ProductRegistry::activationType('Unknown');
    }

    public function test_menu_routes_for_returns_routes_for_known_product(): void
    {
        $this->assertSame(['archivd.index'], ProductRegistry::menuRoutesFor('Archivd'));
    }

    public function test_menu_routes_for_returns_empty_for_product_without_routes(): void
    {
        $this->assertSame([], ProductRegistry::menuRoutesFor('Billing'));
    }

    public function test_menu_routes_for_returns_empty_for_unknown_product(): void
    {
        $this->assertSame([], ProductRegistry::menuRoutesFor('Unknown'));
    }

    public function test_product_key_by_menu_route_resolves_known_route(): void
    {
        $this->assertSame('Archivd', ProductRegistry::productKeyByMenuRoute('archivd.index'));
    }

    public function test_product_key_by_menu_route_returns_null_for_unmapped_route(): void
    {
        $this->assertNull(ProductRegistry::productKeyByMenuRoute('dashboard.index'));
        $this->assertNull(ProductRegistry::productKeyByMenuRoute('unknown.route'));
    }
}
