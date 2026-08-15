<?php

namespace Tests\Feature\Http\Middleware;

use Tests\TestCase;

class RequireSpaOriginTest extends TestCase
{
    public function test_direct_api_hit_is_blocked_outside_local_and_testing(): void
    {
        $this->app->detectEnvironment(fn () => 'staging');

        $this->getJson('/api/ping')
            ->assertForbidden()
            ->assertJsonPath('message', __('api/common.spa_origin_required'));
    }

    public function test_worker_brokered_hit_passes_outside_local_and_testing(): void
    {
        $this->app->detectEnvironment(fn () => 'staging');

        $this->getJson('/api/ping', ['X-SPA-Origin' => '1'])
            ->assertOk()
            ->assertJsonPath('message', __('api/common.pong'));
    }

    public function test_direct_api_hit_passes_in_testing(): void
    {
        $this->getJson('/api/ping')
            ->assertOk()
            ->assertJsonPath('message', __('api/common.pong'));
    }

    public function test_options_preflight_passes_without_header_outside_local(): void
    {
        $this->app->detectEnvironment(fn () => 'staging');

        $response = $this->call('OPTIONS', '/api/ping');

        $response->assertSuccessful();
    }
}
