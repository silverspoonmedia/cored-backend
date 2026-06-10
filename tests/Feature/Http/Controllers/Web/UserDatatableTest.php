<?php

namespace Tests\Feature\Http\Controllers\Web;

use App\Models\User;
use App\Support\Auth\AuthCookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * The web datatable is a pure PROXY of GET /api/v1/users (datatable mode): it
 * forwards the request and rewrites the API's allowed-action keys onto web
 * routes. RBAC filtering itself is covered by the API's own tests, so here we
 * fake the API and assert only the proxy/mapping behaviour.
 */
class UserDatatableTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_datatable_proxies_api_and_maps_actions_to_web_urls(): void
    {
        $admin = $this->userWithRole('Admin');
        $moderator = User::factory()->create(['email' => 'mod@example.com']);

        Http::fake([
            '*/api/v1/users*' => Http::response([
                'draw' => 1,
                'recordsTotal' => 1,
                'recordsFiltered' => 1,
                'data' => [
                    [
                        'id' => $moderator->id,
                        'name' => $moderator->name,
                        'email' => 'mod@example.com',
                        'roles' => [],
                        'email_verified_at' => null,
                        // API decides which actions the actor may take (RBAC).
                        'actions' => [
                            ['key' => 'show', 'url' => 'http://localhost/api/v1/users/'.$moderator->id],
                            ['key' => 'edit', 'url' => 'http://localhost/api/v1/users/'.$moderator->id],
                            ['key' => 'delete', 'url' => 'http://localhost/api/v1/users/'.$moderator->id],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingWithJwtCookie($admin)
            ->getJson('/users/datatable?draw=1&start=0&length=50');

        $response->assertOk()->assertJsonPath('recordsTotal', 1);

        $row = collect($response->json('data'))->firstWhere('email', 'mod@example.com');
        $this->assertNotNull($row);

        // 'show' is dropped (no web detail page); edit/delete map onto web routes.
        $this->assertSame(['edit', 'delete'], collect($row['actions'])->pluck('key')->all());
        $this->assertStringEndsWith('/users/'.$moderator->id.'/edit', $row['actions'][0]['url']);
        $this->assertSame('DELETE', $row['actions'][1]['method']);

        // The list request was forwarded to the API in datatable mode. (Bearer
        // forwarding relies on the encrypted-cookie roundtrip, which the test
        // harness's raw withCookie() does not reproduce, so it isn't asserted
        // here — it is exercised end-to-end in the browser.)
        Http::assertSent(fn ($request) => $request->method() === 'GET'
            && str_contains($request->url(), '/api/v1/users')
            && str_contains($request->url(), 'display_mode=datatable'));
    }

    public function test_datatable_requires_authentication(): void
    {
        $this->get('/users/datatable?draw=1')
            ->assertRedirect(route('login'));
    }

    private function actingWithJwtCookie(User $user): static
    {
        $token = auth('api')->login($user);

        return $this->withCookie(AuthCookie::NAME, $token);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
