<?php

namespace Tests\Feature\Http\Middleware;

use Tests\TestCase;

/**
 * RequireSpaOrigin is audit-only: direct (Worker-less) traffic is the
 * sanctioned data path and must never be blocked, regardless of environment.
 */
class RequireSpaOriginTest extends TestCase
{
    public function test_direct_api_hit_is_allowed_outside_local(): void
    {
        $this->app->detectEnvironment(fn () => 'staging');

        $this->getJson('/api/ping')
            ->assertOk()
            ->assertJsonPath('message', __('api/common.pong'));
    }

    public function test_worker_brokered_hit_passes_outside_local(): void
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

    public function test_options_preflight_passes_without_header(): void
    {
        $this->app->detectEnvironment(fn () => 'staging');

        $response = $this->call('OPTIONS', '/api/ping');

        $response->assertSuccessful();
    }
}
