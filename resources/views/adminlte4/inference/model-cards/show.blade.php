@php
    $usage = $card['usage_summary'] ?? [];
    $providerRate = $card['provider_rate'] ?? [];
@endphp

<x-adminlte4::layouts.app :title="__('web/inference.model_cards.show_title')">
  @php
      \Diglactic\Breadcrumbs\Breadcrumbs::setCurrentRoute('inference.model-cards.show', $card['id']);
  @endphp
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h4 mb-0"><code>{{ $card['slug'] }}</code></h1>
            <p class="text-muted small mb-0">{{ $card['display_name'] ?? '—' }}</p>
        </div>
        @can('manage inference catalog')
            <a href="{{ route('inference.model-cards.edit', $card['id']) }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-pencil me-1"></i>{{ __('ui.actions.edit') }}
            </a>
        @endcan
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">{{ __('web/inference.model_cards.fields.is_active') }}</div>
                    <div class="fw-semibold">
                        @if ($card['is_active'])
                            <span class="badge text-bg-success">Active</span>
                        @else
                            <span class="badge text-bg-secondary">Inactive</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">{{ __('web/inference.model_cards.provider_rate') }}</div>
                    <div class="small fw-semibold">
                        In ${{ number_format((float) ($card['provider_rate']['input_per_million_usd'] ?? 0), 2) }}
                        · Out ${{ number_format((float) ($card['provider_rate']['output_per_million_usd'] ?? 0), 2) }}
                    </div>
                    @if (! empty($card['provider_rate']['provider_slug']))
                        <div class="text-muted small mt-1"><code>{{ $card['provider_rate']['provider_slug'] }}</code></div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">{{ __('web/inference.usage.totals.events') }}</div>
                    <div class="fs-5 fw-semibold">{{ (int) ($usage['event_count'] ?? 0) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">{{ __('web/inference.usage.totals.tokens') }}</div>
                    <div class="fs-5 fw-semibold">{{ number_format((int) ($usage['total_tokens'] ?? 0)) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">{{ __('web/inference.usage.totals.billable') }}</div>
                    <div class="fs-5 fw-semibold">{{ $usage['billable_usd'] ?? '—' }}</div>
                </div>
            </div>
        </div>
    </div>

    <x-adminlte4::card :title="__('web/inference.model_cards.recent_usage')">
        <div data-inference-card-usage data-events-url="{{ $usageEventsUrl }}" data-model-card-id="{{ $card['id'] }}">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('web/inference.usage.columns.date') }}</th>
                            <th>{{ __('web/inference.usage.columns.user') }}</th>
                            <th>{{ __('web/inference.usage.columns.stage') }}</th>
                            <th>{{ __('web/inference.usage.columns.tokens') }}</th>
                            <th>{{ __('web/inference.usage.columns.billable') }}</th>
                            <th>{{ __('web/inference.usage.columns.snapshot') }}</th>
                        </tr>
                    </thead>
                    <tbody data-inference-card-usage-body>
                        <tr><td colspan="6" class="text-muted">{{ __('web/inference.usage.loading') }}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </x-adminlte4::card>

    <x-slot:scripts>
        @vite('resources/js/inference/usage-events.js')
    </x-slot:scripts>
</x-adminlte4::layouts.app>
