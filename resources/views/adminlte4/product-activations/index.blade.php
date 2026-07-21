<x-adminlte4::layouts.app :title="__('product-activations.products_title')">
    <x-adminlte4::card :title="__('product-activations.products_title')" :subtitle="__('product-activations.products_subtitle')">
        @if ($products->isEmpty())
            <x-adminlte4::empty-state icon="bi bi-box-seam" :title="__('product-activations.products_empty')" />
        @else
            <div class="row g-3">
                @foreach ($products as $product)
                    @php
                        $key = $product['product_key'] ?? '';
                        $type = $product['activation_type'] ?? '';
                        $counts = $product['counts'] ?? [];
                        $metrics = $product['product_metrics'] ?? [];
                        $typeBadge = $type === 'explicit' ? 'info' : 'light';
                    @endphp
                    <div class="col-12 col-md-6 col-xl-4">
                        <div class="card h-100 shadow-sm">
                            <div class="card-body d-flex flex-column">
                                <div class="d-flex align-items-start justify-content-between mb-2">
                                    <h5 class="card-title mb-0">
                                        <code>{{ $key }}</code>
                                    </h5>
                                    <span class="badge text-bg-{{ $typeBadge }}">
                                        {{ __('product-activations.type_' . ($type ?: 'implicit')) }}
                                    </span>
                                </div>

                                @if (! empty($product['fulfillment_target']))
                                    <p class="text-muted small mb-3">
                                        {{ __('product-activations.fulfillment_target') }}:
                                        <code>{{ $product['fulfillment_target'] }}</code>
                                    </p>
                                @endif

                                <div class="d-flex flex-wrap gap-2 mb-3">
                                    <span class="badge text-bg-success" title="{{ __('product-activations.state_active') }}">
                                        {{ __('product-activations.state_active') }}: {{ $counts['active'] ?? 0 }}
                                    </span>
                                    <span class="badge text-bg-warning" title="{{ __('product-activations.state_pending') }}">
                                        {{ __('product-activations.state_pending') }}: {{ $counts['pending'] ?? 0 }}
                                    </span>
                                    <span class="badge text-bg-secondary" title="{{ __('product-activations.state_suspended') }}">
                                        {{ __('product-activations.state_suspended') }}: {{ $counts['suspended'] ?? 0 }}
                                    </span>
                                    <span class="badge text-bg-danger" title="{{ __('product-activations.state_expired') }}">
                                        {{ __('product-activations.state_expired') }}: {{ $counts['expired'] ?? 0 }}
                                    </span>
                                </div>

                                @if ($metrics !== [])
                                    <ul class="list-unstyled small text-muted mb-3">
                                        @foreach ($metrics as $metricKey => $metricValue)
                                            @php
                                                if (! is_numeric($metricValue) && ! is_string($metricValue)) {
                                                    continue;
                                                }
                                            @endphp
                                            <li>
                                                <span class="fw-semibold">{{ __('product-activations.metric_' . $metricKey, [], $metricKey) }}</span>:
                                                @if (is_numeric($metricValue) && str_ends_with((string) $metricKey, '_bytes'))
                                                    {{ \Illuminate\Support\Number::fileSize((int) $metricValue) }}
                                                @else
                                                    {{ $metricValue }}
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif

                                <div class="mt-auto">
                                    <a href="{{ route('product-activations.show', $key) }}" class="btn btn-outline-primary btn-sm w-100">
                                        <i class="bi bi-graph-up me-1"></i> {{ __('product-activations.view_product') }}
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-adminlte4::card>
</x-adminlte4::layouts.app>
