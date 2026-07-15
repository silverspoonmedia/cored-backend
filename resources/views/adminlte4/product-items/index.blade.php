<x-adminlte4::layouts.app :title="__('web/product-items.title')">
    <x-adminlte4::card :title="__('web/product-items.list_title')">
        @can('create product item')
            <x-slot:actions>
                <a href="{{ route('product-items.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg me-1"></i> {{ __('web/product-items.add_button') }}
                </a>
            </x-slot:actions>
        @endcan

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('web/product-items.columns.sku') }}</th>
                        <th>{{ __('web/product-items.columns.name') }}</th>
                        <th>{{ __('web/product-items.columns.price') }}</th>
                        <th>{{ __('web/product-items.columns.status') }}</th>
                        <th class="text-end">{{ __('web/product-items.columns.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr>
                            <td><code>{{ $item['sku'] ?? '' }}</code></td>
                            <td>{{ $item['name'] ?? '' }}</td>
                            <td>{{ number_format((int) ($item['unit_price_cents'] ?? 0)) }}</td>
                            <td>
                                <span class="badge text-bg-light border">{{ $item['status'] ?? '' }}</span>
                            </td>
                            <td class="text-end">
                                @can('edit product item')
                                    <a href="{{ route('product-items.edit', $item['id']) }}" class="btn btn-outline-secondary btn-sm">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                @endcan
                                @can('delete product item')
                                    <form action="{{ route('product-items.destroy', $item['id']) }}" method="POST" class="d-inline"
                                        onsubmit="return confirm(@json(__('ui.datatable.confirm.delete_product_item')));">
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
                            <td colspan="5" class="text-muted text-center py-4">{{ __('web/product-items.empty') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-adminlte4::card>
</x-adminlte4::layouts.app>
