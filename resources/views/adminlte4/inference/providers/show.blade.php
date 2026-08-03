@php
    $rates = collect($provider['model_rates'] ?? []);
    $modelCardOptions = collect($modelCards)->mapWithKeys(fn (array $c): array => [(string) $c['id'] => (string) ($c['slug'] ?? $c['id'])])->all();
@endphp

<x-adminlte4::layouts.app :title="__('web/inference.providers.show_title')">
  @php
      \Diglactic\Breadcrumbs\Breadcrumbs::setCurrentRoute('inference.providers.show', $provider['id']);
  @endphp
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h4 mb-0">{{ $provider['name'] }}</h1>
            <div class="d-flex flex-wrap gap-2 mt-1">
                <span class="badge text-bg-light border"><code>{{ $provider['slug'] }}</code></span>
                @if ($provider['is_active'] ?? true)
                    <span class="badge text-bg-success">Active</span>
                @else
                    <span class="badge text-bg-secondary">Inactive</span>
                @endif
            </div>
        </div>
        @can('manage inference catalog')
            <a href="{{ route('inference.providers.edit', $provider['id']) }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-pencil me-1"></i>{{ __('ui.actions.edit') }}
            </a>
        @endcan
    </div>

    <div class="inf-rate-plan-admin" data-inference-rate-plan-admin data-sync-url="{{ $syncRatesUrl }}">
        <x-adminlte4::card :title="__('web/inference.providers.rates_title')">
            <p class="text-muted small mb-3">{{ __('web/inference.providers.rates_lead') }}</p>

            @can('manage inference catalog')
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-inference-add-entry>
                        <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('web/inference.providers.add_rate') }}
                    </button>
                    <button type="button" class="btn btn-primary btn-sm" data-inference-save-entries>
                        <i class="bi bi-check-lg me-1" aria-hidden="true"></i>{{ __('web/inference.providers.save_rates') }}
                        <span class="visually-hidden" data-form-submit-spinner><span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span></span>
                    </button>
                </div>
            @endcan

            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Model</th>
                            <th>{{ __('web/inference.providers.input_rate') }}</th>
                            <th>{{ __('web/inference.providers.output_rate') }}</th>
                            @can('manage inference catalog')
                                <th></th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody data-inference-entries-body>
                        @forelse ($rates as $rate)
                            @php
                                $modelCard = $rate['model_card'] ?? [];
                            @endphp
                            <tr data-inference-entry-row data-model-card-id="{{ $rate['model_card_id'] }}">
                                <td><code>{{ $modelCard['slug'] ?? $rate['model_card_id'] }}</code></td>
                                <td>
                                    <input type="number" class="form-control form-control-sm" min="0" step="0.0001" data-entry-input value="{{ $rate['input_per_million_usd'] }}" @cannot('manage inference catalog') readonly @endcannot>
                                </td>
                                <td>
                                    <input type="number" class="form-control form-control-sm" min="0" step="0.0001" data-entry-output value="{{ $rate['output_per_million_usd'] }}" @cannot('manage inference catalog') readonly @endcannot>
                                </td>
                                @can('manage inference catalog')
                                    <td class="text-end">
                                        <button type="button" class="btn btn-outline-danger btn-sm" data-inference-remove-entry
                                                aria-label="{{ __('web/inference.providers.remove_rate') }}"
                                                title="{{ __('web/inference.providers.remove_rate') }}">
                                            <i class="bi bi-trash" aria-hidden="true"></i>
                                        </button>
                                    </td>
                                @endcan
                            </tr>
                        @empty
                            <tr data-inference-empty-row>
                                <td colspan="4" class="text-muted">{{ __('web/inference.providers.no_rates') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <template data-inference-entry-template>
                <tr data-inference-entry-row data-new-entry="1">
                    <td>
                        <select class="form-select form-select-sm" data-entry-model-select>
                            @foreach ($modelCardOptions as $id => $slug)
                                <option value="{{ $id }}">{{ $slug }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td><input type="number" class="form-control form-control-sm" min="0" step="0.0001" data-entry-input value="0"></td>
                    <td><input type="number" class="form-control form-control-sm" min="0" step="0.0001" data-entry-output value="0"></td>
                    <td class="text-end">
                        <button type="button" class="btn btn-outline-danger btn-sm" data-inference-remove-entry
                                aria-label="{{ __('web/inference.providers.remove_rate') }}"
                                title="{{ __('web/inference.providers.remove_rate') }}">
                            <i class="bi bi-trash" aria-hidden="true"></i>
                        </button>
                    </td>
                </tr>
            </template>

            <div class="monolith-toast-container mt-2" data-inference-admin-toast role="status" aria-live="polite"></div>
        </x-adminlte4::card>
    </div>

    @vite('resources/css/inference/rate-plans-admin.css')

    <x-slot:scripts>
        @vite('resources/js/inference/rate-plans-admin.js')
    </x-slot:scripts>
</x-adminlte4::layouts.app>
