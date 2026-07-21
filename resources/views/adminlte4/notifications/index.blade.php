<x-adminlte4::layouts.app :title="__('web/notifications.title')">
    <x-adminlte4::card :title="__('web/notifications.list_title')">
        @if (count($notifications) > 0)
            <x-slot:actions>
                <x-adminlte4::form.ajax :action="route('notifications.read-all')" :show-alert="false" class="m-0">
                    <button type="submit" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-check2-all me-1"></i> {{ __('web/notifications.mark_all_read') }}
                    </button>
                </x-adminlte4::form.ajax>
            </x-slot:actions>
        @endif

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('web/notifications.columns.message') }}</th>
                        <th>{{ __('web/notifications.columns.status') }}</th>
                        <th>{{ __('web/notifications.columns.time') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($notifications as $notification)
                        <tr @class(['fw-semibold' => ! $notification['is_read']])>
                            <td>
                                <i class="{{ $notification['icon'] ?? 'bi bi-bell' }} me-2"></i>
                                @if ($notification['url'])
                                    <a href="{{ $notification['url'] }}" class="text-decoration-none">{{ $notification['message'] }}</a>
                                @else
                                    {{ $notification['message'] }}
                                @endif
                            </td>
                            <td>
                                @if ($notification['is_read'])
                                    <span class="badge text-bg-light border text-muted">{{ __('web/notifications.status.read') }}</span>
                                @else
                                    <span class="badge text-bg-primary">{{ __('web/notifications.status.unread') }}</span>
                                @endif
                            </td>
                            <td class="text-muted small">{{ $notification['created_at'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3">
                                <x-adminlte4::empty-state icon="bi-bell-slash" :title="__('web/notifications.empty')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-adminlte4::card>
</x-adminlte4::layouts.app>
