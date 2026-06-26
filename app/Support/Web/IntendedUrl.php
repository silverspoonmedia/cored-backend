<?php

namespace App\Support\Web;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Rules for storing and resolving the post-login "intended" URL in session.
 *
 * Background sub-resource requests (notification bell polling, DataTables JSON,
 * etc.) must not overwrite the URL the user originally tried to open.
 */
class IntendedUrl
{
    public const SESSION_KEY = 'url.intended';

    public static function shouldStore(Request $request): bool
    {
        if (! $request->isMethod('GET')) {
            return false;
        }

        if ($request->expectsJson() || $request->ajax()) {
            return false;
        }

        $routeName = $request->route()?->getName();

        if ($routeName !== null && self::isExcludedRoute($routeName)) {
            return false;
        }

        return true;
    }

    public static function storeIfApplicable(Request $request): void
    {
        if (! self::shouldStore($request)) {
            return;
        }

        if ($request->session()->has(self::SESSION_KEY)) {
            return;
        }

        $request->session()->put(self::SESSION_KEY, $request->fullUrl());
    }

    /**
     * Pull the stored intended URL, sanitize it, and fall back when missing or unsafe.
     */
    public static function resolve(Request $request, string $fallback): string
    {
        $intended = $request->session()->pull(self::SESSION_KEY);

        if (! is_string($intended) || $intended === '') {
            return $fallback;
        }

        if (! self::isValidIntendedUrl($intended, $request)) {
            return $fallback;
        }

        return $intended;
    }

    public static function forget(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }

    private static function isExcludedRoute(string $routeName): bool
    {
        return in_array($routeName, config('web.intended_url_exclude_routes', []), true);
    }

    private static function isValidIntendedUrl(string $url, Request $request): bool
    {
        if (! self::isSameApplicationUrl($url, $request)) {
            return false;
        }

        $path = self::urlPath($url);

        if ($path === null || $path === '') {
            return false;
        }

        foreach (self::excludedPathSuffixes() as $suffix) {
            if (str_ends_with($path, $suffix)) {
                return false;
            }
        }

        return true;
    }

    private static function isSameApplicationUrl(string $url, Request $request): bool
    {
        if (Str::startsWith($url, '/')) {
            return true;
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return false;
        }

        $allowedHosts = array_unique(array_filter([
            parse_url((string) config('app.url'), PHP_URL_HOST),
            $request->getHost(),
        ]));

        return in_array($host, $allowedHosts, true);
    }

    private static function urlPath(string $url): ?string
    {
        if (Str::startsWith($url, '/')) {
            return parse_url($url, PHP_URL_PATH) ?? $url;
        }

        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) ? $path : null;
    }

    /**
     * Path suffixes that must never become a post-login landing page.
     *
     * @return list<string>
     */
    private static function excludedPathSuffixes(): array
    {
        return [
            '/notifications/unread-count',
            '/notifications/recent',
            '/datatable',
            '/navigation/tree',
        ];
    }
}
