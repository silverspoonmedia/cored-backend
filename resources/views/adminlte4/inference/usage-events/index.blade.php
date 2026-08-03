@php
    $modelOptions = ['' => '—'] + collect($modelCards)->mapWithKeys(fn (array $c): array => [(string) $c['slug'] => (string) $c['slug']])->all();
@endphp

<x-adminlte4::layouts.app :title="__('web/inference.usage.title')">
    <div class="inf-usage-admin mb-3">
        <h1 class="h4 mb-1">{{ __('web/inference.usage.list_title') }}</h1>
        <p class="text-muted small mb-0">{{ __('web/inference.usage.subtitle') }}</p>
    </div>

    <x-adminlte4::card>
        <form class="row g-2 align-items-end mb-4" data-inference-usage-filters>
            <div class="col-md-2">
                <label class="form-label small" for="inf-from">{{ __('web/inference.usage.filters.from') }}</label>
                <input type="date" class="form-control form-control-sm" id="inf-from" name="from" data-filter-from>
            </div>
            <div class="col-md-2">
                <label class="form-label small" for="inf-to">{{ __('web/inference.usage.filters.to') }}</label>
                <input type="date" class="form-control form-control-sm" id="inf-to" name="to" data-filter-to>
            </div>
            <div class="col-md-2">
                <label class="form-label small" for="inf-model">{{ __('web/inference.usage.filters.model') }}</label>
                <select class="form-select form-select-sm" id="inf-model" name="model_slug" data-filter-model>
                    @foreach ($modelOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small" for="inf-user">{{ __('web/inference.usage.filters.user_id') }}</label>
                <input type="text" class="form-control form-control-sm" id="inf-user" name="user_id" data-filter-user>
            </div>
            <div class="col-md-2">
                <label class="form-label small" for="inf-stage">{{ __('web/inference.usage.filters.stage') }}</label>
                <input type="text" class="form-control form-control-sm" id="inf-stage" name="stage" data-filter-stage>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-sm w-100">{{ __('web/inference.usage.filters.apply') }}</button>
            </div>
        </form>

        <div
            data-inference-usage-report
            data-overview-url="{{ $overviewUrl }}"
            data-events-url="{{ $eventsUrl }}"
        >
            <div class="row g-3 mb-4" data-inference-usage-totals>
                <div class="col-md-2"><div class="card"><div class="card-body py-2"><div class="text-muted small">{{ __('web/inference.usage.totals.events') }}</div><div class="fw-semibold" data-total-events>—</div></div></div></div>
                <div class="col-md-2"><div class="card"><div class="card-body py-2"><div class="text-muted small">{{ __('web/inference.usage.totals.users') }}</div><div class="fw-semibold" data-total-users>—</div></div></div></div>
                <div class="col-md-2"><div class="card"><div class="card-body py-2"><div class="text-muted small">{{ __('web/inference.usage.totals.tokens') }}</div><div class="fw-semibold" data-total-tokens>—</div></div></div></div>
                <div class="col-md-2"><div class="card"><div class="card-body py-2"><div class="text-muted small">{{ __('web/inference.usage.totals.provider_cost') }}</div><div class="fw-semibold" data-total-provider>—</div></div></div></div>
                <div class="col-md-2"><div class="card"><div class="card-body py-2"><div class="text-muted small">{{ __('web/inference.usage.totals.billable') }}</div><div class="fw-semibold" data-total-billable>—</div></div></div></div>
                <div class="col-md-2"><div class="card"><div class="card-body py-2"><div class="text-muted small">{{ __('web/inference.usage.totals.margin') }}</div><div class="fw-semibold" data-total-margin>—</div></div></div></div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <h2 class="h6">{{ __('web/inference.usage.by_model') }}</h2>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0"><tbody data-inference-by-model></tbody></table>
                    </div>
                </div>
                <div class="col-md-4">
                    <h2 class="h6">{{ __('web/inference.usage.by_stage') }}</h2>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0"><tbody data-inference-by-stage></tbody></table>
                    </div>
                </div>
                <div class="col-md-4">
                    <h2 class="h6">{{ __('web/inference.usage.by_provider') }}</h2>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0"><tbody data-inference-by-provider></tbody></table>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-12">
                    <h2 class="h6">{{ __('web/inference.usage.by_source') }}</h2>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0"><tbody data-inference-by-source></tbody></table>
                    </div>
                </div>
            </div>

            <h2 class="h6 mb-2">{{ __('web/inference.usage.events_table') }}</h2>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-2">
                    <thead>
                        <tr>
                            <th>{{ __('web/inference.usage.columns.date') }}</th>
                            <th>{{ __('web/inference.usage.columns.user') }}</th>
                            <th>{{ __('web/inference.usage.columns.model') }}</th>
                            <th>{{ __('web/inference.usage.columns.provider') }}</th>
                            <th>{{ __('web/inference.usage.columns.stage') }}</th>
                            <th>{{ __('web/inference.usage.columns.tokens') }}</th>
                            <th>{{ __('web/inference.usage.columns.billable') }}</th>
                            <th>{{ __('web/inference.usage.columns.snapshot') }}</th>
                        </tr>
                    </thead>
                    <tbody data-inference-events-body>
                        <tr><td colspan="8" class="text-muted">{{ __('web/inference.usage.loading') }}</td></tr>
                    </tbody>
                </table>
            </div>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-inference-load-more hidden>{{ __('web/inference.usage.load_more') }}</button>
        </div>
    </x-adminlte4::card>

    @vite('resources/css/inference/rate-plans-admin.css')

    <x-slot:scripts>
        @vite('resources/js/inference/usage-events.js')
    </x-slot:scripts>
</x-adminlte4::layouts.app>
