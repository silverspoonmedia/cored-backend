@props([
    'title' => null,
])
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <title>{{ $title ? $title.' | ' : '' }}{{ config('app.name', 'Monolith') }}</title>
        <x-adminlte4::partials.assets-head />
        @stack('head')
    </head>
    <body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
        {{-- Skip-link must stay outside .app-wrapper — AdminLTE 4 CSS grid auto-places
             unnamed children and can shove the footer under the sidebar. --}}
        <a href="#main-content" class="visually-hidden-focusable skip-link p-2">
            {{ __('ui.skip_to_content') }}
        </a>
        <div class="app-wrapper">
            <x-adminlte4::partials.navbar />
            <x-adminlte4::partials.sidebar />

            <main class="app-main" id="main-content">
                <div class="app-content-header">
                    <div class="container-fluid">
                        <div class="row align-items-center">
                            <div class="col-sm-6">
                                <h3 class="mb-0">{{ $title }}</h3>
                            </div>
                            {{-- Breadcrumb trail is defined once per route in routes/breadcrumbs.php
                                 and auto-rendered for the current route. Views needing a dynamic
                                 segment (e.g. orders.show) call Breadcrumbs::setCurrentRoute(...). --}}
                            @if (Breadcrumbs::exists())
                                <div class="col-sm-6">
                                    {{ Breadcrumbs::view(\App\Support\Web\WebLayout::breadcrumbsView()) }}
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="app-content">
                    <div class="container-fluid">
                        <x-adminlte4::alert />
                        {{ $slot }}
                    </div>
                </div>
            </main>

            <x-adminlte4::partials.footer />
        </div>

        <x-adminlte4::partials.assets-scripts />
        {{ $scripts ?? '' }}
        @stack('scripts')
    </body>
</html>
