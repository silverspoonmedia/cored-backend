@php
    $folder = $browse['folder'] ?? [];
    $breadcrumb = $browse['breadcrumb'] ?? [];
    $folders = collect($browse['folders'] ?? []);
    $files = collect($browse['files'] ?? []);

@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ ($folder['name'] ?? __('web/file-hosting.type_folder')).' | '.config('app.name') }}</title>
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
    <div class="fh-public__card" style="max-width: 800px;">
        <div class="fh-public__card-body">
            <div class="fh-public__label">{{ __('web/file-hosting.public.shared_folder') }}</div>

            <nav class="fh-breadcrumb" aria-label="breadcrumb">
                <span class="fh-breadcrumb__item is-active">
                    <i class="bi bi-folder2-open fh-breadcrumb__root-icon me-1"></i>{{ $folder['name'] ?? '' }}
                </span>
                @foreach ($breadcrumb as $crumb)
                    <i class="bi bi-chevron-right fh-breadcrumb__sep"></i>
                    @if ($loop->last)
                        <span class="fh-breadcrumb__item is-active">{{ $crumb['name'] ?? '' }}</span>
                    @else
                        <a href="{{ route('file-hosting.public.folders.show', $crumb['id']) }}" class="fh-breadcrumb__item">
                            {{ $crumb['name'] ?? '' }}
                        </a>
                    @endif
                @endforeach
            </nav>

            @if ($folders->isEmpty() && $files->isEmpty())
                <div class="fh-empty">
                    <i class="bi bi-folder2-open fh-empty__icon"></i>
                    <div class="fh-empty__text">{{ __('web/file-hosting.empty') }}</div>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table fh-table">
                        <thead>
                            <tr>
                                <th class="fh-table__icon-cell"></th>
                                <th>{{ __('web/file-hosting.columns.name') }}</th>
                                <th>{{ __('web/file-hosting.columns.type') }}</th>
                                <th>{{ __('web/file-hosting.columns.size') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($folders as $child)
                                <tr>
                        <td class="fh-table__icon-cell">
                            <a href="{{ route('file-hosting.public.folders.show', $child['id']) }}">
                                @include('adminlte4.file-hosting.partials._file-icon', ['mime' => null, 'isFolder' => true])
                            </a>
                        </td>
                        <td>
                            <a href="{{ route('file-hosting.public.folders.show', $child['id']) }}" class="fh-table__name">
                                <span class="fh-table__name-text">{{ $child['name'] ?? '' }}</span>
                            </a>
                        </td>
                        <td class="fh-table__type">{{ __('web/file-hosting.type_folder') }}</td>
                        <td class="fh-table__size">—</td>
                    </tr>
                @endforeach
                            @foreach ($files as $file)
                                <tr>
                        <td class="fh-table__icon-cell">
                            <a href="{{ route('file-hosting.public.files.show', $file['id']) }}">
                                @include('adminlte4.file-hosting.partials._file-icon', ['mime' => $file['mime'] ?? null, 'isFolder' => false])
                            </a>
                        </td>
                        <td>
                            <a href="{{ route('file-hosting.public.files.show', $file['id']) }}" class="fh-table__name" title="{{ $file['name'] ?? '' }}">
                                <span class="fh-table__name-text">{{ $file['name'] ?? '' }}</span>
                            </a>
                        </td>
                        <td class="fh-table__type">{{ $file['mime'] ?? __('web/file-hosting.type_file') }}</td>
                        <td class="fh-table__size">{{ $file['size_human'] ?? '—' }}</td>
                    </tr>
                @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
<x-adminlte4::partials.assets-scripts :bare="true" />
</body>
</html>
