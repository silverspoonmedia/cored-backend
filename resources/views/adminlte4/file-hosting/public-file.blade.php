@php
    $fileId = $file['id'] ?? '';
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <title>{{ ($file['name'] ?? __('web/file-hosting.type_file')).' | '.config('app.name') }}</title>
    <x-adminlte4::partials.assets-head />
</head>
<body class="bg-body-secondary">
<div class="container py-5" style="max-width: 640px;">
    <div class="card shadow-sm">
        <div class="card-body">
            <p class="text-muted small mb-2">{{ __('web/file-hosting.public.shared_file') }}</p>
            <h1 class="h4 mb-3">{{ $file['name'] ?? '' }}</h1>
            <dl class="row small mb-4">
                <dt class="col-4">{{ __('web/file-hosting.columns.type') }}</dt>
                <dd class="col-8">{{ $file['mime'] ?? '—' }}</dd>
                <dt class="col-4">{{ __('web/file-hosting.columns.size') }}</dt>
                <dd class="col-8">{{ number_format(((int) ($file['size'] ?? 0)) / 1024, 1) }} KB</dd>
            </dl>
            <a href="{{ $downloadUrl }}" class="btn btn-primary">
                <i class="bi bi-download me-1"></i>{{ __('web/file-hosting.download') }}
            </a>
        </div>
    </div>
</div>
<x-adminlte4::partials.assets-scripts :bare="true" />
</body>
</html>
