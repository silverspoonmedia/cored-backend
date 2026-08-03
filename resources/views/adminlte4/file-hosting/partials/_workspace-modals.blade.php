    {{-- Create Team Workspace Modal (top-level: avoid sticky toolbar stacking context) --}}
    @if ($canCreateWorkspace)
        <div class="modal fade" id="fhCreateWorkspaceModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form method="post" action="{{ route('file-hosting.workspaces.store') }}" class="modal-content">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('web/file-hosting.collab.create_workspace') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="small text-muted">{{ __('web/file-hosting.collab.create_hint') }}</p>
                        <label class="form-label">{{ __('web/file-hosting.collab.workspace_name') }}</label>
                        <input type="text" name="name" class="form-control" required maxlength="255"
                               value="{{ old('name', __('web/file-hosting.collab.default_workspace_name')) }}">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('web/file-hosting.cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('web/file-hosting.create') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Create Microstock Workspace Modal (name input; slot gate handled client-side) --}}
    <div class="modal fade" id="fhCreateMicrostockModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-images me-1"></i>{{ __('web/file-hosting.microstock.create_title') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted">{{ __('web/file-hosting.microstock.create_hint') }}</p>
                    <label class="form-label" for="fhMicrostockName">{{ __('web/file-hosting.microstock.name_label') }}</label>
                    <input type="text" id="fhMicrostockName" class="form-control" maxlength="255"
                           placeholder="{{ __('web/file-hosting.microstock.name_placeholder') }}">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('web/file-hosting.cancel') }}</button>
                    <button type="button" class="btn btn-primary" data-fh-microstock-modal-submit>{{ __('web/file-hosting.create') }}</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Microstock Collection: Lock & Describe Modal --}}
    <div class="modal fade" id="fhCollectionDescribeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-lock me-1"></i>{{ __('web/file-hosting.microstock.describe_title') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-1">{{ __('web/file-hosting.microstock.describe_hint') }}</p>
                    <p class="small text-muted mb-3">{{ __('web/file-hosting.microstock.describe_sections_intro') }}</p>
                    <form data-fh-collection-describe-form novalidate>
                        <div class="mb-3" data-fh-describe-group="primary">
                            <label class="form-label" for="fhDescribePrimary">{{ __('web/file-hosting.microstock.describe_primary_concept') }}</label>
                            <textarea id="fhDescribePrimary" class="form-control" rows="3" data-fh-describe-primary
                                      placeholder="{{ __('web/file-hosting.microstock.describe_primary_placeholder') }}"></textarea>
                            <div class="invalid-feedback">{{ __('web/file-hosting.microstock.describe_validation_primary', ['min' => 10]) }}</div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6" data-fh-describe-group="visible">
                                <label class="form-label d-block small">{{ __('web/file-hosting.microstock.describe_visible_facts') }}</label>
                                <div class="d-flex flex-wrap gap-1 mb-2">
                                    @foreach (__('web/file-hosting.microstock.describe_visible_options') as $key => $label)
                                        <input type="checkbox" class="btn-check" id="fhDescribeVisible{{ $key }}"
                                               value="{{ $key }}" data-fh-describe-visible autocomplete="off">
                                        <label class="btn btn-outline-secondary btn-sm" for="fhDescribeVisible{{ $key }}">{{ $label }}</label>
                                    @endforeach
                                </div>
                                <input type="text" class="form-control form-control-sm" data-fh-describe-visible-other
                                       placeholder="{{ __('web/file-hosting.microstock.describe_other_placeholder') }}">
                                <div class="invalid-feedback">{{ __('web/file-hosting.microstock.describe_validation_visible') }}</div>
                            </div>
                            <div class="col-md-6" data-fh-describe-group="intent">
                                <label class="form-label d-block small">{{ __('web/file-hosting.microstock.describe_buyer_intent') }}</label>
                                <div class="d-flex flex-wrap gap-1 mb-2">
                                    @foreach (__('web/file-hosting.microstock.describe_intent_options') as $key => $label)
                                        <input type="checkbox" class="btn-check" id="fhDescribeIntent{{ $key }}"
                                               value="{{ $key }}" data-fh-describe-intent autocomplete="off">
                                        <label class="btn btn-outline-secondary btn-sm" for="fhDescribeIntent{{ $key }}">{{ $label }}</label>
                                    @endforeach
                                </div>
                                <input type="text" class="form-control form-control-sm" data-fh-describe-intent-other
                                       placeholder="{{ __('web/file-hosting.microstock.describe_other_placeholder') }}">
                                <div class="invalid-feedback">{{ __('web/file-hosting.microstock.describe_validation_intent') }}</div>
                            </div>
                        </div>
                        <div class="mt-3" data-fh-describe-group="style">
                            <label class="form-label d-block small">{{ __('web/file-hosting.microstock.describe_style_mood') }}</label>
                            <div class="d-flex flex-wrap gap-1 mb-2">
                                @foreach (__('web/file-hosting.microstock.describe_style_options') as $key => $label)
                                    <input type="checkbox" class="btn-check" id="fhDescribeStyle{{ $key }}"
                                           value="{{ $key }}" data-fh-describe-style autocomplete="off">
                                    <label class="btn btn-outline-secondary btn-sm" for="fhDescribeStyle{{ $key }}">{{ $label }}</label>
                                @endforeach
                            </div>
                            <input type="text" class="form-control form-control-sm" data-fh-describe-style-other
                                   placeholder="{{ __('web/file-hosting.microstock.describe_other_placeholder') }}">
                            <div class="invalid-feedback">{{ __('web/file-hosting.microstock.describe_validation_style') }}</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="fhDescribeNotes">{{ __('web/file-hosting.microstock.describe_additional_notes') }}</label>
                            <textarea id="fhDescribeNotes" class="form-control" rows="2" data-fh-describe-notes
                                      placeholder="{{ __('web/file-hosting.microstock.describe_additional_placeholder') }}"></textarea>
                        </div>
                        <div class="mb-3 {{ setting_config('microstock.daily_free.enabled', false) ? '' : 'd-none' }}" data-fh-describe-billing-group>
                            <label class="form-label">{{ __('web/file-hosting.microstock.describe_billing_mode_label') }}</label>
                            <p class="small text-muted mb-2">{{ __('web/file-hosting.microstock.describe_billing_mode_hint') }}</p>
                            <div class="d-flex flex-wrap gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="fhDescribeBillingMode" id="fhDescribeBillingFree" value="free" data-fh-describe-billing>
                                    <label class="form-check-label" for="fhDescribeBillingFree">{{ __('web/file-hosting.microstock.describe_billing_mode_free') }}</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="fhDescribeBillingMode" id="fhDescribeBillingPaid" value="paid" data-fh-describe-billing>
                                    <label class="form-check-label" for="fhDescribeBillingPaid">{{ __('web/file-hosting.microstock.describe_billing_mode_paid') }}</label>
                                </div>
                            </div>
                            <div class="invalid-feedback">{{ __('web/file-hosting.microstock.describe_validation_billing_mode') }}</div>
                        </div>
                        <div class="border rounded p-2 bg-light">
                            <button class="btn btn-link btn-sm p-0 text-decoration-none" type="button"
                                    data-bs-toggle="collapse" data-bs-target="#fhDescribePreviewCollapse"
                                    aria-expanded="false" aria-controls="fhDescribePreviewCollapse">
                                {{ __('web/file-hosting.microstock.describe_preview') }}
                            </button>
                            <div class="collapse" id="fhDescribePreviewCollapse">
                                <pre class="small mb-1 mt-2 text-wrap" style="white-space: pre-wrap;" data-fh-describe-preview-text>â€”</pre>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-3">
                        <span class="small text-muted" data-fh-describe-char-count>0 / 2000</span>
                        <div class="small text-muted d-none" data-fh-collection-billing>
                            <span data-fh-collection-billing-text></span>
                            <a href="{{ route('file-hosting.microstock.usage', array_filter(['workspace_id' => $workspaceId])) }}"
                               class="ms-1" data-fh-collection-billing-link>{{ __('web/microstock.usage.billing_history_link') }}</a>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('web/file-hosting.cancel') }}</button>
                        <button type="button" class="btn btn-primary" data-fh-collection-describe-submit>{{ __('web/file-hosting.microstock.describe_submit') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
