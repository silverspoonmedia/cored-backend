<x-adminlte4::layouts.app :title="__('web/notifications.title')">
    <x-adminlte4::card :title="__('web/notifications.list_title')">
        @if (count($notifications) > 0)
            <x-slot:actions>
                <x-adminlte4::form.ajax :action="route('notifications.read-all')" :show-alert="false" class="m-0" data-notification-read-all-form>
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
                        <tr
                            @class(['fw-semibold' => ! $notification['is_read']])
                            data-notification-row
                            data-notification-read="{{ $notification['is_read'] ? 'true' : 'false' }}"
                        >
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
                                    <span class="badge text-bg-light border text-muted" data-notification-status>{{ __('web/notifications.status.read') }}</span>
                                @else
                                    <span class="badge text-bg-primary" data-notification-status>{{ __('web/notifications.status.unread') }}</span>
                                @endif
                            </td>
                            <td class="text-muted small">{{ $notification['created_at_human'] ?? $notification['created_at_formatted'] ?? $notification['created_at'] ?? '—' }}</td>
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

    <x-slot:scripts>
        <script>
            (function () {
                function applyReadAllRows() {
                    document.querySelectorAll('[data-notification-row]').forEach(function (row) {
                        row.classList.remove('fw-semibold');
                        row.dataset.notificationRead = 'true';

                        const status = row.querySelector('[data-notification-status]');

                        if (status) {
                            status.className = 'badge text-bg-light border text-muted';
                            status.textContent = @json(__('web/notifications.status.read'));
                        }
                    });

                    document.querySelectorAll('[data-notification-read-all-form] button[type="submit"]').forEach(function (button) {
                        button.disabled = true;
                    });
                }

                // Direct AJAX success keeps this page independent from bell.js;
                // the domain event also covers cross-tab updates from the navbar.
                document.addEventListener('ajax-form:success', function (event) {
                    if (event.detail?.form?.matches('[data-notification-read-all-form]')) {
                        applyReadAllRows();
                    }
                });
                document.addEventListener('notification-read-all:applied', applyReadAllRows);
                document.addEventListener('notification-unread-count:changed', function (event) {
                    if (Number(event.detail?.unread ?? 0) > 0) {
                        document.querySelectorAll('[data-notification-read-all-form] button[type="submit"]').forEach(function (button) {
                            button.disabled = false;
                        });
                    }
                });
            })();
        </script>
    </x-slot:scripts>
</x-adminlte4::layouts.app>
