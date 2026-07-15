@php
    $folder = $browse['folder'] ?? [];
    $breadcrumb = $browse['breadcrumb'] ?? [];
    $folders = collect($browse['folders'] ?? []);
    $files = collect($browse['files'] ?? []);
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <title>{{ ($folder['name'] ?? __('web/file-hosting.type_folder')).' | '.config('app.name') }}</title>
    <x-adminlte4::partials.assets-head />
</head>
<body class="bg-body-secondary">
<div class="container py-5" style="max-width: 800px;">
    <div class="card shadow-sm">
        <div class="card-body">
            <p class="text-muted small mb-2">{{ __('web/file-hosting.public.shared_folder') }}</p>
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb mb-0">
                    @foreach ($breadcrumb as $crumb)
                        @if ($loop->last)
                            <li class="breadcrumb-item active">{{ $crumb['name'] ?? '' }}</li>
                        @else
                            <li class="breadcrumb-item">
                                <a href="{{ route('file-hosting.public.folders.show', $crumb['id']) }}">{{ $crumb['name'] ?? '' }}</a>
                            </li>
                        @endif
                    @endforeach
                </ol>
            </nav>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                    <tr>
                        <th>{{ __('web/file-hosting.columns.name') }}</th>
                        <th>{{ __('web/file-hosting.columns.type') }}</th>
                        <th>{{ __('web/file-hosting.columns.size') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($folders as $child)
                        <tr>
                            <td>
                                <a href="{{ route('file-hosting.public.folders.show', $child['id']) }}">
                                    <i class="bi bi-folder-fill text-warning me-1"></i>{{ $child['name'] ?? '' }}
                                </a>
                            </td>
                            <td>{{ __('web/file-hosting.type_folder') }}</td>
                            <td>—</td>
                        </tr>
                    @endforeach
                    @foreach ($files as $file)
                        <tr>
                            <td>
                                <a href="{{ route('file-hosting.public.files.show', $file['id']) }}">
                                    <i class="bi bi-file-earmark me-1"></i>{{ $file['name'] ?? '' }}
                                </a>
                            </td>
                            <td>{{ $file['mime'] ?? __('web/file-hosting.type_file') }}</td>
                            <td>{{ number_format(((int) ($file['size'] ?? 0)) / 1024, 1) }} KB</td>
                        </tr>
                    @endforeach
                    @if ($folders->isEmpty() && $files->isEmpty())
                        <tr>
                            <td colspan="3" class="text-muted text-center py-4">{{ __('web/file-hosting.empty') }}</td>
                        </tr>
                    @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<x-adminlte4::partials.assets-scripts :bare="true" />
</body>
</html>
