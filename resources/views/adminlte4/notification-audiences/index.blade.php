<x-adminlte4::layouts.app :title="__('web/notification-audiences.title')">
    <x-adminlte4::card :title="__('web/notification-audiences.title')" :subtitle="__('web/notification-audiences.subtitle')">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('web/notification-audiences.columns.type') }}</th>
                        <th>{{ __('web/notification-audiences.columns.roles') }}</th>
                        <th>{{ __('web/notification-audiences.columns.subject_user') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr>
                            <td>
                                <i class="{{ $item['icon'] ?? 'bi bi-bell' }} me-2"></i>
                                {{ $item['label'] ?? $item['type'] }}
                                <div class="text-muted small"><code>{{ $item['type'] }}</code></div>
                            </td>
                            <td>{{ implode(', ', $item['roles'] ?? []) ?: '—' }}</td>
                            <td>{{ ($item['notify_subject_user'] ?? false) ? __('ui.yes') : __('ui.no') }}</td>
                            <td class="text-end">
                                @can('manage settings')
                                    <a href="{{ route('notification-audiences.edit', $item['type']) }}" class="btn btn-sm btn-outline-primary">
                                        {{ __('ui.actions.edit') }}
                                    </a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-muted">{{ __('web/notification-audiences.empty') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-adminlte4::card>
</x-adminlte4::layouts.app>
