<x-adminlte4::layouts.app :title="__('web/services.title')">
    @if ($microstockFree || $microstockSlot || $microstockAiCredits)
        <x-adminlte4::card :title="__('web/services.microstock.title')" class="mb-3">
            <p class="text-muted mb-3">{{ __('web/services.microstock.slot_desc') }}</p>
            @if ($microstockActive && is_array($microstockBilling))
                <p class="small mb-3">
                    {{ __('web/services.microstock.ai_balance', [
                        'balance' => number_format((float) ($microstockBilling['balance_usd'] ?? 0), 2),
                        'estimate' => number_format((float) ($microstockBilling['estimated_per_analysis_usd'] ?? 0), 2),
                    ]) }}
                    <a href="{{ route('file-hosting.microstock.usage') }}" class="ms-2">
                        {{ __('web/services.microstock.usage_balance') }}
                    </a>
                </p>
            @endif
            <div class="d-flex flex-wrap gap-2">
                @if ($microstockFree)
                    @if ($microstockActive)
                        <span class="badge text-bg-success align-self-center px-3 py-2">
                            <i class="bi bi-check-circle me-1"></i> {{ __('web/services.microstock.active_badge') }}
                        </span>
                    @else
                        <form action="{{ route('services.checkout', $microstockFree['id']) }}" method="POST" class="d-inline">
                            @csrf
                            <input type="hidden" name="currency" value="USD">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="bi bi-lightning-charge me-1"></i> {{ __('web/services.microstock.activate_free') }}
                            </button>
                        </form>
                    @endif
                @endif
                @if ($microstockSlot && $microstockActive)
                    <form action="{{ route('services.checkout', $microstockSlot['id']) }}" method="POST" class="d-inline">
                        @csrf
                        <input type="hidden" name="currency" value="USD">
                        <button type="submit" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-plus-lg me-1"></i> {{ __('web/services.microstock.buy_slot') }}
                        </button>
                    </form>
                @endif
                @if ($microstockAiCredits && $microstockActive)
                    <form action="{{ route('services.checkout', $microstockAiCredits['id']) }}" method="POST" class="d-inline">
                        @csrf
                        <input type="hidden" name="currency" value="USD">
                        <button type="submit" class="btn btn-outline-success btn-sm">
                            <i class="bi bi-coin me-1"></i> {{ __('web/services.microstock.buy_ai_credits') }}
                        </button>
                    </form>
                @endif
            </div>
        </x-adminlte4::card>
    @endif

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
                                ->map(fn ($p) => $p['amount_formatted'] ?? trim(($p['currency'] ?? '').' '.($p['amount'] ?? '')))
                                ->filter()
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
                                <div class="btn-group btn-group-sm" role="group" aria-label="{{ __('ui.labels.actions') }}">
                                    <a href="{{ route('services.show', $service['id']) }}" class="btn btn-outline-info btn-sm"
                                        aria-label="{{ __('ui.actions.view') }} {{ $service['name'] ?? '' }}"
                                        title="{{ __('ui.actions.view') }}">
                                        <i class="bi bi-eye" aria-hidden="true"></i>
                                    </a>
                                    @can('manage service')
                                        <a href="{{ route('services.edit', $service['id']) }}" class="btn btn-outline-secondary btn-sm"
                                            aria-label="{{ __('ui.actions.edit') }} {{ $service['name'] ?? '' }}"
                                            title="{{ __('ui.actions.edit') }}">
                                            <i class="bi bi-pencil" aria-hidden="true"></i>
                                        </a>
                                        <form action="{{ route('services.destroy', $service['id']) }}" method="POST" class="d-inline-block"
                                            data-swal-confirm="{{ __('web/services.confirm_delete') }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-sm"
                                                aria-label="{{ __('ui.actions.delete') }} {{ $service['name'] ?? '' }}"
                                                title="{{ __('ui.actions.delete') }}">
                                                <i class="bi bi-trash" aria-hidden="true"></i>
                                                <span class="visually-hidden" data-form-submit-spinner><span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span></span>
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <x-adminlte4::empty-state icon="bi-box-seam" :title="__('web/services.empty')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-adminlte4::card>
</x-adminlte4::layouts.app>
