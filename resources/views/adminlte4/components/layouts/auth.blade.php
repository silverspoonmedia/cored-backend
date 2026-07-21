@props([
    'title' => null,
    'heading' => null,
])
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <title>{{ $title ? $title.' | ' : '' }}{{ config('app.name', 'Monolith') }}</title>
        <x-adminlte4::partials.assets-head />
    </head>
    <body class="login-page bg-body-secondary">
        <a href="#main-content" class="visually-hidden-focusable skip-link p-2">
            {{ __('ui.skip_to_content') }}
        </a>
        <div class="login-box">
            <div class="login-logo">
                <a href="{{ route('login') }}"><strong>Mono</strong>lith</a>
            </div>
            <div class="card">
                <div class="card-body login-card-body" id="main-content">
                    @isset($heading)
                        <p class="login-box-msg">{{ $heading }}</p>
                    @endisset

                    <x-adminlte4::alert />
                    {{ $slot }}
                </div>
            </div>
        </div>

        <x-adminlte4::partials.assets-scripts :bare="true" />
    </body>
</html>
