<?php

namespace Tests\Feature\Http\Controllers\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class Select2ScriptsTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_menu_create_includes_select2_assets_and_init(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $response = $this->actingAs($admin, 'api')
            ->get('/menus/create');

        $response->assertOk();
        $response->assertSee('data-monolith-select2="1"', false);
        $response->assertSee('select2.min.js', false);
        $response->assertSee('select2-init', false);
        $response->assertSee('build/assets/select2-init', false);
    }
}
