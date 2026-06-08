<?php

namespace Tests\Unit\Services\Navigation;

use App\Models\Menu;
use App\Services\Navigation\MenuService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class MenuServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    private MenuService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->service = app(MenuService::class);
    }

    public function test_create_maps_request_fields_and_assigns_roles(): void
    {
        $menu = $this->service->create([
            'name' => 'Settings',
            'type' => 'h',
            'authenticate' => true,
            'guest_only' => false,
            'roles' => ['Admin'],
        ]);

        $this->assertTrue($menu->is_authenticate);
        $this->assertFalse($menu->is_guest_only);
        $this->assertSame(['Admin'], $menu->roles->pluck('name')->all());
    }

    public function test_update_with_empty_roles_array_clears_assignments(): void
    {
        $menu = $this->service->create(['name' => 'X', 'type' => 'h', 'roles' => ['Admin']]);

        $updated = $this->service->update($menu, ['roles' => []]);

        $this->assertCount(0, $updated->roles);
    }

    public function test_update_without_roles_key_keeps_assignments(): void
    {
        $menu = $this->service->create(['name' => 'Y', 'type' => 'h', 'roles' => ['Admin']]);

        $updated = $this->service->update($menu, ['name' => 'Y renamed']);

        $this->assertSame('Y renamed', $updated->name);
        $this->assertSame(['Admin'], $updated->roles->pluck('name')->all());
    }

    public function test_failed_relationship_sync_rolls_back_create(): void
    {
        // A non-existent user id makes the include sync fail; the whole create
        // (menu row + any partial relations) must roll back.
        try {
            $this->service->create([
                'name' => 'Atomic',
                'type' => 'h',
                'user_includes' => ['not-a-real-user-uuid'],
            ]);
            $this->fail('Expected the failing sync to throw.');
        } catch (\Throwable) {
            // expected
        }

        $this->assertDatabaseMissing('menus', ['name' => 'Atomic']);
    }
}
