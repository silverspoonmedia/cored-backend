<x-adminlte4::layouts.app :title="__('web/vtual-platform-credentials.title')">
    <x-adminlte4::card :title="__('web/vtual-platform-credentials.list_title')">
        <x-slot:actions>
            <a href="{{ route('vtual.platform-credentials.probe') }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-bug me-1" aria-hidden="true"></i>{{ __('web/vtual-platform-credentials.probe_button') }}
            </a>
        </x-slot:actions>

        <p class="text-muted small mb-3">{{ __('web/vtual-platform-credentials.subtitle') }}</p>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" aria-label="{{ __('web/vtual-platform-credentials.list_title') }}">
                <caption class="visually-hidden">{{ __('web/vtual-platform-credentials.list_title') }}</caption>
                <thead>
                    <tr>
                        <th scope="col">{{ __('web/vtual-platform-credentials.columns.platform') }}</th>
                        <th scope="col">{{ __('web/vtual-platform-credentials.columns.key') }}</th>
                        <th scope="col">{{ __('web/vtual-platform-credentials.columns.enabled') }}</th>
                        <th scope="col">{{ __('web/vtual-platform-credentials.columns.active') }}</th>
                        <th scope="col">{{ __('web/vtual-platform-credentials.columns.pending') }}</th>
                        <th scope="col" class="text-end">{{ __('web/vtual-platform-credentials.columns.actions') }}</th>
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
                            <td>{{ $platform['credentials_active'] ?? 0 }}</td>
                            <td>{{ $platform['credentials_pending'] ?? 0 }}</td>
                            <td class="text-end">
                                <a href="{{ route('vtual.platform-credentials.platform', $platform['id']) }}" class="btn btn-outline-primary btn-sm">
                                    {{ __('web/vtual-platform-credentials.manage_button') }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                {{ __('web/vtual-platform-credentials.empty') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-adminlte4::card>
</x-adminlte4::layouts.app>
