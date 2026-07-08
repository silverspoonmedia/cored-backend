<x-adminlte4::layouts.app :title="__('web/menus.title')">
    <x-adminlte4::card :title="__('web/menus.list_title')">
        @can('manage menu')
            <x-slot:actions>
                <a href="{{ route('menus.trashed', ['layout' => $selectedLayout]) }}" class="btn btn-outline-secondary btn-sm me-1">
                    <i class="bi bi-trash me-1"></i> {{ __('web/menus.trashed_breadcrumb') }}
                </a>
                <a href="{{ route('menus.create', ['layout' => $selectedLayout]) }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg me-1"></i> {{ __('web/menus.add_button') }}
                </a>
            </x-slot:actions>
        @endcan

        <form method="GET" action="{{ route('menus.index') }}" class="row g-2 align-items-end mb-3">
            <div class="col-sm-4 col-md-3">
                <x-adminlte4::form.select
                    name="layout"
                    :label="__('web/menus.filter.layout_label')"
                    :options="$layoutOptions"
                    :selected="$selectedLayout"
                    :hint="__('web/menus.filter.layout_hint')"
                    :autosubmit="true"
                />
            </div>
        </form>

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
                        @php($hasChildren = ($menu['children_count'] ?? 0) > 0)
                        <tr>
                            <td>
                                <span style="padding-left: {{ $menu['type'] === 'c' ? 32 : ($menu['type'] === 'p' ? 16 : 0) }}px;">
                                    @if ($menu['icon'])<i class="bi {{ $menu['icon'] }} me-1"></i>@endif
                                    <span @class(['fw-bold' => $hasChildren])>{{ $menu['name'] }}</span>
                                    @if ($hasChildren)
                                        <span class="badge text-bg-light border text-muted ms-1 fw-normal"
                                            title="{{ __('web/menus.has_children_title', ['count' => $menu['children_count']]) }}">
                                            <i class="bi bi-diagram-3 me-1" aria-hidden="true"></i>{{ $menu['children_count'] }}
                                        </span>
                                    @endif
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
                                    <x-adminlte4::form.ajax
                                        :action="route('menus.destroy', ['menu' => $menu['id'], 'layout' => $selectedLayout])"
                                        method="DELETE"
                                        class="d-inline"
                                    >
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
                                    </x-adminlte4::form.ajax>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                {{ __('web/menus.empty_list_layout', ['layout' => $layoutOptions[$selectedLayout] ?? $selectedLayout]) }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-adminlte4::card>

    <x-slot:scripts>
        <x-adminlte4::partials.select2-scripts />
    </x-slot:scripts>
</x-adminlte4::layouts.app>
