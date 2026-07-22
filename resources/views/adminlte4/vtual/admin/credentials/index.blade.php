<x-adminlte4::layouts.app :title="__('web/vtual.credentials.title')">
    <x-adminlte4::card :title="__('web/vtual.credentials.title')">
        <p class="text-muted small mb-3">{{ __('web/vtual.credentials.subtitle') }}</p>

        <div class="mb-3">
            <a href="{{ route('vtual.admin.platform-api-probe') }}" class="btn btn-outline-primary btn-sm">
                {{ __('web/vtual.probe.open_probe') }}
            </a>
        </div>

        <h5 class="mb-2">{{ __('web/vtual.credentials.platforms') }}</h5>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('web/vtual.name') }}</th>
                        <th>Key</th>
                        <th>{{ __('web/vtual.status') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($platforms as $platform)
                        <tr>
                            <td>{{ $platform['name'] ?? '' }}</td>
                            <td><code>{{ $platform['key'] ?? '' }}</code></td>
                            <td>
                                @if (! empty($platform['enabled']))
                                    <span class="badge text-bg-success">{{ __('web/vtual.enabled') }}</span>
                                @else
                                    <span class="badge text-bg-secondary">{{ __('web/vtual.disabled') }}</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('vtual.admin.credentials.platform', $platform['id']) }}"
                                   class="btn btn-outline-primary btn-sm">
                                    {{ __('web/vtual.credentials.manage') }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">
                                <x-adminlte4::empty-state icon="bi-key" :title="__('web/vtual.credentials.empty')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-adminlte4::card>
</x-adminlte4::layouts.app>
