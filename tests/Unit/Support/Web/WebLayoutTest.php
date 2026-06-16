<?php

namespace Tests\Unit\Support\Web;

use App\Support\Web\WebLayout;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class WebLayoutTest extends TestCase
{
    public function test_resolves_view_paths_from_config(): void
    {
        Config::set('web.layout', 'adminlte4');

        $this->assertSame('adminlte4', WebLayout::name());
        $this->assertSame('adminlte4.dashboard', WebLayout::view('dashboard'));
        $this->assertSame('adminlte4.partials.breadcrumbs', WebLayout::breadcrumbsView());
        $this->assertSame(
            hash('xxh128', 'adminlte4').'::partials.sidebar',
            WebLayout::sidebarComposerView(),
        );
        $this->assertSame(
            resource_path('views/adminlte4/components'),
            WebLayout::componentsPath(),
        );
    }

    public function test_follows_custom_layout_value(): void
    {
        Config::set('web.layout', 'tailwind');

        $this->assertSame('tailwind.users.index', WebLayout::view('users.index'));
        $this->assertSame(
            hash('xxh128', 'tailwind').'::partials.sidebar',
            WebLayout::sidebarComposerView(),
        );
    }
}
