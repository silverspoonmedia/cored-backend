<x-layouts.app :title="__('web/menus.trashed_title')">
    <x-card :title="__('web/menus.trashed_card_title')">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('web/menus.columns.name') }}</th>
                        <th>{{ __('web/menus.columns.type') }}</th>
                        <th>{{ __('web/menus.columns.route') }}</th>
                        <th class="text-end">{{ __('web/menus.columns.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($menus as $menu)
                        <tr>
                            <td>
                                @if ($menu['icon'])<i class="bi {{ $menu['icon'] }} me-1"></i>@endif
                                {{ $menu['name'] }}
                            </td>
                            <td><code>{{ $menu['type'] }}</code></td>
                            <td>{{ $menu['route'] ?? '—' }}</td>
                            <td class="text-end">
                                @can('restore menu')
                                    <x-form.ajax :action="route('menus.restore', $menu['id'])" class="d-inline"
                                          onsubmit="return confirm(@json(__('web/menus.restore_confirm')));">
                                        <button type="submit" class="btn btn-sm btn-success">
                                            <i class="bi bi-arrow-counterclockwise me-1"></i> {{ __('web/menus.restore') }}
                                        </button>
                                    </x-form.ajax>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">{{ __('web/menus.empty_trashed') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</x-layouts.app>
