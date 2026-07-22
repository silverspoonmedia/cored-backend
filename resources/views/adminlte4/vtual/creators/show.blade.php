<x-adminlte4::layouts.app :title="($creator['display_name'] ?? __('web/vtual.creators')).' — '.__('web/vtual.title')">
    <x-adminlte4::card :title="$creator['display_name'] ?? __('web/vtual.creators')">
        <x-slot:actions>
            <a href="{{ route('vtual.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('web/vtual.back') }}</a>
        </x-slot:actions>

        <dl class="row mb-4">
            <dt class="col-sm-2">Slug</dt>
            <dd class="col-sm-10"><code>{{ $creator['slug'] ?? '' }}</code></dd>
            <dt class="col-sm-2">{{ __('web/vtual.status') }}</dt>
            <dd class="col-sm-10">{{ $creator['status'] ?? '' }}</dd>
            <dt class="col-sm-2">{{ __('web/vtual.bio') }}</dt>
            <dd class="col-sm-10">{{ $creator['bio'] ?? '—' }}</dd>
        </dl>

        <h5 class="mb-2">{{ __('web/vtual.channels') }}</h5>
        <form method="POST" action="{{ route('vtual.creators.channels.store', $creator['id']) }}" class="border rounded p-3 mb-3">
            @csrf
            <div class="row g-2">
                <div class="col-md-3">
                    <label class="form-label">{{ __('web/vtual.platform') }}</label>
                    <select name="platform_key" class="form-select form-select-sm" required>
                        @foreach ($platforms as $platform)
                            @if (!empty($platform['enabled']))
                                <option value="{{ $platform['key'] }}">{{ $platform['name'] }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">{{ __('web/vtual.external_id') }}</label>
                    <input type="text" name="external_id" class="form-control form-control-sm" required maxlength="191">
                </div>
                <div class="col-md-3">
                    <label class="form-label">{{ __('web/vtual.handle') }}</label>
                    <input type="text" name="handle" class="form-control form-control-sm" maxlength="191">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary btn-sm w-100">{{ __('web/vtual.add_channel') }}</button>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('web/vtual.platform') }}</th>
                        <th>{{ __('web/vtual.external_id') }}</th>
                        <th>{{ __('web/vtual.handle') }}</th>
                        <th>{{ __('web/vtual.status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($channels as $channel)
                        <tr>
                            <td>{{ $channel['platform']['name'] ?? $channel['platform']['key'] ?? '' }}</td>
                            <td><code>{{ $channel['external_id'] ?? '' }}</code></td>
                            <td>{{ $channel['handle'] ?? '—' }}</td>
                            <td><span class="badge text-bg-light border">{{ $channel['status'] ?? '' }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">
                                <x-adminlte4::empty-state icon="bi-link-45deg" :title="__('web/vtual.empty_channels')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-adminlte4::card>
</x-adminlte4::layouts.app>
