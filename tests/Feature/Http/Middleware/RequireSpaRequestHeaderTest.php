<?php

namespace Tests\Feature\Http\Middleware;

use App\Http\Middleware\RequireSpaRequestHeader;
use Tests\TestCase;

class RequireSpaRequestHeaderTest extends TestCase
{
    public function test_versioned_api_rejects_missing_marker(): void
    {
        $this->withoutHeader(RequireSpaRequestHeader::HEADER)
            ->getJson('/api/v1/csrf')
            ->assertForbidden()
            ->assertJsonPath('message', __('api/common.spa_origin_required'));
    }

    public function test_versioned_api_rejects_invalid_marker(): void
    {
        $this->withHeader(RequireSpaRequestHeader::HEADER, '0')
            ->getJson('/api/v1/csrf')
            ->assertForbidden()
            ->assertJsonPath('message', __('api/common.spa_origin_required'));
    }

    public function test_versioned_api_accepts_matching_marker(): void
    {
        $this->withHeader(RequireSpaRequestHeader::HEADER, RequireSpaRequestHeader::VALUE)
            ->getJson('/api/v1/csrf')
            ->assertOk()
            ->assertJsonStructure(['data' => ['token']]);
    }

    public function test_options_preflight_passes_without_marker_and_advertises_it(): void
    {
        $origin = rtrim((string) config('app.url'), '/');

        $response = $this->withoutHeader(RequireSpaRequestHeader::HEADER)->call(
            'OPTIONS',
            '/api/v1/csrf',
            server: [
                'HTTP_ORIGIN' => $origin,
                'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
                'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'x-spa-origin',
            ],
        );

        $response->assertSuccessful();
        $this->assertStringContainsString(
            'x-spa-origin',
            strtolower((string) $response->headers->get('Access-Control-Allow-Headers')),
        );
    }

    public function test_provider_webhook_remains_reachable_without_marker(): void
    {
        $this->withoutHeader(RequireSpaRequestHeader::HEADER)
            ->getJson('/api/v1/payments/providers/midtrans/webhook')
            ->assertOk()
            ->assertJsonPath('message', __('api/payments.midtrans.webhook_ping'));
    }
}
