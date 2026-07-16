@php
    $formatBytes = function (int $bytes): string {
        if ($bytes === 0) return '0 B';
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1024 * 1024) return number_format($bytes / 1024, 1) . ' KB';
        if ($bytes < 1024 * 1024 * 1024) return number_format($bytes / (1024 * 1024), 1) . ' MB';
        return number_format($bytes / (1024 * 1024 * 1024), 1) . ' GB';
    };
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ ($file['name'] ?? __('web/file-hosting.type_file')).' | '.config('app.name') }}</title>
    <x-adminlte4::partials.assets-head />
    <script>
        (function () {
            try {
                var pref = localStorage.getItem('lte-theme');
                if (pref === 'dark' || (!pref && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                    document.documentElement.setAttribute('data-bs-theme', 'dark');
                }
            } catch (e) {}
        })();
    </script>
    @vite('resources/css/file-hosting/file-hosting.css')
</head>
<body>
<div class="fh-public">
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
                <div class="fh-public__meta-value">{{ $formatBytes((int) ($file['size'] ?? 0)) }}</div>
            </div>
            <div class="fh-public__actions">
                <a href="{{ $downloadUrl }}" class="btn btn-primary btn-lg">
                    <i class="bi bi-download me-1"></i>{{ __('web/file-hosting.download') }}
                </a>
            </div>
        </div>
    </div>
</div>
<x-adminlte4::partials.assets-scripts :bare="true" />
</body>
</html>
