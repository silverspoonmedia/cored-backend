@php
    $ownerQuery = array_filter(['user_id' => $ownerUserId]);
    $folderId = $file['folder_id'] ?? null;
    $backQuery = array_filter(array_merge($ownerQuery, ['folder_id' => $folderId]));
    $vis = $file['visibility'] ?? 'private';
    $visLink = \App\Domain\FileHosting\Enums\Visibility::AnyoneWithLink->value;
    $visPrivate = \App\Domain\FileHosting\Enums\Visibility::Private->value;

    $formatBytes = function (int $bytes): string {
        if ($bytes === 0) return '0 B';
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1024 * 1024) return number_format($bytes / 1024, 1) . ' KB';
        if ($bytes < 1024 * 1024 * 1024) return number_format($bytes / (1024 * 1024), 1) . ' MB';
        return number_format($bytes / (1024 * 1024 * 1024), 1) . ' GB';
    };
@endphp

<x-adminlte4::layouts.app :title="__('web/file-hosting.show_title')">
    <div class="fh-manager" data-file-manager data-config="{{ json_encode([
        'ownerUserId' => $ownerUserId,
        'messages' => [
            'searchNoResults' => __('web/file-hosting.search_no_results'),
            'rootLabel' => __('web/file-hosting.root_label'),
            'loadFailed' => __('web/file-hosting.load_failed'),
            'linkCopied' => __('web/file-hosting.link_copied'),
            'success' => __('web/file-hosting.ajax_success'),
            'error' => __('web/file-hosting.ajax_error'),
            'networkError' => __('web/file-hosting.network_error'),
        ],
    ]) }}">
        <div class="row g-3">
            {{-- Main column --}}
            <div class="col-lg-8">
                <x-adminlte4::card>
                    {{-- Hero --}}
                    <div class="fh-detail__hero">
                        <div class="fh-detail__hero-icon">
                            @include('adminlte4.file-hosting.partials._file-icon', ['mime' => $file['mime'] ?? null, 'isFolder' => false])
                        </div>
                        <div class="flex-grow-1 min-w-0">
                            <div class="fh-detail__hero-name">{{ $file['name'] ?? '' }}</div>
                            <div class="fh-detail__hero-meta">
                                {{ $file['mime'] ?? __('web/file-hosting.type_file') }} · {{ $formatBytes((int) ($file['size'] ?? 0)) }}
                            </div>
                        </div>
                    </div>

                    {{-- Stats grid --}}
                    <h6 class="text-uppercase text-muted small mb-3">{{ __('web/file-hosting.stats.views') }} & {{ __('web/file-hosting.stats.downloads') }}</h6>
                    <div class="fh-stats">
                        <div class="fh-stat">
                            <div class="fh-stat__icon"><i class="bi bi-eye"></i></div>
                            <div class="fh-stat__body">
                                <div class="fh-stat__value">{{ (int) ($file['view_count'] ?? 0) }}</div>
                                <div class="fh-stat__label">{{ __('web/file-hosting.stats.views') }}</div>
                            </div>
                        </div>
                        <div class="fh-stat">
                            <div class="fh-stat__icon"><i class="bi bi-download"></i></div>
                            <div class="fh-stat__body">
                                <div class="fh-stat__value">{{ (int) ($file['download_count'] ?? 0) }}</div>
                                <div class="fh-stat__label">{{ __('web/file-hosting.stats.downloads') }}</div>
                            </div>
                        </div>
                        <div class="fh-stat">
                            <div class="fh-stat__icon"><i class="bi bi-clock-history"></i></div>
                            <div class="fh-stat__body">
                                <div class="fh-stat__value small">{{ $file['last_viewed_at'] ?? '—' }}</div>
                                <div class="fh-stat__label">{{ __('web/file-hosting.stats.last_viewed') }}</div>
                            </div>
                        </div>
                        <div class="fh-stat">
                            <div class="fh-stat__icon"><i class="bi bi-clock-history"></i></div>
                            <div class="fh-stat__body">
                                <div class="fh-stat__value small">{{ $file['last_downloaded_at'] ?? '—' }}</div>
                                <div class="fh-stat__label">{{ __('web/file-hosting.stats.last_downloaded') }}</div>
                            </div>
                        </div>
                    </div>

                    <hr class="my-3">

                    <dl class="row mb-0">
                        <dt class="col-sm-3">{{ __('web/file-hosting.columns.name') }}</dt>
                        <dd class="col-sm-9">{{ $file['name'] ?? '' }}</dd>

                        <dt class="col-sm-3">{{ __('web/file-hosting.columns.type') }}</dt>
                        <dd class="col-sm-9">{{ $file['mime'] ?? __('web/file-hosting.type_file') }}</dd>

                        <dt class="col-sm-3">{{ __('web/file-hosting.columns.size') }}</dt>
                        <dd class="col-sm-9">{{ $formatBytes((int) ($file['size'] ?? 0)) }}</dd>

                        @php
                            $showRegionKey = $file['region']
                                ?? \App\Domain\FileHosting\Support\RegionResolver::keyForDisk((string) ($file['disk'] ?? ''));
                        @endphp
                        @if ($showRegionKey)
                            <dt class="col-sm-3">{{ __('web/file-hosting.columns.region') }}</dt>
                            <dd class="col-sm-9">
                                <span class="badge text-bg-secondary fh-region-badge">{{ __('web/file-hosting.regions.'.$showRegionKey) }}</span>
                            </dd>
                        @endif

                        <dt class="col-sm-3">{{ __('web/file-hosting.columns.visibility') }}</dt>
                        <dd class="col-sm-9">
                            @include('adminlte4.file-hosting.partials._visibility-badge', ['visibility' => $vis])
                        </dd>

                        <dt class="col-sm-3">{{ __('web/file-hosting.created_at') }}</dt>
                        <dd class="col-sm-9">{{ $file['created_at'] ?? '—' }}</dd>
                    </dl>
                </x-adminlte4::card>
            </div>

            {{-- Sidebar --}}
            <div class="col-lg-4">
                <x-adminlte4::card :title="__('web/file-hosting.columns.actions')" class="mb-3">
                    <div class="d-grid gap-2">
                        <a href="{{ route('file-hosting.index', $backQuery) }}" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-arrow-left me-1"></i> {{ __('web/file-hosting.back') }}
                        </a>
                        @can('view file hosting file')
                            <a href="{{ route('file-hosting.files.download', array_filter(array_merge(['file' => $file['id']], $ownerQuery))) }}"
                               class="btn btn-primary btn-sm">
                                <i class="bi bi-download me-1"></i> {{ __('web/file-hosting.download') }}
                            </a>
                        @endcan
                        @can('edit file hosting file')
                            <button type="button" class="btn btn-outline-secondary btn-sm"
                                    data-bs-toggle="modal" data-bs-target="#fhRenameFileModal"
                                    data-id="{{ $file['id'] }}" data-name="{{ $file['name'] ?? '' }}">
                                <i class="bi bi-pencil me-1"></i> {{ __('web/file-hosting.rename') }}
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm"
                                    data-bs-toggle="modal" data-bs-target="#fhShareFileModal"
                                    data-id="{{ $file['id'] }}"
                                    data-visibility="{{ $vis }}"
                                    data-share-url="{{ $file['share_url'] ?? '' }}">
                                <i class="bi bi-share me-1"></i> {{ __('web/file-hosting.share') }}
                            </button>
                        @endcan
                        @can('delete file hosting file')
                            <button type="button" class="btn btn-outline-danger btn-sm"
                                    data-fh-ajax-action="delete-file"
                                    data-fh-action-url="{{ route('file-hosting.files.destroy', $file['id']) }}"
                                    data-fh-method="DELETE"
                                    data-fh-confirm="{{ __('web/file-hosting.confirm_delete_file') }}"
                                    data-fh-redirect="{{ route('file-hosting.index', $backQuery) }}">
                                <i class="bi bi-trash me-1"></i> {{ __('web/file-hosting.delete') }}
                            </button>
                        @endcan
                    </div>
                </x-adminlte4::card>

                @can('edit file hosting file')
                    <x-adminlte4::card :title="__('web/file-hosting.visibility.label')" class="mb-3">
                        <form method="POST" action="{{ route('file-hosting.files.visibility', $file['id']) }}">
                            @csrf
                            @method('PATCH')
                            @if ($ownerUserId)
                                <input type="hidden" name="user_id" value="{{ $ownerUserId }}">
                            @endif
                            <label class="form-label" for="fh-show-vis">{{ __('web/file-hosting.visibility.label') }}</label>
                            <select name="visibility" id="fh-show-vis" class="form-select mb-2">
                                <option value="{{ $visPrivate }}" @selected($vis === $visPrivate)>{{ __('web/file-hosting.visibility.private') }}</option>
                                <option value="{{ $visLink }}" @selected($vis === $visLink)>{{ __('web/file-hosting.visibility.anyone_with_link') }}</option>
                            </select>
                            <button type="submit" class="btn btn-primary btn-sm w-100">{{ __('web/file-hosting.save') }}</button>
                        </form>
                    </x-adminlte4::card>
                @endcan

                @if ($vis === $visLink && ! empty($file['share_url']))
                    <x-adminlte4::card :title="__('web/file-hosting.share_link')">
                        <div class="fh-share-box">
                            <div class="fh-share-box__label">{{ __('web/file-hosting.share_link') }}</div>
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control" value="{{ $file['share_url'] }}" readonly id="fh-show-share-url">
                                <button type="button" class="btn btn-outline-primary" data-fh-copy="fh-show-share-url">
                                    <i class="bi bi-clipboard"></i>
                                </button>
                            </div>
                        </div>
                    </x-adminlte4::card>
                @endif
            </div>
        </div>
    </div>

    @can('edit file hosting file')
        <div class="modal fade" id="fhRenameFileModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="#" class="modal-content" id="fhRenameFileForm">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="return_folder_id" value="{{ $folderId }}">
                    @if ($ownerUserId)
                        <input type="hidden" name="user_id" value="{{ $ownerUserId }}">
                    @endif
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-pencil me-1"></i>{{ __('web/file-hosting.rename') }}</h5>
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
                        <h5 class="modal-title"><i class="bi bi-share me-1"></i>{{ __('web/file-hosting.share') }}</h5>
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
    @endcan

    @vite(['resources/css/file-hosting/file-hosting.css', 'resources/js/file-hosting/file-manager.js'])
</x-adminlte4::layouts.app>
