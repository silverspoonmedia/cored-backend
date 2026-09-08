<?php

namespace Tests\Unit\Support\Http;

use App\Support\Http\StampApiCors;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class StampApiCorsTest extends TestCase
{
    public function test_stamps_allow_origin_when_missing_on_api_error(): void
    {
        $origin = rtrim((string) config('app.url'), '/');
        $request = Request::create('/api/v1/notifications/1/read', 'POST', server: [
            'HTTP_ORIGIN' => $origin,
        ]);
        $response = new Response('{"status":"error"}', 403);

        $out = StampApiCors::apply($response, $request);

        $this->assertSame($origin, $out->headers->get('Access-Control-Allow-Origin'));
        $this->assertSame('true', $out->headers->get('Access-Control-Allow-Credentials'));
        $this->assertStringContainsString('Origin', (string) $out->headers->get('Vary'));
    }

    public function test_leaves_existing_allow_origin_untouched(): void
    {
        $origin = rtrim((string) config('app.url'), '/');
        $request = Request::create('/api/v1/auth/me', 'GET', server: [
            'HTTP_ORIGIN' => $origin,
        ]);
        $response = new Response('{"status":"error"}', 401);
        $response->headers->set('Access-Control-Allow-Origin', $origin);

        StampApiCors::apply($response, $request);

        $this->assertSame($origin, $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertNull($response->headers->get('Access-Control-Allow-Credentials'));
    }

    public function test_ignores_disallowed_origin(): void
    {
        $request = Request::create('/api/v1/auth/me', 'GET', server: [
            'HTTP_ORIGIN' => 'https://evil.example',
        ]);
        $response = new Response('{"status":"error"}', 401);

        StampApiCors::apply($response, $request);

        $this->assertNull($response->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_ignores_non_api_paths(): void
    {
        $origin = rtrim((string) config('app.url'), '/');
        $request = Request::create('/login', 'GET', server: [
            'HTTP_ORIGIN' => $origin,
        ]);
        $response = new Response('html', 403);

        StampApiCors::apply($response, $request);

        $this->assertNull($response->headers->get('Access-Control-Allow-Origin'));
    }
}
