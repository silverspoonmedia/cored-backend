<?php

namespace Tests\Unit\Support\Web;

use App\Support\Web\IntendedUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntendedUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolve_returns_sanitized_stored_url(): void
    {
        $this->get(route('login'));

        session()->put(IntendedUrl::SESSION_KEY, route('users.index'));

        $this->assertSame(route('users.index'), IntendedUrl::resolve($this->app['request'], route('dashboard')));
    }

    public function test_resolve_rejects_notification_poll_url(): void
    {
        $this->get(route('login'));

        session()->put(IntendedUrl::SESSION_KEY, route('notifications.unread-count'));

        $this->assertSame(route('dashboard'), IntendedUrl::resolve($this->app['request'], route('dashboard')));
    }

    public function test_resolve_reads_intended_url_from_with_session(): void
    {
        $this->withSession([
            IntendedUrl::SESSION_KEY => route('users.index'),
        ])->get(route('login'));

        $this->assertSame(route('users.index'), IntendedUrl::resolve($this->app['request'], route('dashboard')));
    }
}
