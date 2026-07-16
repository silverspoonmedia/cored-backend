<x-adminlte4::layouts.app :title="__('web/services.title')">
    <x-adminlte4::card :title="__('web/services.list_title')">
        @can('manage service')
            <x-slot:actions>
                <a href="{{ route('services.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg me-1"></i> {{ __('web/services.add_button') }}
                </a>
            </x-slot:actions>
        @endcan

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('web/services.columns.code') }}</th>
                        <th>{{ __('web/services.columns.name') }}</th>
                        <th>{{ __('web/services.columns.prices') }}</th>
                        <th>{{ __('web/services.columns.interval') }}</th>
                        <th>{{ __('web/services.columns.status') }}</th>
                        <th class="text-end">{{ __('web/services.columns.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($services as $service)
                        @php
                            $prices = collect($service['prices'] ?? [])
                                ->map(fn ($p) => ($p['currency'] ?? '').' '.number_format((float) ($p['amount'] ?? 0), 2))
                                ->implode(', ');
                        @endphp
                        <tr>
                            <td><code>{{ $service['code'] ?? '' }}</code></td>
                            <td>{{ $service['name'] ?? '' }}</td>
                            <td>{{ $prices ?: '—' }}</td>
                            <td>{{ $service['billing_interval'] ?? '' }}</td>
                            <td>
                                <span class="badge text-bg-light border">{{ $service['status'] ?? '' }}</span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('services.show', $service['id']) }}" class="btn btn-outline-info btn-sm">
                                    <i class="bi bi-eye"></i>
                                </a>
                                @can('manage service')
                                    <a href="{{ route('services.edit', $service['id']) }}" class="btn btn-outline-secondary btn-sm">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form action="{{ route('services.destroy', $service['id']) }}" method="POST" class="d-inline"
                                        onsubmit="return confirm(@json(__('web/services.confirm_delete')));">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-muted text-center py-4">{{ __('web/services.empty') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-adminlte4::card>
</x-adminlte4::layouts.app>
