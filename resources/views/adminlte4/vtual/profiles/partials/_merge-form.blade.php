@php
    $routeKey = $creatorRouteKey ?? $creator['slug'] ?? $creator['id'] ?? '';
@endphp
@if (!empty($canMerge) && isset($mergeTargets) && $mergeTargets->isNotEmpty())
    <h5 class="mb-2">{{ __('web/vtual.profiles.section_merge') }}</h5>
    <x-adminlte4::form.ajax :action="route('vtual.profiles.merge', $routeKey)" method="POST" class="border border-warning rounded p-3 mb-4">
        <p class="small text-muted mb-2">{{ __('web/vtual.profiles.merge_help') }}</p>
        <div class="row g-2 align-items-end">
            <div class="col-md-8" data-form-field="destination_id">
                <label class="form-label" for="destination_id">{{ __('web/vtual.profiles.merge_destination') }}</label>
                <select id="destination_id" name="destination_id" class="form-select form-select-sm" required>
                    <option value="">{{ __('web/vtual.profiles.merge_pick') }}</option>
                    @foreach ($mergeTargets as $target)
                        <option value="{{ $target['id'] }}">
                            {{ $target['display_name'] ?? $target['slug'] ?? $target['id'] }}
                            @if (!empty($target['slug']))
                                ({{ $target['slug'] }})
                            @endif
                        </option>
                    @endforeach
                </select>
                <x-adminlte4::form.error name="destination_id" />
            </div>
            <div class="col-md-4">
                <button type="button"
                        class="btn btn-warning btn-sm w-100"
                        data-bs-toggle="modal"
                        data-bs-target="#vtualConfirmModal"
                        data-confirm-title="{{ __('web/vtual.confirm.merge_title') }}"
                        data-confirm-body="{{ __('web/vtual.confirm.merge_body') }}"
                        data-confirm-submit-label="{{ __('web/vtual.profiles.merge_submit') }}"
                        data-confirm-submit-class="btn-warning">
                    {{ __('web/vtual.profiles.merge_submit') }}
                    <span class="spinner-border spinner-border-sm d-none ms-1" data-form-submit-spinner role="status" aria-hidden="true"></span>
                </button>
            </div>
        </div>
    </x-adminlte4::form.ajax>
@endif
