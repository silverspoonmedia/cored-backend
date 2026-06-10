@props([
    'title' => null,
    'heading' => null,
])
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <title>{{ $title ? $title.' | ' : '' }}{{ config('app.name', 'Monolith') }}</title>
        <x-partials.assets-head />
    </head>
    <body class="login-page bg-body-secondary">
        <div class="login-box">
            <div class="login-logo">
                <a href="{{ route('login') }}"><b>Mono</b>lith</a>
            </div>
            <div class="card">
                <div class="card-body login-card-body">
                    @isset($heading)
                        <p class="login-box-msg">{{ $heading }}</p>
                    @endisset

                    <x-alert />
                    {{ $slot }}
                </div>
            </div>
        </div>

        <x-partials.assets-scripts :bare="true" />
    </body>
</html>
