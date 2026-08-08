@php
    $publicConfig = [
        'appName' => config('app.name'),
        'labels' => [
            'sharedFile' => __('web/file-hosting.public.shared_file'),
            'sharedFolder' => __('web/file-hosting.public.shared_folder'),
            'type' => __('web/file-hosting.columns.type'),
            'size' => __('web/file-hosting.columns.size'),
            'name' => __('web/file-hosting.columns.name'),
            'download' => __('web/file-hosting.download'),
            'folder' => __('web/file-hosting.type_folder'),
            'file' => __('web/file-hosting.type_file'),
            'empty' => __('web/file-hosting.empty'),
            'unavailable' => __('web/file-hosting.public.shared_file'),
            'notFound' => __('api/file-hosting.share_not_found'),
        ],
    ];
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ ($file['name'] ?? __('web/file-hosting.type_file')).' | '.config('app.name') }}</title>
    <x-adminlte4::partials.assets-head />
    <script>
        (function () {
            try {
                var pref = localStorage.getItem('lte-theme');
                if (pref === 'light' || (!pref && window.matchMedia('(prefers-color-scheme: light)').matches)) {
                    document.documentElement.setAttribute('data-bs-theme', 'light');
                }
            } catch (e) {}
        })();
    </script>
    @vite('resources/css/file-hosting/file-hosting.css')
</head>
<body>
<div class="fh-public" data-fh-public-app data-config="{{ json_encode($publicConfig) }}">
    <div data-fh-public-mount>
        <div class="fh-public__card">
            <div class="fh-public__card-body">
                <div class="fh-public__label">{{ __('web/file-hosting.public.shared_file') }}</div>
                <div class="fh-public__file-icon">
                    @include('adminlte4.file-hosting.partials._file-icon', ['mime' => $file['mime'] ?? null, 'isFolder' => false])
                </div>
                <h1 class="fh-public__file-name">{{ $file['name'] ?? '' }}</h1>
                <div class="fh-public__meta">
                    <div class="fh-public__meta-label">{{ __('web/file-hosting.columns.type') }}</div>
                    <div class="fh-public__meta-value">{{ $file['mime'] ?? '—' }}</div>
                    <div class="fh-public__meta-label">{{ __('web/file-hosting.columns.size') }}</div>
                    <div class="fh-public__meta-value">{{ $file['size_human'] ?? '0 B' }}</div>
                </div>
                <div class="fh-public__actions">
                    <a href="{{ $downloadUrl }}" class="btn btn-primary btn-lg">
                        <i class="bi bi-download me-1"></i>{{ __('web/file-hosting.download') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<x-adminlte4::partials.assets-scripts :bare="true" />
@vite('resources/js/file-hosting/public-app.js')
</body>
</html>
