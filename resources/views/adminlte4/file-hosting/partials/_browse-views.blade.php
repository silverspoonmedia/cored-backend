        {{-- SPA browse panel (table + grid) --}}
        <div data-fh-panel="browse">
        {{-- Microstock collection stepper (JS-driven, hidden by default) --}}
        <div class="fh-collection-stepper d-none" data-fh-collection-stepper role="status">
            <div class="fh-collection-stepper__steps">
                <div class="fh-collection-stepper__step is-pending" data-fh-step="1">
                    <span class="fh-collection-stepper__circle">1</span>
                    <span class="fh-collection-stepper__label" data-fh-step-label>{{ __('web/file-hosting.microstock.step_upload') }}</span>
                </div>
                <div class="fh-collection-stepper__line"></div>
                <div class="fh-collection-stepper__step is-pending" data-fh-step="2">
                    <span class="fh-collection-stepper__circle">2</span>
                    <span class="fh-collection-stepper__label" data-fh-step-label>{{ __('web/file-hosting.microstock.step_lock_describe') }}</span>
                </div>
                <div class="fh-collection-stepper__line"></div>
                <div class="fh-collection-stepper__step is-pending" data-fh-step="3">
                    <span class="fh-collection-stepper__circle">3</span>
                    <span class="fh-collection-stepper__label" data-fh-step-label>{{ __('web/file-hosting.microstock.step_analyze') }}</span>
                </div>
                <div class="fh-collection-stepper__line"></div>
                <div class="fh-collection-stepper__step is-pending" data-fh-step="4">
                    <span class="fh-collection-stepper__circle">4</span>
                    <span class="fh-collection-stepper__label" data-fh-step-label>{{ __('web/file-hosting.microstock.step_review') }}</span>
                </div>
                <div class="fh-collection-stepper__line"></div>
                <div class="fh-collection-stepper__step is-pending" data-fh-step="5">
                    <span class="fh-collection-stepper__circle">5</span>
                    <span class="fh-collection-stepper__label" data-fh-step-label>{{ __('web/file-hosting.microstock.step_export') }}</span>
                </div>
            </div>
            <div class="fh-collection-stepper__footer">
                <div class="fh-collection-stepper__hint" data-fh-step-hint></div>
                <div class="fh-collection-stepper__progress d-none" data-fh-step-progress></div>
            </div>
            {{-- Legacy banner text container for progress/degraded messages --}}
            <div class="d-none" data-fh-collection-banner-text></div>
        </div>
        {{-- Legacy banner container (kept for compatibility, hidden by JS) --}}
        <div class="alert alert-secondary d-none" data-fh-collection-banner role="status" aria-hidden="true">
            <i class="bi bi-images"></i>
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
                            @if ($showRegion)
                                <th>{{ __('web/file-hosting.columns.region') }}</th>
                            @endif
                            <th>{{ __('web/file-hosting.columns.visibility') }}</th>
                            <th class="text-end">{{ __('web/file-hosting.columns.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if ($parentFolderShow)
                            <tr data-fh-row data-fh-entity="parent" data-fh-drop-folder="{{ $parentFolderDrop }}" class="fh-parent-row" title="{{ __('web/file-hosting.parent_folder') }}">
                                <td class="fh-table__select-cell"></td>
                                <td class="fh-table__icon-cell">
                                    <a href="{{ $parentFolderHref }}" data-fh-soft-nav aria-label="{{ __('web/file-hosting.parent_folder') }}">
                                        <i class="bi bi-folder2 fh-icon--folder fh-parent-row__icon" aria-hidden="true"></i>
                                    </a>
                                </td>
                                <td>
                                    <a href="{{ $parentFolderHref }}" class="fh-table__name" data-fh-soft-nav title="{{ __('web/file-hosting.parent_folder') }}">
                                        <span class="fh-table__name-text">...</span>
                                    </a>
                                </td>
                                <td class="fh-table__type text-muted">{{ __('web/file-hosting.parent_folder') }}</td>
                                <td class="fh-table__size">&mdash;</td>
                                @if ($showRegion)
                                    <td class="fh-table__region"><span class="text-muted">&mdash;</span></td>
                                @endif
                                <td></td>
                                <td class="fh-table__actions"></td>
                            </tr>
                        @endif
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
                        <td class="fh-table__size">â€”</td>
                        @if ($showRegion)
                            <td class="fh-table__region"><span class="text-muted">â€”</span></td>
                        @endif
                        <td data-fh-visibility-cell>@include('adminlte4.file-hosting.partials._visibility-badge', ['visibility' => $folderVis])</td>
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
                                        >
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
                            @include('adminlte4.file-hosting.partials._microstock-badge', ['microstock' => $file['microstock'] ?? null, 'show' => $isMicrostock])
                        </td>
                        <td class="fh-table__type">{{ $file['mime'] ?? __('web/file-hosting.type_file') }}</td>
                        <td class="fh-table__size">{{ $file['size_human'] ?? 'â€”' }}</td>
                        @if ($showRegion)
                            <td class="fh-table__region">
                                @php
                                    $regionKey = $file['region']
                                        ?? \App\Domain\FileHosting\Support\RegionResolver::keyForDisk((string) ($file['disk'] ?? ''));
                                @endphp
                                @if ($regionKey)
                                    <span class="badge text-bg-secondary fh-region-badge">{{ __('web/file-hosting.regions.'.$regionKey) }}</span>
                                @else
                                    <span class="text-muted">â€”</span>
                                @endif
                            </td>
                        @endif
                        <td data-fh-visibility-cell>@include('adminlte4.file-hosting.partials._visibility-badge', ['visibility' => $fileVis])</td>
                        <td class="fh-table__actions">
                            @if ($isMicrostock)
                                <button type="button" class="fh-table__action-btn" title="{{ __('web/file-hosting.microstock.review') }}"
                                        data-fh-ms-review="{{ $file['id'] }}">
                                    <i class="bi bi-stars"></i>
                                </button>
                            @endif
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
                                        >
                                    <i class="bi bi-trash"></i>
                                </button>
                            @endcan
                        </td>
                    </tr>
                @endforeach

                        @if ($folders->isEmpty() && $files->isEmpty())
                            <tr data-fh-empty>
                                <td colspan="{{ $tableColspan }}">
                                    <div class="fh-empty{{ $parentFolderShow ? ' fh-empty--compact' : '' }}">
                                        @unless ($parentFolderShow)
                                            <i class="bi bi-folder2-open fh-empty__icon"></i>
                                        @endunless
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
            @if ($folders->isEmpty() && $files->isEmpty() && ! $parentFolderShow)
                <div class="fh-empty">
                    <i class="bi bi-folder2-open fh-empty__icon"></i>
                    <div class="fh-empty__text">{{ __('web/file-hosting.empty') }}</div>
                </div>
            @else
                <div class="fh-grid">
                    @if ($parentFolderShow)
                        <div class="fh-file-card is-parent" data-fh-row data-fh-entity="parent" data-fh-drop-folder="{{ $parentFolderDrop }}" title="{{ __('web/file-hosting.parent_folder') }}">
                            <div class="fh-file-card__icon">
                                <a href="{{ $parentFolderHref }}" data-fh-soft-nav aria-label="{{ __('web/file-hosting.parent_folder') }}">
                                    <i class="bi bi-folder2 fh-icon--folder fh-parent-row__icon" aria-hidden="true"></i>
                                </a>
                            </div>
                            <a href="{{ $parentFolderHref }}" class="fh-file-card__name" data-fh-soft-nav title="{{ __('web/file-hosting.parent_folder') }}">...</a>
                            <div class="fh-file-card__meta">{{ __('web/file-hosting.parent_folder') }}</div>
                        </div>
                    @endif
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
                                            >
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
                                @include('adminlte4.file-hosting.partials._microstock-badge', ['microstock' => $file['microstock'] ?? null, 'show' => $isMicrostock])
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
                                {{ $file['size_human'] ?? 'â€”' }}
                                @if ($showRegion)
                                    @php
                                        $gridRegionKey = $file['region']
                                            ?? \App\Domain\FileHosting\Support\RegionResolver::keyForDisk((string) ($file['disk'] ?? ''));
                                    @endphp
                                    @if ($gridRegionKey) Â· <span class="fh-file-card__region">{{ __('web/file-hosting.regions.'.$gridRegionKey) }}</span>@endif
                                @endif
                            </div>
                            <div class="fh-file-card__actions">
                                @if ($isMicrostock)
                                    <button type="button" class="fh-table__action-btn" title="{{ __('web/file-hosting.microstock.review') }}"
                                            data-fh-ms-review="{{ $file['id'] }}">
                                        <i class="bi bi-stars"></i>
                                    </button>
                                @endif
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
                                            >
                                        <i class="bi bi-trash"></i>
                                    </button>
                                @endcan
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
        </div>{{-- /data-fh-panel=browse --}}