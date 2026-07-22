<x-adminlte4::layouts.app :title="__('web/vtual.claims.staff_registry')">
    <x-adminlte4::card :title="__('web/vtual.claims.staff_registry')">
        <p class="text-muted small mb-3">{{ __('web/vtual.claims.staff_subtitle') }}</p>
        @include('adminlte4.vtual.partials.nav')

        <div class="border rounded p-3 mb-4">
            <h6 class="mb-2">{{ __('web/vtual.claims.orphan_create') }}</h6>
            <p class="text-muted small">{{ __('web/vtual.claims.orphan_help') }}</p>
            <x-adminlte4::form.ajax :action="route('vtual.claims.orphan')" method="POST" class="row g-2 align-items-end">
                <div class="col-md-5" data-form-field="display_name">
                    <label class="form-label" for="orphan_display_name">{{ __('web/vtual.display_name') }}</label>
                    <input type="text" id="orphan_display_name" name="display_name" class="form-control form-control-sm" required maxlength="120">
                    <x-adminlte4::form.error name="display_name" />
                </div>
                <div class="col-md-4" data-form-field="slug">
                    <label class="form-label" for="orphan_slug">{{ __('web/vtual.slug') }}</label>
                    <input type="text" id="orphan_slug" name="slug" class="form-control form-control-sm" maxlength="120">
                    <x-adminlte4::form.error name="slug" />
                </div>
                <div class="col-md-3">
                    <x-adminlte4::form.submit :label="__('web/vtual.create')" class="btn btn-warning btn-sm w-100" />
                </div>
            </x-adminlte4::form.ajax>
        </div>

        <h5 class="mb-2">{{ __('web/vtual.claims.pending_queue') }}</h5>
        <p class="text-muted small mb-2">{{ __('web/vtual.claims.expires_help') }}</p>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" aria-label="{{ __('web/vtual.table.caption_pending_claims') }}">
                <caption class="visually-hidden">{{ __('web/vtual.table.caption_pending_claims') }}</caption>
                <thead>
                    <tr>
                        <th>{{ __('web/vtual.claims.claimant') }}</th>
                        <th>{{ __('web/vtual.claims.profile') }}</th>
                        <th>{{ __('web/vtual.claims.channel') }}</th>
                        <th>{{ __('web/vtual.claims.attempts') }}</th>
                        <th>{{ __('web/vtual.claims.expires') }}</th>
                        <th class="text-end">{{ __('web/vtual.table.label_actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pending as $claim)
                        <tr>
                            <td>
                                {{ $claim['user']['name'] ?? $claim['user']['email'] ?? $claim['user_id'] ?? '—' }}
                                @if (!empty($claim['user']['email']))
                                    <div class="small text-muted">{{ $claim['user']['email'] }}</div>
                                @endif
                            </td>
                            <td>{{ $claim['creator']['display_name'] ?? $claim['creator_id'] ?? '' }}</td>
                            <td class="small">
                                <span class="badge text-bg-light border">
                                    {{ $claim['channel']['platform']['name'] ?? $claim['channel']['platform']['key'] ?? '—' }}
                                </span>
                                <div class="mt-1">
                                    {{ $claim['channel']['handle'] ?? $claim['channel']['display_name'] ?? $claim['channel']['external_id'] ?? '' }}
                                </div>
                            </td>
                            <td>{{ $claim['attempt_count'] ?? 0 }}</td>
                            <td class="small">{{ $claim['expires_at'] ?? '—' }}</td>
                            <td class="text-end text-nowrap">
                                <div class="btn-group" role="group" aria-label="{{ __('web/vtual.table.label_actions') }}">
                                    <a href="{{ route('vtual.claims.show', $claim['id']) }}" class="btn btn-outline-primary btn-sm">
                                        {{ __('web/vtual.view') }}
                                    </a>
                                    @if ($acceptAnywayEnabled)
                                        <x-adminlte4::form.ajax :action="route('vtual.claims.accept-anyway', $claim['id'])" method="POST" class="d-inline">
                                            <button type="button"
                                                    class="btn btn-warning btn-sm"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#vtualConfirmModal"
                                                    data-confirm-title="{{ __('web/vtual.confirm.accept_anyway_title') }}"
                                                    data-confirm-body="{{ __('web/vtual.confirm.accept_anyway_body') }}"
                                                    data-confirm-submit-label="{{ __('web/vtual.claims.accept_anyway') }}"
                                                    data-confirm-submit-class="btn-warning">
                                                {{ __('web/vtual.claims.accept_anyway') }}
                                            </button>
                                        </x-adminlte4::form.ajax>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <x-adminlte4::empty-state icon="bi-inbox" :title="__('web/vtual.claims.empty_pending')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-adminlte4::card>

    @include('adminlte4.vtual.partials._confirm-modal')

    <x-slot:scripts>
        @vite('resources/js/vtual/confirm-modal.js')
    </x-slot:scripts>
</x-adminlte4::layouts.app>
