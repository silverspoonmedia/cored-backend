<?php

namespace App\Support\Web;

/**
 * Resolves view paths and Blade component namespaces for the active web layout.
 */
class WebLayout
{
    public static function name(): string
    {
        return (string) config('web.layout', 'adminlte4');
    }

    /**
     * Fully-qualified view name for a screen under resources/views/{layout}/.
     */
    public static function view(string $path): string
    {
        return self::name().'.'.$path;
    }

    public static function componentsPath(): string
    {
        return resource_path('views/'.self::name().'/components');
    }

    /**
     * View composer target for the layout sidebar partial.
     *
     * Anonymous components registered via Blade::anonymousComponentPath() are
     * stored under a hashed view namespace (xxh128 of the prefix), not the
     * human-readable prefix used in <x-{layout}::…> tags.
     */
    public static function sidebarComposerView(): string
    {
        return hash('xxh128', self::name()).'::partials.sidebar';
    }

    public static function navbarComposerView(): string
    {
        return hash('xxh128', self::name()).'::partials.navbar';
    }

    /**
     * Breadcrumb renderer view passed to Breadcrumbs::view().
     */
    public static function breadcrumbsView(): string
    {
        return self::view('partials.breadcrumbs');
    }

    /**
     * Fully-qualified view name for an include partial under resources/views/{layout}/.
     *
     * Alias for view() — use in @include(WebLayout::partial('users.partials.form'), $data).
     */
    public static function partial(string $path): string
    {
        return self::view($path);
    }
}
