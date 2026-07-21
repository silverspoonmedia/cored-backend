<x-adminlte4::layouts.app :title="__('web/menus.trashed_title')">
    <x-adminlte4::card :title="__('web/menus.trashed_card_title')">
        <x-slot:actions>
            <a href="{{ route('menus.index', ['layout' => $selectedLayout]) }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> {{ __('web/menus.back_to_list') }}
            </a>
        </x-slot:actions>

        <form method="GET" action="{{ route('menus.trashed') }}" class="row g-2 align-items-end mb-3">
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
                        <th>{{ __('web/menus.columns.ancestors') }}</th>
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
                                <span class="fw-semibold">{{ $menu['name'] }}</span>
                            </td>
                            <td class="text-muted small">
                                @php($ancestors = $menu['ancestor_chain'] ?? [])
                                @if ($ancestors === [])
                                    <span class="text-body-secondary">—</span>
                                @else
                                    <span class="d-inline-flex flex-wrap align-items-center gap-1">
                                        @foreach ($ancestors as $ancestor)
                                            @if (! $loop->first)
                                                <i class="bi bi-chevron-right text-body-tertiary" aria-hidden="true"></i>
                                            @endif
                                            <span @class(['text-decoration-line-through' => $ancestor['is_trashed'] ?? false])>
                                                {{ $ancestor['name'] }}
                                                <code class="text-muted">{{ $ancestor['type'] }}</code>
                                            </span>
                                            @if ($ancestor['is_trashed'] ?? false)
                                                <span class="badge text-bg-secondary">{{ __('web/menus.ancestor_trashed_badge') }}</span>
                                            @endif
                                        @endforeach
                                    </span>
                                @endif
                            </td>
                            <td><code>{{ $menu['type'] }}</code></td>
                            <td>{{ $menu['route'] ?? '—' }}</td>
                            <td class="text-end">
                                @can('restore menu')
                                    <x-adminlte4::form.ajax
                                        :action="route('menus.restore', ['menu' => $menu['id'], 'layout' => $selectedLayout])"
                                        class="d-inline"
                                    >
                                        <button type="submit" class="btn btn-sm btn-success" aria-label="{{ __('web/menus.restore') }}">
                                            <i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i> {{ __('web/menus.restore') }}
                                        </button>
                                    </x-adminlte4::form.ajax>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                {{ __('web/menus.empty_trashed_layout', ['layout' => $layoutOptions[$selectedLayout] ?? $selectedLayout]) }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-adminlte4::card>
</x-adminlte4::layouts.app>
