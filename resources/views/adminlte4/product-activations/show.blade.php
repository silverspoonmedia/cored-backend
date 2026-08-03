<x-adminlte4::layouts.app :title="__('product-activations.product_title', ['product' => $productKey])">
    @php
        $summary = $summary ?? [];
        $counts = $summary['counts'] ?? [];
        $metrics = $summary['product_metrics'] ?? [];
        $type = $summary['activation_type'] ?? '';
        $fulfillment = $summary['fulfillment_target'] ?? null;
        $canManage = \Illuminate\Support\Facades\Gate::allows('manage product activations');
    @endphp

    <x-adminlte4::card :title="__('product-activations.product_title', ['product' => $productKey])">
        <x-slot:actions>
            <a href="{{ route('product-activations.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> {{ __('product-activations.back_to_products') }}
            </a>
        </x-slot:actions>

        <dl class="row mb-4">
            <dt class="col-sm-3">{{ __('product-activations.col_product') }}</dt>
            <dd class="col-sm-9"><code>{{ $productKey }}</code></dd>
            <dt class="col-sm-3">{{ __('product-activations.col_type') }}</dt>
            <dd class="col-sm-9">
                <span class="badge text-bg-{{ $type === 'explicit' ? 'info' : 'light' }}">
                    {{ __('product-activations.type_' . ($type ?: 'implicit')) }}
                </span>
            </dd>
            @if ($fulfillment !== null)
                <dt class="col-sm-3">{{ __('product-activations.fulfillment_target') }}</dt>
                <dd class="col-sm-9"><code>{{ $fulfillment }}</code></dd>
            @endif
        </dl>

        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="border rounded p-3 text-center">
                    <div class="h4 mb-0 text-success">{{ $counts['active'] ?? 0 }}</div>
                    <div class="small text-muted">{{ __('product-activations.state_active') }}</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="border rounded p-3 text-center">
                    <div class="h4 mb-0 text-warning">{{ $counts['pending'] ?? 0 }}</div>
                    <div class="small text-muted">{{ __('product-activations.state_pending') }}</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="border rounded p-3 text-center">
                    <div class="h4 mb-0 text-secondary">{{ $counts['suspended'] ?? 0 }}</div>
                    <div class="small text-muted">{{ __('product-activations.state_suspended') }}</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="border rounded p-3 text-center">
                    <div class="h4 mb-0 text-danger">{{ $counts['expired'] ?? 0 }}</div>
                    <div class="small text-muted">{{ __('product-activations.state_expired') }}</div>
                </div>
            </div>
        </div>

        @if ($metrics !== [])
            <div class="row g-3 mb-4">
                @foreach ($metrics as $metricKey => $metricValue)
                    @php
                        // Skip nested structures (by_tier) — rendered separately below.
                        if (! is_numeric($metricValue) && ! is_string($metricValue)) {
                            continue;
                        }
                    @endphp
                    <div class="col-6 col-md-3">
                        <div class="border rounded p-3 text-center h-100">
                            <div class="h5 mb-0">
                                @if (is_numeric($metricValue) && str_ends_with((string) $metricKey, '_bytes'))
                                    {{ \Illuminate\Support\Number::fileSize((int) $metricValue) }}
                                @else
                                    {{ $metricValue }}
                                @endif
                            </div>
                            <div class="small text-muted">{{ __('product-activations.metric_' . $metricKey, [], $metricKey) }}</div>
                        </div>
                    </div>
                @endforeach
            </div>

            @if (isset($metrics['by_tier']) && is_array($metrics['by_tier']))
                <div class="table-responsive mb-4">
                    <table class="table table-sm table-bordered mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('product-activations.tier') }}</th>
                                <th class="text-end">{{ __('product-activations.metric_storage_used_bytes') }}</th>
                                <th class="text-end">{{ __('product-activations.metric_file_count') }}</th>
                                <th class="text-end">{{ __('product-activations.metric_workspace_count_owned') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($metrics['by_tier'] as $tier => $tierMetrics)
                                <tr>
                                    <td>
                                        <span class="badge text-bg-{{ match ($tier) { 'paid' => 'primary', 'grace' => 'warning', 'free' => 'secondary', default => 'light' } }}">
                                            {{ __('product-activations.tier_' . $tier, [], $tier) }}
                                        </span>
                                    </td>
                                    <td class="text-end">{{ \Illuminate\Support\Number::fileSize((int) ($tierMetrics['storage_used_bytes'] ?? 0)) }}</td>
                                    <td class="text-end">{{ $tierMetrics['file_count'] ?? 0 }}</td>
                                    <td class="text-end">{{ $tierMetrics['workspaces'] ?? 0 }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif

        <form method="GET" action="{{ route('product-activations.show', $productKey) }}" class="d-flex align-items-center gap-2 mb-3">
            <label for="state-filter" class="form-label mb-0 small">{{ __('product-activations.filter_state') }}</label>
            <select id="state-filter" name="state" class="form-select form-select-sm" style="max-width: 200px;" onchange="this.form.submit()">
                <option value="" @selected($stateFilter === '')>{{ __('product-activations.filter_all') }}</option>
                <option value="active" @selected($stateFilter === 'active')>{{ __('product-activations.state_active') }}</option>
                <option value="pending" @selected($stateFilter === 'pending')>{{ __('product-activations.state_pending') }}</option>
                <option value="suspended" @selected($stateFilter === 'suspended')>{{ __('product-activations.state_suspended') }}</option>
                <option value="expired" @selected($stateFilter === 'expired')>{{ __('product-activations.state_expired') }}</option>
            </select>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('product-activations.col_user') }}</th>
                        <th>{{ __('product-activations.col_state') }}</th>
                        <th>{{ __('product-activations.col_source') }}</th>
                        <th>{{ __('product-activations.col_activated_at') }}</th>
                        <th>{{ __('product-activations.col_updated_at') }}</th>
                        <th class="text-end">{{ __('product-activations.col_actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($activations as $activation)
                        @php
                            $state = $activation['state'] ?? '';
                            $stateBadge = match ($state) {
                                'active' => 'success',
                                'pending' => 'warning',
                                'suspended' => 'secondary',
                                'expired' => 'danger',
                                default => 'light',
                            };
                            $user = $activation['user'] ?? [];
                            $activationId = $activation['id'] ?? '';
                        @endphp
                        <tr>
                            <td>
                                @if (! empty($user))
                                    <div class="fw-semibold">{{ $user['name'] ?? '' }}</div>
                                    <div class="small text-muted">{{ $user['email'] ?? '' }}</div>
                                @else
                                    <code>{{ $activation['user_id'] ?? '' }}</code>
                                @endif
                            </td>
                            <td>
                                <span class="badge text-bg-{{ $stateBadge }}">
                                    {{ __('product-activations.state_' . $state) }}
                                </span>
                            </td>
                            <td>{{ $activation['source'] ?? '—' }}</td>
                            <td>{{ $activation['activated_at_human'] ?? $activation['activated_at_formatted'] ?? $activation['activated_at'] ?? '—' }}</td>
                            <td>{{ $activation['updated_at_human'] ?? $activation['updated_at_formatted'] ?? $activation['updated_at'] ?? '—' }}</td>
                            <td class="text-end">
                                @if ($canManage)
                                    @if ($state === 'active')
                                        <form action="{{ route('product-activations.suspend', [$productKey, $activationId]) }}" method="POST" class="d-inline"
                                            data-swal-confirm="@json(__('product-activations.action_suspend'))">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-warning btn-sm" title="{{ __('product-activations.action_suspend') }}">
                                                <i class="bi bi-pause-fill"></i>
                                            </button>
                                        </form>
                                    @elseif ($state === 'suspended')
                                        <form action="{{ route('product-activations.reactivate', [$productKey, $activationId]) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-success btn-sm" title="{{ __('product-activations.action_reactivate') }}">
                                                <i class="bi bi-play-fill"></i>
                                            </button>
                                        </form>
                                    @elseif ($state === 'pending')
                                        <form action="{{ route('product-activations.approve', [$productKey, $activationId]) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-success btn-sm" title="{{ __('product-activations.action_approve') }}">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                        </form>
                                        <form action="{{ route('product-activations.reject', [$productKey, $activationId]) }}" method="POST" class="d-inline"
                                            data-swal-confirm="@json(__('product-activations.action_reject'))">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-danger btn-sm" title="{{ __('product-activations.action_reject') }}">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        </form>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <x-adminlte4::empty-state icon="bi bi-people" :title="__('product-activations.no_activations')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if (($pagination['last_page'] ?? 1) > 1)
            <nav class="mt-3" aria-label="{{ __('product-activations.pagination') }}">
                <ul class="pagination justify-content-center">
                    @for ($i = 1; $i <= $pagination['last_page']; $i++)
                        <li class="page-item {{ $i === ($pagination['current_page'] ?? 1) ? 'active' : '' }}">
                            <a class="page-link" href="{{ route('product-activations.show', $productKey) . '?' . http_build_query(array_filter(['state' => $stateFilter, 'page' => $i])) }}">{{ $i }}</a>
                        </li>
                    @endfor
                </ul>
            </nav>
        @endif
    </x-adminlte4::card>
</x-adminlte4::layouts.app>
