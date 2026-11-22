<?php

namespace Tests\Domain\Core;

use App\Domain\Identity\Database\Seeders\RoleSeeder;
use App\Domain\Navigation\Database\Seeders\MenuSeeder;
use App\Domain\Navigation\Models\Menu;
use App\Support\Navigation\Contracts\MenuContributor;
use App\Support\Navigation\MenuContributorRegistry;
use Database\Seeders\ArchivdRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The navigation seam, proven from both ends.
 *
 * `HostMenuSeederTreeTest` is the contract for the tree **with** its products:
 * identical nodes, identical order. This test proves the two things a fork needs
 * beyond that:
 *
 *  - an empty registry is a legal state — the core tree seeds, and a group whose
 *    only purpose was product placement collapses instead of leaving an empty
 *    header behind;
 *  - a contributor's nodes land in the group it names, at the position its
 *    `after` key asks for, in registration order.
 */
class MenuContributorSeamTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_empty_registry_seeds_the_core_tree_and_drops_the_products_group(): void
    {
        // A fork has no product provider, so nothing is registered on the kernel
        // registry. Replacing the singleton reproduces exactly that state.
        $this->app->instance(MenuContributorRegistry::class, new MenuContributorRegistry);

        $this->seed(RoleSeeder::class);
        $this->seed(MenuSeeder::class);

        $roots = Menu::query()
            ->whereNull('parent_id')
            ->where('layout', 'sidebar')
            ->orderBy('order')
            ->get()
            ->map(fn (Menu $menu): string => (string) $menu->getTranslation('name', 'en'))
            ->all();

        $this->assertSame(['Workspace', 'Administration'], $roots, 'An empty contributor registry must leave no empty Products header behind.');
        $this->assertSame(0, Menu::query()->whereNull('parent_id')->where('route', 'archivd.index')->count());
    }

    public function test_a_contributor_lands_its_nodes_after_the_sibling_it_names(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(ArchivdRoleSeeder::class);

        app(MenuContributorRegistry::class)->register(TestMenuContributor::class);

        $this->seed(MenuSeeder::class);

        $settings = Menu::query()
            ->where('layout', 'sidebar')
            ->whereNull('parent_id')
            ->where('name->en', 'Administration')
            ->firstOrFail();

        $settingsParent = Menu::query()
            ->where('parent_id', $settings->id)
            ->where('name->en', 'Settings')
            ->firstOrFail();

        $children = Menu::query()
            ->where('parent_id', $settingsParent->id)
            ->orderBy('order')
            ->get()
            ->map(fn (Menu $menu): string => (string) $menu->getTranslation('name', 'en'))
            ->all();

        // `after` is the sibling key the contribution names, so the inserted node
        // sits directly below it — ahead of the nodes that were already there,
        // including the product contributions competing for the same anchor.
        $this->assertSame(
            ['Platform', 'Contributor node', 'Archivd', 'Microstock', 'Notification audiences', 'Currencies'],
            $children,
        );
    }

    public function test_a_products_group_receives_its_contribution(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(ArchivdRoleSeeder::class);
        $this->seed(MenuSeeder::class);

        $products = Menu::query()
            ->where('layout', 'sidebar')
            ->whereNull('parent_id')
            ->where('name->en', 'Products')
            ->firstOrFail();

        $children = Menu::query()
            ->where('parent_id', $products->id)
            ->orderBy('order')
            ->pluck('route')
            ->all();

        $this->assertSame(['archivd.index'], $children);
    }
}

/**
 * A contributor with no product knowledge: one node in each group the seeder
 * exposes, both after a kernel-owned sibling.
 */
final class TestMenuContributor implements MenuContributor
{
    public function contributions(): array
    {
        return [
            'products' => [
                'nodes' => [
                    [
                        'key' => 'test.product',
                        'name' => array_fill_keys(['en', 'id', 'ja', 'ko', 'zh_CN', 'ar', 'ru'], 'Contributor product'),
                        'route' => 'dashboard',
                        'type' => 'p',
                    ],
                ],
            ],
            'administration.settings' => [
                'after' => 'settings.platform',
                'nodes' => [
                    [
                        'key' => 'test.settings',
                        'name' => array_fill_keys(['en', 'id', 'ja', 'ko', 'zh_CN', 'ar', 'ru'], 'Contributor node'),
                        'route' => 'dashboard',
                        'type' => 'c',
                        'roles' => ['Root', 'Admin'],
                    ],
                ],
            ],
        ];
    }
}
