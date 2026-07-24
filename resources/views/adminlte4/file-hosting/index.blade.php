@php
    $breadcrumb = $browse['breadcrumb'] ?? [];
    $folders = collect($browse['folders'] ?? []);
    $files = collect($browse['files'] ?? []);
    $workspaceId = $workspaceId ?? null;
    $workspaces = collect($workspaces ?? []);
    $workspaceActivity = collect($workspaceActivity ?? []);
    $ownerQuery = array_filter([
        'user_id' => $ownerUserId,
        'workspace_id' => $workspaceId,
    ]);
    $visPrivate = \App\Domain\FileHosting\Enums\Visibility::Private->value;
    $visLink = \App\Domain\FileHosting\Enums\Visibility::AnyoneWithLink->value;
    $regions = (array) config('file-hosting.regions');
    $uploadableRegions = array_filter(
        $regions,
        fn ($disk, $key): bool => ! \App\Domain\FileHosting\Support\RegionResolver::isDisabled((string) $key),
        ARRAY_FILTER_USE_BOTH,
    );

    $fhRealtime = app(\App\Domain\FileHosting\Support\FileHostingRealtime::class);
    $fhBroadcast = $fhRealtime->clientConfig();
    $authUser = auth('api')->user();

    $fmConfig = [
        'searchUrl' => route('file-hosting.search'),
        'browseUrl' => route('file-hosting.index', $ownerQuery),
        'quotaUrl' => route('file-hosting.quota', $ownerQuery),
        'trashUrl' => route('file-hosting.trash', $ownerQuery),
        'membersUrl' => $workspaceId
            ? route('file-hosting.workspaces.members.index', $workspaceId)
            : null,
        'activityUrl' => $workspaceId
            ? route('file-hosting.workspaces.activity', $workspaceId)
            : null,
        'fileShowBase' => url('file-hosting/files'),
        'fileRenameBase' => url('file-hosting/files'),
        'folderRenameBase' => url('file-hosting/folders'),
        'bulkFileVisibilityUrl' => route('file-hosting.files.bulk-visibility'),
        'bulkFolderVisibilityUrl' => route('file-hosting.folders.bulk-visibility'),
        'bulkFileDeleteUrl' => route('file-hosting.files.bulk-destroy'),
        'bulkFolderDeleteUrl' => route('file-hosting.folders.bulk-destroy'),
        'ownerUserId' => $ownerUserId,
        'workspaceId' => $workspaceId,
        'currentFolderId' => $currentFolderId,
        'visLink' => $visLink,
        'realtime' => [
            'enabled' => $fhBroadcast !== null,
            'broadcast' => $fhBroadcast,
            'userId' => $authUser?->id,
            'page' => 'index',
        ],
        'quotaLabels' => [
            'used' => __('web/file-hosting.quota_used'),
            'unlimited' => __('web/file-hosting.quota_unlimited'),
            'states' => [
                'free' => __('web/file-hosting.collab.badge_free'),
                'paid' => __('web/file-hosting.collab.badge_paid'),
                'grace' => __('web/file-hosting.collab.badge_grace'),
            ],
        ],
        'messages' => [
            'searchNoResults' => __('web/file-hosting.search_no_results'),
            'rootLabel' => __('web/file-hosting.root_label'),
            'loadFailed' => __('web/file-hosting.load_failed'),
            'linkCopied' => __('web/file-hosting.link_copied'),
            'success' => __('web/file-hosting.ajax_success'),
            'error' => __('web/file-hosting.ajax_error'),
            'networkError' => __('web/file-hosting.network_error'),
            'typeFolder' => __('web/file-hosting.type_folder'),
            'typeFile' => __('web/file-hosting.type_file'),
            'emptyFolder' => __('web/file-hosting.empty'),
        ],
    ];
@endphp

<x-adminlte4::layouts.app :title="__('web/file-hosting.title')">
    <div class="fh-manager" data-file-manager data-config="{{ json_encode($fmConfig) }}">

        {{-- Toolbar --}}
        <div class="fh-toolbar">
            <div class="fh-toolbar__search">
                <i class="bi bi-search fh-toolbar__search-icon"></i>
                <input type="text"
                       class="form-control form-control-sm fh-toolbar__search-input"
                       data-fh-search-input
                       placeholder="{{ __('web/file-hosting.search_placeholder') }}"
                       autocomplete="off">
                <div class="fh-toolbar__search-results" data-fh-search-results></div>
            </div>

            <div class="fh-toolbar__view-toggle">
                <button type="button" class="fh-toolbar__view-btn" data-fh-view-btn="table" title="{{ __('web/file-hosting.view_list') }}">
                    <i class="bi bi-list"></i>
                </button>
                <button type="button" class="fh-toolbar__view-btn" data-fh-view-btn="grid" title="{{ __('web/file-hosting.view_grid') }}">
                    <i class="bi bi-grid"></i>
                </button>
            </div>

            <a href="{{ route('file-hosting.trash', $ownerQuery) }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-trash3 me-1"></i> {{ __('web/file-hosting.trash') }}
            </a>
            @can('create file hosting folder')
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#fhNewFolderModal">
                    <i class="bi bi-folder-plus me-1"></i> {{ __('web/file-hosting.new_folder') }}
                </button>
            @endcan

            @include('adminlte4.file-hosting.partials._workspace-switcher', [
                'planState' => $planState ?? 'free',
                'canCreateWorkspace' => $canCreateWorkspace ?? false,
                'workspaceId' => $workspaceId,
                'currentWorkspace' => $currentWorkspace ?? null,
                'workspaces' => $workspaces,
                'ownerUserId' => $ownerUserId,
                'ownedWorkspaceUsed' => $ownedWorkspaceUsed ?? 0,
                'ownedWorkspaceLimit' => $ownedWorkspaceLimit ?? 0,
            ])
        </div>

        @include('adminlte4.file-hosting.partials._team-offcanvas', [
            'workspaceId' => $workspaceId,
            'currentWorkspace' => $currentWorkspace ?? null,
            'workspaceMembers' => $workspaceMembers ?? [],
            'workspaceActivity' => $workspaceActivity ?? [],
        ])

        {{-- Breadcrumb --}}
        <nav class="fh-breadcrumb" aria-label="breadcrumb">
            <a href="{{ route('file-hosting.index', $ownerQuery) }}" class="fh-breadcrumb__item {{ empty($breadcrumb) ? 'is-active' : '' }}">
                <i class="bi bi-folder2-open fh-breadcrumb__root-icon me-1"></i>
                @if($workspaceId)
                    {{ $currentWorkspace['name'] ?? __('web/file-hosting.workspace_team') }}
                @else
                    {{ __('web/file-hosting.root_label') }}
                @endif
            </a>
            @foreach ($breadcrumb as $crumb)
                <i class="bi bi-chevron-right fh-breadcrumb__sep"></i>
                @if ($loop->last)
                    <span class="fh-breadcrumb__item is-active">{{ $crumb['name'] }}</span>
                @else
                    <a href="{{ route('file-hosting.index', array_filter(array_merge($ownerQuery, ['folder_id' => $crumb['id']]))) }}"
                       class="fh-breadcrumb__item">
                        {{ $crumb['name'] }}
                    </a>
                @endif
            @endforeach
        </nav>

        {{-- Quota --}}
        <div data-fh-quota>
            @include('adminlte4.file-hosting.partials._quota-bar', ['quota' => $quota])
        </div>

        {{-- Upload dropzone --}}
        @can('create file hosting file')
            <div
                class="fh-dropzone"
                data-fh-dropzone
                data-direct-upload
                data-reload-on-complete="1"
                data-folder-id="{{ $currentFolderId }}"
                data-user-id="{{ $ownerUserId }}"
                data-workspace-id="{{ $workspaceId }}"
                data-init-url="{{ route('file-hosting.uploads.store') }}"
                data-status-url="{{ url('file-hosting/uploads/__UPLOAD__') }}"
                data-complete-url="{{ url('file-hosting/uploads/__UPLOAD__/complete') }}"
                data-abort-url="{{ url('file-hosting/uploads/__UPLOAD__') }}"
                data-sign-parts-url="{{ url('file-hosting/uploads/__UPLOAD__/sign-parts') }}"
                data-max-files="{{ (int) config('file-hosting.max_files_per_batch', 20) }}"
                data-max-concurrent="{{ (int) config('file-hosting.max_concurrent_uploads', 2) }}"
                role="button"
                tabindex="0"
                aria-label="{{ __('web/file-hosting.upload_here') }}"
                data-messages="{{ json_encode([
                    'tooManyFiles' => __('web/file-hosting.upload.too_many'),
                    'waitForUploads' => __('web/file-hosting.upload.wait'),
                    'uploadFailed' => __('web/file-hosting.upload.failed'),
                    'pending' => __('web/file-hosting.upload.pending'),
                    'waiting' => __('web/file-hosting.upload.waiting'),
                    'uploading' => __('web/file-hosting.upload.uploading'),
                    'completing' => __('web/file-hosting.upload.completing'),
                    'done' => __('web/file-hosting.upload.done'),
                    'remove' => __('web/file-hosting.upload.remove'),
                ]) }}"
            >
                <div class="fh-dropzone__icon">
                    <i class="bi bi-cloud-arrow-up" aria-hidden="true"></i>
                </div>
                <div class="fh-dropzone__text">{{ __('web/file-hosting.upload_here') }}</div>
                <div class="fh-dropzone__hint">{{ __('web/file-hosting.drag_drop_hint') }}</div>
                <div class="fh-dropzone__actions">
                    <input type="file" class="d-none" data-direct-upload-input multiple>
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="this.closest('.fh-dropzone').querySelector('[data-direct-upload-input]').click()">
                        <i class="bi bi-folder2-open me-1" aria-hidden="true"></i>{{ __('web/file-hosting.upload.browse') }}
                    </button>
                    @if (count($uploadableRegions) > 1)
                        <select class="form-select form-select-sm fh-dropzone__region" data-direct-upload-region aria-label="{{ __('web/file-hosting.upload.region') }}">
                            @foreach ($uploadableRegions as $key => $diskName)
                                <option value="{{ $key }}">{{ __('web/file-hosting.regions.'.$key) }}</option>
                            @endforeach
                        </select>
                    @endif
                    <button type="button" class="btn btn-primary btn-sm" data-direct-upload-start disabled>
                        <i class="bi bi-play-fill me-1"></i>{{ __('web/file-hosting.upload.start') }}
                    </button>
                </div>
                <div class="fh-upload-queue" data-direct-upload-queue></div>
            </div>
            @vite('resources/js/file-hosting/direct-upload.js')
        @endcan

        {{-- Bulk action bar --}}
        <div class="fh-bulk-bar" data-fh-bulk-bar style="display:none;">
            <span class="fh-bulk-bar__count">
                <strong data-fh-bulk-count>0</strong> {{ __('web/file-hosting.bulk.selected') }}
            </span>
            <div class="fh-bulk-bar__actions">
                @can('edit file hosting file')
                    <button type="button" class="btn btn-outline-success btn-sm" data-fh-bulk-action="share"
                            data-fh-confirm="{{ __('web/file-hosting.bulk.confirm_share') }}">
                        <i class="bi bi-share me-1"></i>{{ __('web/file-hosting.bulk.share') }}
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-fh-bulk-action="unshare"
                            data-fh-confirm="{{ __('web/file-hosting.bulk.confirm_unshare') }}">
                        <i class="bi bi-shield-lock me-1"></i>{{ __('web/file-hosting.bulk.unshare') }}
                    </button>
                @endcan
                @can('delete file hosting file')
                    <button type="button" class="btn btn-outline-danger btn-sm" data-fh-bulk-action="delete"
                            data-fh-confirm="{{ __('web/file-hosting.bulk.confirm_delete') }}">
                        <i class="bi bi-trash me-1"></i>{{ __('web/file-hosting.bulk.delete') }}
                    </button>
                @endcan
            </div>
        </div>

        {{-- Table view --}}
        <div data-fh-view="table">
            <div class="table-responsive">
                <table class="table fh-table">
                    <thead>
                        <tr>
                            <th class="fh-table__select-cell">
                                <input type="checkbox" class="form-check-input" data-fh-select-all aria-label="{{ __('web/file-hosting.bulk.select_all') }}">
                            </th>
                            <th class="fh-table__icon-cell"></th>
                            <th>{{ __('web/file-hosting.columns.name') }}</th>
                            <th>{{ __('web/file-hosting.columns.type') }}</th>
                            <th>{{ __('web/file-hosting.columns.size') }}</th>
                            <th>{{ __('web/file-hosting.columns.region') }}</th>
                            <th>{{ __('web/file-hosting.columns.visibility') }}</th>
                            <th class="text-end">{{ __('web/file-hosting.columns.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($folders as $folder)
                            @php $folderVis = $folder['visibility'] ?? $visPrivate; @endphp
                            <tr data-fh-row data-fh-entity="folder" data-fh-id="{{ $folder['id'] }}">
                        <td class="fh-table__select-cell">
                            <input type="checkbox" class="form-check-input" data-fh-select value="folder:{{ $folder['id'] }}" aria-label="{{ __('web/file-hosting.bulk.select_item') }}">
                        </td>
                        <td class="fh-table__icon-cell">
                            <a href="{{ route('file-hosting.index', array_filter(array_merge($ownerQuery, ['folder_id' => $folder['id']]))) }}">
                                @include('adminlte4.file-hosting.partials._file-icon', ['mime' => null, 'isFolder' => true])
                            </a>
                        </td>
                        <td>
                            <a href="{{ route('file-hosting.index', array_filter(array_merge($ownerQuery, ['folder_id' => $folder['id']]))) }}"
                               class="fh-table__name">
                                <span class="fh-table__name-text" data-fh-name>{{ $folder['name'] ?? '' }}</span>
                            </a>
                        </td>
                        <td class="fh-table__type">{{ __('web/file-hosting.type_folder') }}</td>
                        <td class="fh-table__size">—</td>
                        <td>@include('adminlte4.file-hosting.partials._visibility-badge', ['visibility' => $folderVis])</td>
                        <td class="fh-table__actions">
                            @can('edit file hosting folder')
                                <button type="button" class="fh-table__action-btn" title="{{ __('web/file-hosting.rename') }}"
                                        data-bs-toggle="modal" data-bs-target="#fhRenameFolderModal"
                                        data-id="{{ $folder['id'] }}" data-name="{{ $folder['name'] ?? '' }}">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button type="button" class="fh-table__action-btn" title="{{ __('web/file-hosting.move') }}"
                                        data-bs-toggle="modal" data-bs-target="#fhMoveFolderModal"
                                        data-id="{{ $folder['id'] }}">
                                    <i class="bi bi-folder-symlink"></i>
                                </button>
                                <button type="button" class="fh-table__action-btn" title="{{ __('web/file-hosting.share') }}"
                                        data-bs-toggle="modal" data-bs-target="#fhShareFolderModal"
                                        data-id="{{ $folder['id'] }}"
                                        data-visibility="{{ $folderVis }}"
                                        data-share-url="{{ $folder['share_url'] ?? '' }}">
                                    <i class="bi bi-share"></i>
                                </button>
                            @endcan
                            @can('delete file hosting folder')
                                <button type="button" class="fh-table__action-btn is-danger" title="{{ __('web/file-hosting.delete') }}"
                                        data-fh-ajax-action="delete-folder"
                                        data-fh-action-url="{{ route('file-hosting.folders.destroy', $folder['id']) }}"
                                        data-fh-method="DELETE"
                                        data-fh-confirm="{{ __('web/file-hosting.confirm_delete_folder') }}"
                                        data-fh-redirect="{{ route('file-hosting.index', array_filter(array_merge($ownerQuery, ['folder_id' => $currentFolderId]))) }}">
                                    <i class="bi bi-trash"></i>
                                </button>
                            @endcan
                        </td>
                    </tr>
                @empty
                @endforelse

                        @foreach ($files as $file)
                            @php $fileVis = $file['visibility'] ?? $visPrivate; @endphp
                            <tr data-fh-row data-fh-entity="file" data-fh-id="{{ $file['id'] }}">
                        <td class="fh-table__select-cell">
                            <input type="checkbox" class="form-check-input" data-fh-select value="file:{{ $file['id'] }}" aria-label="{{ __('web/file-hosting.bulk.select_item') }}">
                        </td>
                        <td class="fh-table__icon-cell">
                            <a href="{{ route('file-hosting.files.show', array_filter(array_merge($ownerQuery, ['file' => $file['id']]))) }}">
                                @include('adminlte4.file-hosting.partials._file-icon', ['mime' => $file['mime'] ?? null, 'isFolder' => false])
                            </a>
                        </td>
                        <td>
                            <a href="{{ route('file-hosting.files.show', array_filter(array_merge($ownerQuery, ['file' => $file['id']]))) }}"
                               class="fh-table__name" title="{{ $file['name'] ?? '' }}">
                                <span class="fh-table__name-text" data-fh-name>{{ $file['name'] ?? '' }}</span>
                            </a>
                        </td>
                        <td class="fh-table__type">{{ $file['mime'] ?? __('web/file-hosting.type_file') }}</td>
                        <td class="fh-table__size">{{ $file['size_human'] ?? '—' }}</td>
                        <td class="fh-table__region">
                            @php
                                $regionKey = $file['region']
                                    ?? \App\Domain\FileHosting\Support\RegionResolver::keyForDisk((string) ($file['disk'] ?? ''));
                            @endphp
                            @if ($regionKey)
                                <span class="badge text-bg-secondary fh-region-badge">{{ __('web/file-hosting.regions.'.$regionKey) }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>@include('adminlte4.file-hosting.partials._visibility-badge', ['visibility' => $fileVis])</td>
                        <td class="fh-table__actions">
                            <a href="{{ route('file-hosting.files.show', array_filter(array_merge($ownerQuery, ['file' => $file['id']]))) }}"
                               class="fh-table__action-btn" title="{{ __('web/file-hosting.view') }}">
                                <i class="bi bi-eye"></i>
                            </a>
                            @can('edit file hosting file')
                                <button type="button" class="fh-table__action-btn" title="{{ __('web/file-hosting.rename') }}"
                                        data-bs-toggle="modal" data-bs-target="#fhRenameFileModal"
                                        data-id="{{ $file['id'] }}" data-name="{{ $file['name'] ?? '' }}">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button type="button" class="fh-table__action-btn" title="{{ __('web/file-hosting.move') }}"
                                        data-bs-toggle="modal" data-bs-target="#fhMoveFileModal"
                                        data-id="{{ $file['id'] }}">
                                    <i class="bi bi-folder-symlink"></i>
                                </button>
                                <button type="button" class="fh-table__action-btn" title="{{ __('web/file-hosting.share') }}"
                                        data-bs-toggle="modal" data-bs-target="#fhShareFileModal"
                                        data-id="{{ $file['id'] }}"
                                        data-visibility="{{ $fileVis }}"
                                        data-share-url="{{ $file['share_url'] ?? '' }}">
                                    <i class="bi bi-share"></i>
                                </button>
                            @endcan
                            @can('delete file hosting file')
                                <button type="button" class="fh-table__action-btn is-danger" title="{{ __('web/file-hosting.delete') }}"
                                        data-fh-ajax-action="delete-file"
                                        data-fh-action-url="{{ route('file-hosting.files.destroy', $file['id']) }}"
                                        data-fh-method="DELETE"
                                        data-fh-confirm="{{ __('web/file-hosting.confirm_delete_file') }}"
                                        data-fh-redirect="{{ route('file-hosting.index', array_filter(array_merge($ownerQuery, ['folder_id' => $currentFolderId]))) }}">
                                    <i class="bi bi-trash"></i>
                                </button>
                            @endcan
                        </td>
                    </tr>
                @endforeach

                        @if ($folders->isEmpty() && $files->isEmpty())
                            <tr>
                                <td colspan="8">
                                    <div class="fh-empty">
                                        <i class="bi bi-folder2-open fh-empty__icon"></i>
                                        <div class="fh-empty__text">{{ __('web/file-hosting.empty') }}</div>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Grid view --}}
        <div data-fh-view="grid" class="d-none">
            @if ($folders->isEmpty() && $files->isEmpty())
                <div class="fh-empty">
                    <i class="bi bi-folder2-open fh-empty__icon"></i>
                    <div class="fh-empty__text">{{ __('web/file-hosting.empty') }}</div>
                </div>
            @else
                <div class="fh-grid">
                    @foreach ($folders as $folder)
                        @php $folderVis = $folder['visibility'] ?? $visPrivate; @endphp
                        <div class="fh-file-card" data-fh-row data-fh-entity="folder" data-fh-id="{{ $folder['id'] }}">
                            <input type="checkbox" class="form-check-input fh-file-card__select" data-fh-select value="folder:{{ $folder['id'] }}" aria-label="{{ __('web/file-hosting.bulk.select_item') }}">
                            <div class="fh-file-card__badge">
                                @include('adminlte4.file-hosting.partials._visibility-badge', ['visibility' => $folderVis])
                            </div>
                            <div class="fh-file-card__icon">
                                <a href="{{ route('file-hosting.index', array_filter(array_merge($ownerQuery, ['folder_id' => $folder['id']]))) }}">
                                    @include('adminlte4.file-hosting.partials._file-icon', ['mime' => null, 'isFolder' => true])
                                </a>
                            </div>
                            <a href="{{ route('file-hosting.index', array_filter(array_merge($ownerQuery, ['folder_id' => $folder['id']]))) }}"
                               class="fh-file-card__name" title="{{ $folder['name'] ?? '' }}" data-fh-name>
                                {{ $folder['name'] ?? '' }}
                            </a>
                            <div class="fh-file-card__meta">{{ __('web/file-hosting.type_folder') }}</div>
                            <div class="fh-file-card__actions">
                                @can('edit file hosting folder')
                                    <button type="button" class="fh-table__action-btn" title="{{ __('web/file-hosting.rename') }}"
                                            data-bs-toggle="modal" data-bs-target="#fhRenameFolderModal"
                                            data-id="{{ $folder['id'] }}" data-name="{{ $folder['name'] ?? '' }}">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                @endcan
                                @can('delete file hosting folder')
                                    <button type="button" class="fh-table__action-btn is-danger" title="{{ __('web/file-hosting.delete') }}"
                                            data-fh-ajax-action="delete-folder"
                                            data-fh-action-url="{{ route('file-hosting.folders.destroy', $folder['id']) }}"
                                            data-fh-method="DELETE"
                                            data-fh-confirm="{{ __('web/file-hosting.confirm_delete_folder') }}"
                                            data-fh-redirect="{{ route('file-hosting.index', array_filter(array_merge($ownerQuery, ['folder_id' => $currentFolderId]))) }}">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                @endcan
                            </div>
                        </div>
                    @endforeach

                    @foreach ($files as $file)
                        @php $fileVis = $file['visibility'] ?? $visPrivate; @endphp
                        <div class="fh-file-card" data-fh-row data-fh-entity="file" data-fh-id="{{ $file['id'] }}">
                            <input type="checkbox" class="form-check-input fh-file-card__select" data-fh-select value="file:{{ $file['id'] }}" aria-label="{{ __('web/file-hosting.bulk.select_item') }}">
                            <div class="fh-file-card__badge">
                                @include('adminlte4.file-hosting.partials._visibility-badge', ['visibility' => $fileVis])
                            </div>
                            <div class="fh-file-card__icon">
                                <a href="{{ route('file-hosting.files.show', array_filter(array_merge($ownerQuery, ['file' => $file['id']]))) }}">
                                    @include('adminlte4.file-hosting.partials._file-icon', ['mime' => $file['mime'] ?? null, 'isFolder' => false])
                                </a>
                            </div>
                            <a href="{{ route('file-hosting.files.show', array_filter(array_merge($ownerQuery, ['file' => $file['id']]))) }}"
                               class="fh-file-card__name" title="{{ $file['name'] ?? '' }}" data-fh-name>
                                {{ $file['name'] ?? '' }}
                            </a>
                            <div class="fh-file-card__meta">
                                @php
                                    $gridRegionKey = $file['region']
                                        ?? \App\Domain\FileHosting\Support\RegionResolver::keyForDisk((string) ($file['disk'] ?? ''));
                                @endphp
                                {{ $file['size_human'] ?? '—' }}
                                @if ($gridRegionKey) · <span class="fh-file-card__region">{{ __('web/file-hosting.regions.'.$gridRegionKey) }}</span>@endif
                            </div>
                            <div class="fh-file-card__actions">
                                <a href="{{ route('file-hosting.files.show', array_filter(array_merge($ownerQuery, ['file' => $file['id']]))) }}"
                                   class="fh-table__action-btn" title="{{ __('web/file-hosting.view') }}">
                                    <i class="bi bi-eye"></i>
                                </a>
                                @can('edit file hosting file')
                                    <button type="button" class="fh-table__action-btn" title="{{ __('web/file-hosting.rename') }}"
                                            data-bs-toggle="modal" data-bs-target="#fhRenameFileModal"
                                            data-id="{{ $file['id'] }}" data-name="{{ $file['name'] ?? '' }}">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                @endcan
                                @can('delete file hosting file')
                                    <button type="button" class="fh-table__action-btn is-danger" title="{{ __('web/file-hosting.delete') }}"
                                            data-fh-ajax-action="delete-file"
                                            data-fh-action-url="{{ route('file-hosting.files.destroy', $file['id']) }}"
                                            data-fh-method="DELETE"
                                            data-fh-confirm="{{ __('web/file-hosting.confirm_delete_file') }}"
                                            data-fh-redirect="{{ route('file-hosting.index', array_filter(array_merge($ownerQuery, ['folder_id' => $currentFolderId]))) }}">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                @endcan
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- New Folder Modal --}}
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
                        @if($workspaceId)
                            <input type="hidden" name="workspace_id" value="{{ $workspaceId }}">
                        @endif
                    @endif
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-folder-plus me-1"></i>{{ __('web/file-hosting.new_folder') }}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label" for="fh-folder-name">{{ __('web/file-hosting.folder_name') }}</label>
                        <input type="text" name="name" id="fh-folder-name" class="form-control" required maxlength="255" autofocus>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('web/file-hosting.cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('web/file-hosting.create') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endcan

    {{-- Rename File Modal --}}
    @can('edit file hosting file')
        <div class="modal fade" id="fhRenameFileModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="#" class="modal-content" id="fhRenameFileForm">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="return_folder_id" value="{{ $currentFolderId }}">
                    @if ($ownerUserId)
                        <input type="hidden" name="user_id" value="{{ $ownerUserId }}">
                        @if($workspaceId)
                            <input type="hidden" name="workspace_id" value="{{ $workspaceId }}">
                        @endif
                    @endif
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-pencil me-1"></i>{{ __('web/file-hosting.rename') }}
                        </h5>
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

        {{-- Share File Modal --}}
        <div class="modal fade" id="fhShareFileModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="#" class="modal-content" id="fhShareFileForm">
                    @csrf
                    @method('PATCH')
                    @if ($ownerUserId)
                        <input type="hidden" name="user_id" value="{{ $ownerUserId }}">
                        @if($workspaceId)
                            <input type="hidden" name="workspace_id" value="{{ $workspaceId }}">
                        @endif
                    @endif
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-share me-1"></i>{{ __('web/file-hosting.share') }}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label" for="fh-share-file-vis">{{ __('web/file-hosting.visibility.label') }}</label>
                        <select name="visibility" id="fh-share-file-vis" class="form-select mb-3">
                            <option value="{{ $visPrivate }}">{{ __('web/file-hosting.visibility.private') }}</option>
                            <option value="{{ $visLink }}">{{ __('web/file-hosting.visibility.anyone_with_link') }}</option>
                        </select>
                        <div id="fh-share-file-link-wrap" class="d-none">
                            <div class="fh-share-box">
                                <div class="fh-share-box__label">{{ __('web/file-hosting.share_link') }}</div>
                                <div class="input-group input-group-sm">
                                    <input type="text" class="form-control" id="fh-share-file-link" readonly>
                                    <button type="button" class="btn btn-outline-primary" data-fh-copy="fh-share-file-link">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                </div>
                                <div class="form-text mt-2">{{ __('web/file-hosting.visibility.hint_link') }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('web/file-hosting.cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('web/file-hosting.save') }}</button>
                    </div>
                </form>
            </div>
        </div>

        @include('adminlte4.file-hosting.partials._move-modal', [
            'modalId' => 'fhMoveFileModal',
            'formId' => 'fhMoveFileForm',
            'title' => __('web/file-hosting.move_title'),
            'ownerUserId' => $ownerUserId,
        ])
    @endcan

    {{-- Rename / Share / Move Folder Modals --}}
    @can('edit file hosting folder')
        <div class="modal fade" id="fhRenameFolderModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="#" class="modal-content" id="fhRenameFolderForm">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="return_folder_id" value="{{ $currentFolderId }}">
                    @if ($ownerUserId)
                        <input type="hidden" name="user_id" value="{{ $ownerUserId }}">
                        @if($workspaceId)
                            <input type="hidden" name="workspace_id" value="{{ $workspaceId }}">
                        @endif
                    @endif
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-pencil me-1"></i>{{ __('web/file-hosting.rename') }}
                        </h5>
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
                        @if($workspaceId)
                            <input type="hidden" name="workspace_id" value="{{ $workspaceId }}">
                        @endif
                    @endif
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-share me-1"></i>{{ __('web/file-hosting.share') }}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label" for="fh-share-folder-vis">{{ __('web/file-hosting.visibility.label') }}</label>
                        <select name="visibility" id="fh-share-folder-vis" class="form-select mb-3">
                            <option value="{{ $visPrivate }}">{{ __('web/file-hosting.visibility.private') }}</option>
                            <option value="{{ $visLink }}">{{ __('web/file-hosting.visibility.anyone_with_link') }}</option>
                        </select>
                        <div id="fh-share-folder-link-wrap" class="d-none">
                            <div class="fh-share-box">
                                <div class="fh-share-box__label">{{ __('web/file-hosting.share_link') }}</div>
                                <div class="input-group input-group-sm">
                                    <input type="text" class="form-control" id="fh-share-folder-link" readonly>
                                    <button type="button" class="btn btn-outline-primary" data-fh-copy="fh-share-folder-link">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                </div>
                                <div class="form-text mt-2">{{ __('web/file-hosting.visibility.hint_folder') }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('web/file-hosting.cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('web/file-hosting.save') }}</button>
                    </div>
                </form>
            </div>
        </div>

        @include('adminlte4.file-hosting.partials._move-modal', [
            'modalId' => 'fhMoveFolderModal',
            'formId' => 'fhMoveFolderForm',
            'title' => __('web/file-hosting.move_title'),
            'ownerUserId' => $ownerUserId,
        ])
    @endcan

    @vite(['resources/css/file-hosting/file-hosting.css', 'resources/js/file-hosting/file-manager.js'])
</x-adminlte4::layouts.app>
