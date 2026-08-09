    {{-- Microstock asset review side panel (offcanvas) --}}
    <div class="offcanvas offcanvas-end" tabindex="-1" id="fhMicrostockPanel" aria-labelledby="fhMicrostockPanelLabel" data-fh-microstock-panel>
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="fhMicrostockPanelLabel"><i class="bi bi-stars me-1"></i>{{ __('web/file-hosting.microstock.panel_title') }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                <span class="badge text-bg-secondary" data-fh-ms-status></span>
                <div class="d-flex align-items-center gap-1 small text-muted" data-fh-ms-stages aria-label="{{ __('web/file-hosting.microstock.stage_strip_label') }}"></div>
            </div>
            <div class="text-center mb-3">
                <img data-fh-ms-thumb alt="" class="img-fluid rounded border" style="max-height:200px; object-fit:contain;">
            </div>
            <div class="alert alert-danger d-none mb-3 py-2 small" data-fh-ms-error role="alert"></div>
            <div class="mb-3">
                <label class="form-label" for="fhMsTitle">{{ __('web/file-hosting.microstock.panel_title_label') }}</label>
                <input type="text" class="form-control" id="fhMsTitle" maxlength="255">
            </div>
            <div class="mb-3">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <label class="form-label mb-0">{{ __('web/file-hosting.microstock.panel_keywords_label') }}</label>
                    <span class="badge text-bg-light border small" data-fh-ms-keyword-count>0</span>
                </div>
                <div class="d-flex flex-wrap gap-1 mb-2" data-fh-ms-chips></div>
                <div class="d-flex gap-1 mb-1">
                    <input type="text" class="form-control form-control-sm" id="fhMsKeywordInput" placeholder="{{ __('web/file-hosting.microstock.panel_keyword_placeholder') }}">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-fh-ms-keyword-clear title="{{ __('web/file-hosting.microstock.panel_keyword_clear') }}">
                        <i class="bi bi-eraser"></i>
                    </button>
                </div>
            </div>
            <div class="mb-3">
                <button class="btn btn-link btn-sm p-0 text-decoration-none text-muted d-flex align-items-center gap-1" type="button"
                        data-bs-toggle="collapse" data-bs-target="#fhMsAiDetails" aria-expanded="false" aria-controls="fhMsAiDetails">
                    <i class="bi bi-chevron-right small" data-fh-ms-details-chevron></i>
                    {{ __('web/file-hosting.microstock.panel_ai_details') }}
                </button>
                <div class="collapse mt-2" id="fhMsAiDetails">
                    <div class="mb-2">
                        <label class="form-label small text-muted mb-0">{{ __('web/file-hosting.microstock.panel_description_label') }}</label>
                        <div class="form-control-plaintext small border rounded px-2 py-1 bg-light" data-fh-ms-description>â€”</div>
                    </div>
                    <div>
                        <label class="form-label small text-muted mb-0">{{ __('web/file-hosting.microstock.panel_category_label') }}</label>
                        <div class="form-control-plaintext small border rounded px-2 py-1 bg-light" data-fh-ms-category>â€”</div>
                    </div>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-primary" data-fh-ms-save>{{ __('web/file-hosting.microstock.panel_save') }}</button>
                <button type="button" class="btn btn-outline-secondary" data-fh-ms-reanalyze>{{ __('web/file-hosting.microstock.panel_reanalyze') }}</button>
            </div>
        </div>
    </div>
