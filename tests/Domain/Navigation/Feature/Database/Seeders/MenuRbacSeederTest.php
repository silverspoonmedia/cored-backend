<?php

namespace Tests\Domain\Navigation\Feature\Database\Seeders;

use Database\Seeders\MenuRbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class MenuRbacSeederTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_it_populates_all_three_pivot_tables(): void
    {
        $this->seed(MenuRbacSeeder::class);

        $this->assertSame(2, DB::table('menu_roles')->count());
        $this->assertSame(1, DB::table('menu_user_includes')->count());
        $this->assertSame(1, DB::table('menu_user_excludes')->count());
    }

    public function test_it_is_idempotent(): void
    {
        $this->seed(MenuRbacSeeder::class);
        $this->seed(MenuRbacSeeder::class);

        $this->assertSame(2, DB::table('menu_roles')->count());
        $this->assertSame(1, DB::table('menu_user_includes')->count());
        $this->assertSame(1, DB::table('menu_user_excludes')->count());
    }
}
