@props([
    'title' => null,
])
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <title>{{ $title ? $title.' | ' : '' }}{{ config('app.name', 'Monolith') }}</title>
        <x-partials.assets-head />
    </head>
    <body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
        <div class="app-wrapper">
            <x-partials.navbar />
            <x-partials.sidebar />

            <main class="app-main">
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
                                    {{ Breadcrumbs::view('partials.breadcrumbs') }}
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="app-content">
                    <div class="container-fluid">
                        <x-alert />
                        {{ $slot }}
                    </div>
                </div>
            </main>

            <x-partials.footer />
        </div>

        <x-partials.assets-scripts />
        {{ $scripts ?? '' }}
    </body>
</html>
