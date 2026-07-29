<?php

namespace Tests\Feature\Http\Controllers\Web;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Tests\TestCase;

class WebCsrfErrorTest extends TestCase
{
    use RefreshDatabase;

    public function test_token_mismatch_renders_json_envelope_and_session_flash_for_ajax_requests(): void
    {
        $this->withSession([]);

        $request = Request::create(route('users.store'), 'POST');
        $request->headers->set('Accept', 'application/json');
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');
        $request->setLaravelSession($this->app['session']->driver());

        $response = $this->app->make(ExceptionHandler::class)->render(
            $request,
            new TokenMismatchException('CSRF token mismatch.'),
        );

        $this->assertNotNull($response);
        $this->assertTrue($request->expectsJson());
        $this->assertSame(419, $response->getStatusCode());

        $payload = json_decode($response->getContent(), true);

        $this->assertIsArray($payload);
        $this->assertSame('error', $payload['status']);
        $this->assertSame(__('web/session.csrf_expired'), $payload['message']);
        $this->assertTrue($payload['reload']);
        $this->assertSame(__('web/session.csrf_expired'), $request->session()->get('error'));
    }
}
