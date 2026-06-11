<x-layouts.app :title="__('web/menus.title')">
    <x-slot:breadcrumb>
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('menu.dashboard') }}</a></li>
        <li class="breadcrumb-item active" aria-current="page">{{ __('web/menus.breadcrumb') }}</li>
    </x-slot:breadcrumb>

    <x-card :title="__('web/menus.list_title')">
        @can('manage menu')
            <x-slot:actions>
                <a href="{{ route('menus.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg me-1"></i> {{ __('web/menus.add_button') }}
                </a>
            </x-slot:actions>
        @endcan

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('web/menus.columns.name') }}</th>
                        <th>{{ __('web/menus.columns.type') }}</th>
                        <th>{{ __('web/menus.columns.route') }}</th>
                        <th>{{ __('web/menus.columns.order') }}</th>
                        <th>{{ __('web/menus.columns.attributes') }}</th>
                        <th class="text-end">{{ __('web/menus.columns.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($menus as $menu)
                        <tr>
                            <td>
                                <span style="padding-left: {{ $menu['type'] === 'c' ? 32 : ($menu['type'] === 'p' ? 16 : 0) }}px;">
                                    @if ($menu['icon'])<i class="bi {{ $menu['icon'] }} me-1"></i>@endif
                                    {{ $menu['name'] }}
                                </span>
                            </td>
                            <td><code>{{ $menu['type'] }}</code></td>
                            <td>{{ $menu['route'] ?? '—' }}</td>
                            <td>{{ $menu['order'] ?? '—' }}</td>
                            <td>
                                @if ($menu['is_authenticate'])<span class="badge text-bg-info">auth</span>@endif
                                @if ($menu['is_guest_only'])<span class="badge text-bg-secondary">guest</span>@endif
                            </td>
                            <td class="text-end">
                                @can('manage menu')
                                    <a href="{{ route('menus.edit', $menu['id']) }}" class="btn btn-sm btn-info">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <x-form.ajax :action="route('menus.destroy', $menu['id'])" method="DELETE" class="d-inline">
                                        <div class="btn-group">
                                            <button type="submit" class="btn btn-sm btn-danger" title="{{ __('web/menus.delete_title') }}"
                                                    onclick="return confirm(@json(__('web/menus.delete')));">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                            <button type="submit" name="cascade" value="1" class="btn btn-sm btn-outline-danger"
                                                    title="{{ __('web/menus.delete_cascade_title') }}"
                                                    onclick="return confirm(@json(__('web/menus.delete_cascade')));">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        </div>
                                    </x-form.ajax>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">{{ __('web/menus.empty_list') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</x-layouts.app>
