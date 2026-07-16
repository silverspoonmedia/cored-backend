@php
    $breadcrumb = $browse['breadcrumb'] ?? [];
    $folders = collect($browse['folders'] ?? []);
    $files = collect($browse['files'] ?? []);
    $ownerQuery = array_filter(['user_id' => $ownerUserId]);
    $visPrivate = \App\Domain\FileHosting\Enums\Visibility::Private->value;
    $visLink = \App\Domain\FileHosting\Enums\Visibility::AnyoneWithLink->value;
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
                        <th>{{ __('web/file-hosting.columns.visibility') }}</th>
                        <th class="text-end">{{ __('web/file-hosting.columns.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($folders as $folder)
                        @php $folderVis = $folder['visibility'] ?? $visPrivate; @endphp
                        <tr>
                            <td>
                                <a href="{{ route('file-hosting.index', array_filter(array_merge($ownerQuery, ['folder_id' => $folder['id']]))) }}">
                                    <i class="bi bi-folder-fill text-warning me-1"></i>{{ $folder['name'] ?? '' }}
                                </a>
                            </td>
                            <td>{{ __('web/file-hosting.type_folder') }}</td>
                            <td>—</td>
                            <td>
                                @if ($folderVis === $visLink)
                                    <span class="badge text-bg-success">{{ __('web/file-hosting.visibility.anyone_with_link') }}</span>
                                @else
                                    <span class="badge text-bg-secondary">{{ __('web/file-hosting.visibility.private') }}</span>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                @can('edit file hosting folder')
                                    <button type="button" class="btn btn-outline-secondary btn-sm me-1"
                                            title="{{ __('web/file-hosting.rename') }}"
                                            data-bs-toggle="modal" data-bs-target="#fhRenameFolderModal"
                                            data-id="{{ $folder['id'] }}" data-name="{{ $folder['name'] ?? '' }}">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm me-1"
                                            title="{{ __('web/file-hosting.share') }}"
                                            data-bs-toggle="modal" data-bs-target="#fhShareFolderModal"
                                            data-id="{{ $folder['id'] }}"
                                            data-visibility="{{ $folderVis }}"
                                            data-share-url="{{ $folder['share_url'] ?? '' }}">
                                        <i class="bi bi-share"></i>
                                    </button>
                                @endcan
                                @can('delete file hosting folder')
                                    <form action="{{ route('file-hosting.folders.destroy', $folder['id']) }}" method="POST" class="d-inline"
                                        onsubmit="return confirm(@json(__('web/file-hosting.confirm_delete_folder')));">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="return_folder_id" value="{{ $currentFolderId }}">
                                        @if ($ownerUserId)
                                            <input type="hidden" name="user_id" value="{{ $ownerUserId }}">
                                        @endif
                                        <button type="submit" class="btn btn-outline-danger btn-sm" title="{{ __('web/file-hosting.delete') }}">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                    @endforelse

                    @foreach ($files as $file)
                        @php $fileVis = $file['visibility'] ?? $visPrivate; @endphp
                        <tr>
                            <td>
                                <a href="{{ route('file-hosting.files.show', array_filter(array_merge($ownerQuery, ['file' => $file['id']]))) }}">
                                    <i class="bi bi-file-earmark me-1"></i>{{ $file['name'] ?? '' }}
                                </a>
                            </td>
                            <td>{{ $file['mime'] ?? __('web/file-hosting.type_file') }}</td>
                            <td>{{ number_format(((int) ($file['size'] ?? 0)) / 1024, 1) }} KB</td>
                            <td>
                                @if ($fileVis === $visLink)
                                    <span class="badge text-bg-success">{{ __('web/file-hosting.visibility.anyone_with_link') }}</span>
                                @else
                                    <span class="badge text-bg-secondary">{{ __('web/file-hosting.visibility.private') }}</span>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('file-hosting.files.show', array_filter(array_merge($ownerQuery, ['file' => $file['id']]))) }}"
                                   class="btn btn-outline-secondary btn-sm me-1" title="{{ __('web/file-hosting.view') }}">
                                    <i class="bi bi-eye"></i>
                                </a>
                                @can('edit file hosting file')
                                    <button type="button" class="btn btn-outline-secondary btn-sm me-1"
                                            title="{{ __('web/file-hosting.rename') }}"
                                            data-bs-toggle="modal" data-bs-target="#fhRenameFileModal"
                                            data-id="{{ $file['id'] }}" data-name="{{ $file['name'] ?? '' }}">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm me-1"
                                            title="{{ __('web/file-hosting.share') }}"
                                            data-bs-toggle="modal" data-bs-target="#fhShareFileModal"
                                            data-id="{{ $file['id'] }}"
                                            data-visibility="{{ $fileVis }}"
                                            data-share-url="{{ $file['share_url'] ?? '' }}">
                                        <i class="bi bi-share"></i>
                                    </button>
                                @endcan
                                @can('delete file hosting file')
                                    <form action="{{ route('file-hosting.files.destroy', $file['id']) }}" method="POST" class="d-inline"
                                        onsubmit="return confirm(@json(__('web/file-hosting.confirm_delete_file')));">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="return_folder_id" value="{{ $currentFolderId }}">
                                        @if ($ownerUserId)
                                            <input type="hidden" name="user_id" value="{{ $ownerUserId }}">
                                        @endif
                                        <button type="submit" class="btn btn-outline-danger btn-sm" title="{{ __('web/file-hosting.delete') }}">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @endforeach

                    @if ($folders->isEmpty() && $files->isEmpty())
                        <tr>
                            <td colspan="5" class="text-muted text-center py-4">{{ __('web/file-hosting.empty') }}</td>
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

    @can('edit file hosting file')
        <div class="modal fade" id="fhRenameFileModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="#" class="modal-content" id="fhRenameFileForm">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="return_folder_id" value="{{ $currentFolderId }}">
                    @if ($ownerUserId)
                        <input type="hidden" name="user_id" value="{{ $ownerUserId }}">
                    @endif
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('web/file-hosting.rename') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label" for="fh-rename-file-name">{{ __('web/file-hosting.file_name') }}</label>
                        <input type="text" name="name" id="fh-rename-file-name" class="form-control" required maxlength="255">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('web/file-hosting.cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('web/file-hosting.save_rename') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="fhShareFileModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="#" class="modal-content" id="fhShareFileForm">
                    @csrf
                    @method('PATCH')
                    @if ($ownerUserId)
                        <input type="hidden" name="user_id" value="{{ $ownerUserId }}">
                    @endif
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('web/file-hosting.share') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label" for="fh-share-file-vis">{{ __('web/file-hosting.visibility.label') }}</label>
                        <select name="visibility" id="fh-share-file-vis" class="form-select mb-3">
                            <option value="{{ $visPrivate }}">{{ __('web/file-hosting.visibility.private') }}</option>
                            <option value="{{ $visLink }}">{{ __('web/file-hosting.visibility.anyone_with_link') }}</option>
                        </select>
                        <div id="fh-share-file-link-wrap" class="d-none">
                            <label class="form-label">{{ __('web/file-hosting.share_link') }}</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="fh-share-file-link" readonly>
                                <button type="button" class="btn btn-outline-secondary" id="fh-copy-file-link">{{ __('web/file-hosting.copy_link') }}</button>
                            </div>
                            <div class="form-text">{{ __('web/file-hosting.visibility.hint_link') }}</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('web/file-hosting.cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('web/file-hosting.save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endcan

    @can('edit file hosting folder')
        <div class="modal fade" id="fhRenameFolderModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="#" class="modal-content" id="fhRenameFolderForm">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="return_folder_id" value="{{ $currentFolderId }}">
                    @if ($ownerUserId)
                        <input type="hidden" name="user_id" value="{{ $ownerUserId }}">
                    @endif
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('web/file-hosting.rename') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label" for="fh-rename-folder-name">{{ __('web/file-hosting.folder_name') }}</label>
                        <input type="text" name="name" id="fh-rename-folder-name" class="form-control" required maxlength="255">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('web/file-hosting.cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('web/file-hosting.save_rename') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="fhShareFolderModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="#" class="modal-content" id="fhShareFolderForm">
                    @csrf
                    @method('PATCH')
                    @if ($ownerUserId)
                        <input type="hidden" name="user_id" value="{{ $ownerUserId }}">
                    @endif
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('web/file-hosting.share') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label" for="fh-share-folder-vis">{{ __('web/file-hosting.visibility.label') }}</label>
                        <select name="visibility" id="fh-share-folder-vis" class="form-select mb-3">
                            <option value="{{ $visPrivate }}">{{ __('web/file-hosting.visibility.private') }}</option>
                            <option value="{{ $visLink }}">{{ __('web/file-hosting.visibility.anyone_with_link') }}</option>
                        </select>
                        <div id="fh-share-folder-link-wrap" class="d-none">
                            <label class="form-label">{{ __('web/file-hosting.share_link') }}</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="fh-share-folder-link" readonly>
                                <button type="button" class="btn btn-outline-secondary" id="fh-copy-folder-link">{{ __('web/file-hosting.copy_link') }}</button>
                            </div>
                            <div class="form-text">{{ __('web/file-hosting.visibility.hint_folder') }}</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('web/file-hosting.cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('web/file-hosting.save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endcan

    <script>
        (function () {
            const visLink = @json($visLink);
            const fileRenameBase = @json(url('file-hosting/files'));
            const folderRenameBase = @json(url('file-hosting/folders'));

            function bindRename(modalId, formId, nameInputId, baseUrl) {
                const modal = document.getElementById(modalId);
                if (!modal) return;
                modal.addEventListener('show.bs.modal', function (e) {
                    const btn = e.relatedTarget;
                    if (!btn) return;
                    const id = btn.getAttribute('data-id');
                    const name = btn.getAttribute('data-name') || '';
                    document.getElementById(formId).action = baseUrl + '/' + id + '/rename';
                    document.getElementById(nameInputId).value = name;
                });
            }

            function bindShare(modalId, formId, visSelectId, linkWrapId, linkInputId, copyBtnId, baseUrl) {
                const modal = document.getElementById(modalId);
                if (!modal) return;
                const visSelect = document.getElementById(visSelectId);
                const linkWrap = document.getElementById(linkWrapId);
                const linkInput = document.getElementById(linkInputId);

                function syncLink() {
                    if (visSelect.value === visLink && linkInput.value) {
                        linkWrap.classList.remove('d-none');
                    } else if (visSelect.value === visLink) {
                        linkWrap.classList.remove('d-none');
                    } else {
                        linkWrap.classList.add('d-none');
                    }
                }

                modal.addEventListener('show.bs.modal', function (e) {
                    const btn = e.relatedTarget;
                    if (!btn) return;
                    const id = btn.getAttribute('data-id');
                    const vis = btn.getAttribute('data-visibility') || 'private';
                    const shareUrl = btn.getAttribute('data-share-url') || '';
                    document.getElementById(formId).action = baseUrl + '/' + id + '/visibility';
                    visSelect.value = vis;
                    linkInput.value = shareUrl;
                    syncLink();
                });
                visSelect.addEventListener('change', syncLink);

                const copyBtn = document.getElementById(copyBtnId);
                if (copyBtn) {
                    copyBtn.addEventListener('click', function () {
                        if (!linkInput.value) return;
                        navigator.clipboard.writeText(linkInput.value).catch(function () {
                            linkInput.select();
                            document.execCommand('copy');
                        });
                    });
                }
            }

            bindRename('fhRenameFileModal', 'fhRenameFileForm', 'fh-rename-file-name', fileRenameBase);
            bindRename('fhRenameFolderModal', 'fhRenameFolderForm', 'fh-rename-folder-name', folderRenameBase);
            bindShare('fhShareFileModal', 'fhShareFileForm', 'fh-share-file-vis', 'fh-share-file-link-wrap', 'fh-share-file-link', 'fh-copy-file-link', fileRenameBase);
            bindShare('fhShareFolderModal', 'fhShareFolderForm', 'fh-share-folder-vis', 'fh-share-folder-link-wrap', 'fh-share-folder-link', 'fh-copy-folder-link', folderRenameBase);
        })();
    </script>
</x-adminlte4::layouts.app>
