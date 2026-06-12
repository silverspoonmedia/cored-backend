<?php

namespace Tests\Feature\Support\Api;

use App\Support\Api\ApiClient;
use App\Support\Localization\LocaleCookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The web layer is a pure API consumer: every web controller forwards to
 * /api/v1/* over a real HTTP round-trip. That second request runs its OWN
 * SetLocale middleware, so the web-resolved locale must be carried across or
 * the two layers desync (web renders `id`, API replies in `en`).
 *
 * Why it surfaced "randomly": `users.locale` is nullable. Users who had
 * switched language while logged in resolved from the DB on BOTH layers
 * (synced); users whose locale came from the cookie tier hit the bug, because
 * the server-to-server API call carried no cookie and the API fell back to the
 * app default. Different user/session = different behaviour = looked random.
 *
 * The fix: ApiClient forwards the ALREADY-RESOLVED active locale
 * (`app()->getLocale()`, set by the web SetLocale: db > cookie > default) to
 * the API as the `locale` cookie. These tests pin that contract — given the
 * resolved app locale, the forwarded API request carries the matching cookie.
 *
 * Note: end-to-end cookie plumbing through the real /users/datatable route is
 * not asserted here. The test harness's auth uses a stateful guard
 * (`auth('api')->login()`), so `withCookie()` values do not reach the inner
 * request (the same limitation noted in UserDatatableTest). The locale
 * RESOLUTION itself is covered by LocaleSwitchTest; this file covers the
 * FORWARDING contract that resolution feeds into.
 */
class ApiClientLocaleForwardingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string}>
     */
    public static function localeProvider(): array
    {
        return [
            'indonesian' => ['id'],
            'english' => ['en'],
        ];
    }

    #[DataProvider('localeProvider')]
    public function test_resolved_locale_is_forwarded_to_the_api(string $locale): void
    {
        Http::fake(['*/api/v1/*' => Http::response(['data' => []], 200)]);

        // Stand in for whatever the web SetLocale resolved (db > cookie > default).
        app()->setLocale($locale);

        app(ApiClient::class)->get('users');

        Http::assertSent(function ($request) use ($locale) {
            $cookieHeader = $request->header('Cookie');
            $cookieHeader = is_array($cookieHeader)
                ? implode('; ', $cookieHeader)
                : (string) $cookieHeader;

            return str_contains($request->url(), '/api/v1/users')
                && str_contains($cookieHeader, LocaleCookie::name().'='.$locale);
        });
    }

    public function test_forwarded_locale_cookie_uses_configured_cookie_name(): void
    {
        config()->set('localization.cookie_name', 'app_locale');

        Http::fake(['*/api/v1/*' => Http::response(['data' => []], 200)]);

        app()->setLocale('id');

        app(ApiClient::class)->get('users');

        Http::assertSent(function ($request) {
            $cookieHeader = $request->header('Cookie');
            $cookieHeader = is_array($cookieHeader)
                ? implode('; ', $cookieHeader)
                : (string) $cookieHeader;

            return str_contains($cookieHeader, 'app_locale=id');
        });
    }
}
