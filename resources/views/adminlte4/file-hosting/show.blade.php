@php
    $ownerQuery = array_filter(['user_id' => $ownerUserId]);
    $folderId = $file['folder_id'] ?? null;
    $backQuery = array_filter(array_merge($ownerQuery, ['folder_id' => $folderId]));
    $vis = $file['visibility'] ?? 'private';
    $visLink = \App\Domain\FileHosting\Enums\Visibility::AnyoneWithLink->value;
@endphp

<x-adminlte4::layouts.app :title="__('web/file-hosting.show_title')">
    <x-adminlte4::card :title="$file['name'] ?? __('web/file-hosting.type_file')">
        <x-slot:actions>
            <a href="{{ route('file-hosting.index', $backQuery) }}" class="btn btn-outline-secondary btn-sm me-1">
                <i class="bi bi-arrow-left me-1"></i> {{ __('web/file-hosting.back') }}
            </a>
            @can('view file hosting file')
                <a href="{{ route('file-hosting.files.download', array_filter(array_merge(['file' => $file['id']], $ownerQuery))) }}"
                   class="btn btn-primary btn-sm me-1">
                    <i class="bi bi-download me-1"></i> {{ __('web/file-hosting.download') }}
                </a>
            @endcan
            @can('delete file hosting file')
                <form action="{{ route('file-hosting.files.destroy', $file['id']) }}" method="POST" class="d-inline"
                    onsubmit="return confirm(@json(__('web/file-hosting.confirm_delete_file')));">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="return_folder_id" value="{{ $folderId }}">
                    @if ($ownerUserId)
                        <input type="hidden" name="user_id" value="{{ $ownerUserId }}">
                    @endif
                    <button type="submit" class="btn btn-outline-danger btn-sm">
                        <i class="bi bi-trash me-1"></i> {{ __('web/file-hosting.delete') }}
                    </button>
                </form>
            @endcan
        </x-slot:actions>

        <dl class="row mb-0">
            <dt class="col-sm-3">{{ __('web/file-hosting.columns.name') }}</dt>
            <dd class="col-sm-9">{{ $file['name'] ?? '' }}</dd>

            <dt class="col-sm-3">{{ __('web/file-hosting.columns.type') }}</dt>
            <dd class="col-sm-9">{{ $file['mime'] ?? __('web/file-hosting.type_file') }}</dd>

            <dt class="col-sm-3">{{ __('web/file-hosting.columns.size') }}</dt>
            <dd class="col-sm-9">{{ number_format(((int) ($file['size'] ?? 0)) / 1024, 1) }} KB</dd>

            <dt class="col-sm-3">{{ __('web/file-hosting.columns.visibility') }}</dt>
            <dd class="col-sm-9">
                @if ($vis === $visLink)
                    <span class="badge text-bg-success">{{ __('web/file-hosting.visibility.anyone_with_link') }}</span>
                    @if (! empty($file['share_url']))
                        <div class="input-group input-group-sm mt-2" style="max-width: 28rem;">
                            <input type="text" class="form-control" value="{{ $file['share_url'] }}" readonly id="fh-show-share-url">
                            <button type="button" class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText(document.getElementById('fh-show-share-url').value)">
                                {{ __('web/file-hosting.copy_link') }}
                            </button>
                        </div>
                    @endif
                @else
                    <span class="badge text-bg-secondary">{{ __('web/file-hosting.visibility.private') }}</span>
                @endif
            </dd>

            <dt class="col-sm-3">{{ __('web/file-hosting.stats.views') }}</dt>
            <dd class="col-sm-9">{{ (int) ($file['view_count'] ?? 0) }}</dd>

            <dt class="col-sm-3">{{ __('web/file-hosting.stats.downloads') }}</dt>
            <dd class="col-sm-9">{{ (int) ($file['download_count'] ?? 0) }}</dd>

            <dt class="col-sm-3">{{ __('web/file-hosting.stats.last_viewed') }}</dt>
            <dd class="col-sm-9">{{ $file['last_viewed_at'] ?? '—' }}</dd>

            <dt class="col-sm-3">{{ __('web/file-hosting.stats.last_downloaded') }}</dt>
            <dd class="col-sm-9">{{ $file['last_downloaded_at'] ?? '—' }}</dd>

            <dt class="col-sm-3">{{ __('web/file-hosting.created_at') }}</dt>
            <dd class="col-sm-9">{{ $file['created_at'] ?? '—' }}</dd>
        </dl>
    </x-adminlte4::card>

    @can('edit file hosting file')
        <x-adminlte4::card :title="__('web/file-hosting.share')" class="mt-3">
            <form method="POST" action="{{ route('file-hosting.files.visibility', $file['id']) }}" class="row g-2 align-items-end">
                @csrf
                @method('PATCH')
                @if ($ownerUserId)
                    <input type="hidden" name="user_id" value="{{ $ownerUserId }}">
                @endif
                <div class="col-md-6">
                    <label class="form-label" for="fh-show-vis">{{ __('web/file-hosting.visibility.label') }}</label>
                    <select name="visibility" id="fh-show-vis" class="form-select">
                        <option value="private" @selected($vis === 'private')>{{ __('web/file-hosting.visibility.private') }}</option>
                        <option value="anyone_with_link" @selected($vis === $visLink)>{{ __('web/file-hosting.visibility.anyone_with_link') }}</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary">{{ __('web/file-hosting.save') }}</button>
                </div>
            </form>
        </x-adminlte4::card>
    @endcan
</x-adminlte4::layouts.app>
