@php
    $breadcrumb = $browse['breadcrumb'] ?? [];
    $folders = collect($browse['folders'] ?? []);
    $files = collect($browse['files'] ?? []);
    $ownerQuery = array_filter(['user_id' => $ownerUserId]);
@endphp

<x-adminlte4::layouts.app :title="__('web/file-hosting.title')">
    <x-adminlte4::card :title="__('web/file-hosting.list_title')">
        <x-slot:actions>
            <a href="{{ route('file-hosting.trash', $ownerQuery) }}" class="btn btn-outline-secondary btn-sm me-1">
                <i class="bi bi-trash me-1"></i> {{ __('web/file-hosting.trash') }}
            </a>
            @can('create file hosting folder')
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#fhNewFolderModal">
                    <i class="bi bi-folder-plus me-1"></i> {{ __('web/file-hosting.new_folder') }}
                </button>
            @endcan
        </x-slot:actions>

        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb mb-0">
                @foreach ($breadcrumb as $crumb)
                    @if ($loop->last)
                        <li class="breadcrumb-item active" aria-current="page">{{ $crumb['name'] }}</li>
                    @else
                        <li class="breadcrumb-item">
                            <a href="{{ route('file-hosting.index', array_filter(array_merge($ownerQuery, ['folder_id' => $crumb['id']]))) }}">
                                {{ $crumb['name'] }}
                            </a>
                        </li>
                    @endif
                @endforeach
            </ol>
        </nav>

        @if ($quota)
            <div class="mb-3 small text-muted">
                {{ __('web/file-hosting.quota_used') }}:
                {{ number_format((int) ($quota['used_bytes'] ?? 0) / 1024 / 1024, 1) }} MB
                @if (! empty($quota['quota_bytes']))
                    / {{ number_format((int) $quota['quota_bytes'] / 1024 / 1024, 1) }} MB
                @endif
            </div>
        @endif

        @can('create file hosting file')
            <div
                class="border rounded p-3 mb-4"
                data-direct-upload
                data-reload-on-complete="1"
                data-folder-id="{{ $currentFolderId }}"
                data-user-id="{{ $ownerUserId }}"
                data-init-url="{{ route('file-hosting.uploads.store') }}"
                data-status-url="{{ url('file-hosting/uploads/__UPLOAD__') }}"
                data-complete-url="{{ url('file-hosting/uploads/__UPLOAD__/complete') }}"
                data-abort-url="{{ url('file-hosting/uploads/__UPLOAD__') }}"
                data-sign-parts-url="{{ url('file-hosting/uploads/__UPLOAD__/sign-parts') }}"
                data-max-files="{{ (int) config('file-hosting.max_files_per_batch', 20) }}"
                data-max-concurrent="{{ (int) config('file-hosting.max_concurrent_uploads', 2) }}"
                data-messages="{{ json_encode([
                    'tooManyFiles' => __('web/file-hosting.upload.too_many'),
                    'waitForUploads' => __('web/file-hosting.upload.wait'),
                    'uploadFailed' => __('web/file-hosting.upload.failed'),
                    'pending' => __('web/file-hosting.upload.pending'),
                    'waiting' => __('web/file-hosting.upload.waiting'),
                    'uploading' => __('web/file-hosting.upload.uploading'),
                    'done' => __('web/file-hosting.upload.done'),
                    'remove' => __('web/file-hosting.upload.remove'),
                ]) }}"
            >
                <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                    <input type="file" class="form-control form-control-sm w-auto" data-direct-upload-input multiple>
                    <button type="button" class="btn btn-outline-primary btn-sm" data-direct-upload-start disabled>
                        {{ __('web/file-hosting.upload.start') }}
                    </button>
                </div>
                <div data-direct-upload-queue></div>
            </div>
            @vite('resources/js/file-hosting/direct-upload.js')
        @endcan

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('web/file-hosting.columns.name') }}</th>
                        <th>{{ __('web/file-hosting.columns.type') }}</th>
                        <th>{{ __('web/file-hosting.columns.size') }}</th>
                        <th class="text-end">{{ __('web/file-hosting.columns.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($folders as $folder)
                        <tr>
                            <td>
                                <a href="{{ route('file-hosting.index', array_filter(array_merge($ownerQuery, ['folder_id' => $folder['id']]))) }}">
                                    <i class="bi bi-folder-fill text-warning me-1"></i>{{ $folder['name'] ?? '' }}
                                </a>
                            </td>
                            <td>{{ __('web/file-hosting.type_folder') }}</td>
                            <td>—</td>
                            <td class="text-end">
                                @can('delete file hosting folder')
                                    <form action="{{ route('file-hosting.folders.destroy', $folder['id']) }}" method="POST" class="d-inline"
                                        onsubmit="return confirm(@json(__('web/file-hosting.confirm_delete_folder')));">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="return_folder_id" value="{{ $currentFolderId }}">
                                        @if ($ownerUserId)
                                            <input type="hidden" name="user_id" value="{{ $ownerUserId }}">
                                        @endif
                                        <button type="submit" class="btn btn-outline-danger btn-sm">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                    @endforelse

                    @foreach ($files as $file)
                        <tr>
                            <td><i class="bi bi-file-earmark me-1"></i>{{ $file['name'] ?? '' }}</td>
                            <td>{{ $file['mime'] ?? __('web/file-hosting.type_file') }}</td>
                            <td>{{ number_format(((int) ($file['size'] ?? 0)) / 1024, 1) }} KB</td>
                            <td class="text-end">
                                @can('delete file hosting file')
                                    <form action="{{ route('file-hosting.files.destroy', $file['id']) }}" method="POST" class="d-inline"
                                        onsubmit="return confirm(@json(__('web/file-hosting.confirm_delete_file')));">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="return_folder_id" value="{{ $currentFolderId }}">
                                        @if ($ownerUserId)
                                            <input type="hidden" name="user_id" value="{{ $ownerUserId }}">
                                        @endif
                                        <button type="submit" class="btn btn-outline-danger btn-sm">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @endforeach

                    @if ($folders->isEmpty() && $files->isEmpty())
                        <tr>
                            <td colspan="4" class="text-muted text-center py-4">{{ __('web/file-hosting.empty') }}</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </x-adminlte4::card>

    @can('create file hosting folder')
        <div class="modal fade" id="fhNewFolderModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('file-hosting.folders.store') }}" class="modal-content">
                    @csrf
                    @if ($currentFolderId)
                        <input type="hidden" name="parent_id" value="{{ $currentFolderId }}">
                    @endif
                    @if ($ownerUserId)
                        <input type="hidden" name="user_id" value="{{ $ownerUserId }}">
                    @endif
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('web/file-hosting.new_folder') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label" for="fh-folder-name">{{ __('web/file-hosting.folder_name') }}</label>
                        <input type="text" name="name" id="fh-folder-name" class="form-control" required maxlength="255">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('web/file-hosting.cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('web/file-hosting.create') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endcan
</x-adminlte4::layouts.app>
