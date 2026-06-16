<?php

namespace Tests\Unit\Support\Navigation;

use App\Support\Navigation\MenuLayout;
use Tests\TestCase;

class MenuLayoutTest extends TestCase
{
    public function test_known_layouts_are_configured(): void
    {
        $this->assertContains(MenuLayout::SIDEBAR, MenuLayout::all());
        $this->assertContains(MenuLayout::NAVBAR, MenuLayout::all());
        $this->assertSame('sidebar', MenuLayout::default());
    }
}
