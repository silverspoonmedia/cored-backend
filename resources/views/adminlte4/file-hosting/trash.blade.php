@php
    $folders = collect($trash['folders'] ?? []);
    $files = collect($trash['files'] ?? []);
    $ownerQuery = array_filter(['user_id' => $ownerUserId]);
@endphp

<x-adminlte4::layouts.app :title="__('web/file-hosting.trash_title')">
    <x-adminlte4::card :title="__('web/file-hosting.trash_title')">
        <x-slot:actions>
            <a href="{{ route('file-hosting.index', $ownerQuery) }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> {{ __('web/file-hosting.back') }}
            </a>
        </x-slot:actions>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('web/file-hosting.columns.name') }}</th>
                        <th>{{ __('web/file-hosting.columns.type') }}</th>
                        <th>{{ __('web/file-hosting.columns.deleted_at') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($folders as $folder)
                        <tr>
                            <td><i class="bi bi-folder me-1"></i>{{ $folder['name'] ?? '' }}</td>
                            <td>{{ __('web/file-hosting.type_folder') }}</td>
                            <td>{{ $folder['deleted_at'] ?? '' }}</td>
                        </tr>
                    @endforeach
                    @foreach ($files as $file)
                        <tr>
                            <td><i class="bi bi-file-earmark me-1"></i>{{ $file['name'] ?? '' }}</td>
                            <td>{{ __('web/file-hosting.type_file') }}</td>
                            <td>{{ $file['deleted_at'] ?? '' }}</td>
                        </tr>
                    @endforeach
                    @if ($folders->isEmpty() && $files->isEmpty())
                        <tr>
                            <td colspan="3" class="text-muted text-center py-4">{{ __('web/file-hosting.trash_empty') }}</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </x-adminlte4::card>
</x-adminlte4::layouts.app>
