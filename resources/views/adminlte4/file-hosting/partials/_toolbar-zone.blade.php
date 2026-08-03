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

            <div class="fh-toolbar__view-toggle btn-group btn-group-sm" role="group" aria-label="{{ __('web/file-hosting.view_toggle') }}">
                <button type="button" class="fh-toolbar__view-btn btn btn-outline-secondary" data-fh-view-btn="table"
                        aria-label="{{ __('web/file-hosting.view_list') }}" title="{{ __('web/file-hosting.view_list') }}">
                    <i class="bi bi-list" aria-hidden="true"></i>
                </button>
                <button type="button" class="fh-toolbar__view-btn btn btn-outline-secondary" data-fh-view-btn="grid"
                        aria-label="{{ __('web/file-hosting.view_grid') }}" title="{{ __('web/file-hosting.view_grid') }}">
                    <i class="bi bi-grid" aria-hidden="true"></i>
                </button>
            </div>

            <a href="{{ route('file-hosting.trash', $ownerQuery) }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-trash3 me-1"></i> {{ __('web/file-hosting.trash') }}
            </a>
            <a href="{{ route('file-hosting.microstock.usage', array_filter(['workspace_id' => $workspaceId])) }}"
               class="btn btn-outline-secondary btn-sm {{ $isMicrostock ? '' : 'd-none' }}"
               data-fh-toolbar-balance>
                <i class="bi bi-wallet2 me-1"></i> {{ __('web/microstock.usage.toolbar_balance') }}
            </a>
            @can('create file hosting folder')
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#fhNewFolderModal">
                    <i class="bi bi-folder-plus me-1"></i> {{ __('web/file-hosting.new_folder') }}
                </button>
            @endcan

            {{-- Microstock collection action (visible only inside a microstock collection; JS-driven) --}}
            <div class="d-none" data-fh-collection-action>
                <button type="button" class="btn btn-outline-primary btn-sm" data-fh-collection-primary data-bs-toggle="modal" data-bs-target="#fhCollectionDescribeModal">
                    <i class="bi bi-lock me-1"></i><span data-fh-collection-primary-label>{{ __('web/file-hosting.microstock.lock_describe') }}</span>
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm d-none" data-fh-collection-review>
                    <i class="bi bi-stars me-1"></i>{{ __('web/file-hosting.microstock.review_files') }}
                </button>
            </div>

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
        <nav class="fh-breadcrumb" aria-label="breadcrumb" data-fh-breadcrumb data-fh-browse-chrome>
            @if (empty($breadcrumb))
                <span class="fh-breadcrumb__item is-active">
                    <i class="bi bi-folder2-open fh-breadcrumb__root-icon me-1"></i>
                    @if($workspaceId)
                        {{ $currentWorkspace['name'] ?? __('web/file-hosting.workspace_team') }}
                    @else
                        {{ __('web/file-hosting.root_label') }}
                    @endif
                </span>
            @else
                <a href="{{ route('file-hosting.index', $ownerQuery) }}"
                   class="fh-breadcrumb__item"
                   data-fh-soft-nav
                   data-fh-drop-folder="root">
                    <i class="bi bi-folder2-open fh-breadcrumb__root-icon me-1"></i>
                    @if($workspaceId)
                        {{ $currentWorkspace['name'] ?? __('web/file-hosting.workspace_team') }}
                    @else
                        {{ __('web/file-hosting.root_label') }}
                    @endif
                </a>
            @endif
            @foreach ($breadcrumb as $crumb)
                <i class="bi bi-chevron-right fh-breadcrumb__sep"></i>
                @if ($loop->last)
                    <span class="fh-breadcrumb__item is-active">{{ $crumb['name'] }}</span>
                @else
                    <a href="{{ route('file-hosting.index', array_filter(array_merge($ownerQuery, ['folder_id' => $crumb['id']]))) }}"
                       class="fh-breadcrumb__item"
                       data-fh-soft-nav
                       data-fh-drop-folder="{{ $crumb['id'] }}">
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
                data-workspace-kind="{{ $currentWorkspace['kind'] ?? '' }}"
                data-collection-locked="{{ ! empty($browse['folder']['collection_locked'] ?? null) ? '1' : '0' }}"
                data-init-url="{{ route('file-hosting.uploads.store') }}"
                data-status-url="{{ route('file-hosting.uploads.show', ['upload' => '__UPLOAD__']) }}"
                data-complete-url="{{ route('file-hosting.uploads.complete', ['upload' => '__UPLOAD__']) }}"
                data-abort-url="{{ route('file-hosting.uploads.destroy', ['upload' => '__UPLOAD__']) }}"
                data-sign-parts-url="{{ route('file-hosting.uploads.sign-parts', ['upload' => '__UPLOAD__']) }}"
                data-max-files="{{ (int) setting_config('file-hosting.max_files_per_batch', 20) }}"
                data-max-concurrent="{{ (int) setting_config('file-hosting.max_concurrent_uploads', 2) }}"
                data-max-concurrent-parts="{{ (int) setting_config('file-hosting.max_concurrent_parts', 3) }}"
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
                    'requiresFolder' => __('web/file-hosting.microstock.error_requires_folder'),
                    'collectionLocked' => __('web/file-hosting.microstock.error_collection_locked'),
                ]) }}"
            >
                <div class="fh-dropzone__icon">
                    <i class="bi bi-cloud-arrow-up" aria-hidden="true"></i>
                </div>
                <div class="fh-dropzone__text">{{ __('web/file-hosting.upload_here') }}</div>
                <div class="fh-dropzone__hint">{{ __('web/file-hosting.drag_drop_hint') }}</div>
                <div class="fh-dropzone__actions">
                    <input type="file" class="d-none" data-direct-upload-input multiple>
                    <button type="button" class="btn btn-outline-primary btn-sm" data-direct-upload-browse>
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
                @canany(['edit file hosting file', 'edit file hosting folder'])
                    <button type="button" class="btn btn-outline-primary btn-sm" data-fh-bulk-action="move">
                        <i class="bi bi-folder-symlink me-1"></i>{{ __('web/file-hosting.bulk.move') }}
                    </button>
                @endcanany
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
