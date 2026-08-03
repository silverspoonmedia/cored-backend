    {{-- Microstock Usage & Balance Offcanvas (SPA-integrated) --}}
    <div class="offcanvas offcanvas-end fh-ms-usage-offcanvas" tabindex="-1"
         id="fhMicrostockUsagePanel" aria-labelledby="fhMicrostockUsagePanelLabel"
         data-fh-ms-usage-panel>
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="fhMicrostockUsagePanelLabel">
                <i class="bi bi-wallet2 me-1"></i>{{ __('web/microstock.usage.title') }}
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <p class="text-muted small mb-3">{{ __('web/microstock.usage.subtitle') }}</p>

            {{-- Summary cards --}}
            <div class="row g-2 mb-3">
                <div class="col-6">
                    <div class="card h-100"><div class="card-body py-2 px-3">
                        <div class="text-muted small">{{ __('web/microstock.usage.balance') }}</div>
                        <div class="fs-5 fw-semibold" data-fh-msu-balance>â€”</div>
                    </div></div>
                </div>
                <div class="col-6">
                    <div class="card h-100"><div class="card-body py-2 px-3">
                        <div class="text-muted small">{{ __('web/microstock.usage.estimate') }}</div>
                        <div class="fs-6 fw-semibold" data-fh-msu-estimate>â€”</div>
                    </div></div>
                </div>
            </div>
            <div class="row g-2 mb-3 d-none" data-fh-msu-daily-card>
                <div class="col-12">
                    <div class="card"><div class="card-body py-2 px-3">
                        <div class="text-muted small">{{ __('web/microstock.usage.daily_free') }}</div>
                        <div class="fs-6 fw-semibold" data-fh-msu-daily>â€”</div>
                    </div></div>
                </div>
            </div>

            {{-- Buy credits link --}}
            <div class="mb-3">
                <a href="{{ route('services.index') }}" class="btn btn-outline-primary btn-sm w-100" data-fh-msu-buy-credits>
                    <i class="bi bi-coin me-1"></i>{{ __('web/microstock.usage.buy_credits') }}
                </a>
            </div>

            {{-- Tabs --}}
            <ul class="nav nav-tabs mb-2" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="fh-msu-ledger-tab" data-bs-toggle="tab"
                            data-bs-target="#fh-msu-ledger-pane" type="button" role="tab">
                        {{ __('web/microstock.usage.tab_ledger') }}
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="fh-msu-usage-tab" data-bs-toggle="tab"
                            data-bs-target="#fh-msu-usage-pane" type="button" role="tab">
                        {{ __('web/microstock.usage.tab_usage') }}
                    </button>
                </li>
                @if ($canViewPlatformBilling ?? false)
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="fh-msu-platform-tab" data-bs-toggle="tab"
                                data-bs-target="#fh-msu-platform-pane" type="button" role="tab">
                            {{ __('web/microstock.usage.tab_platform') }}
                        </button>
                    </li>
                @endif
            </ul>

            {{-- Tab panes --}}
            <div class="tab-content">
                <div class="tab-pane fade show active" id="fh-msu-ledger-pane" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead><tr>
                                <th>{{ __('web/microstock.usage.col_date') }}</th>
                                <th>{{ __('web/microstock.usage.col_description') }}</th>
                                <th class="text-end">{{ __('web/microstock.usage.col_amount') }}</th>
                                <th class="text-end">{{ __('web/microstock.usage.col_balance') }}</th>
                            </tr></thead>
                            <tbody data-fh-msu-ledger-body>
                                <tr><td colspan="4" class="text-muted">{{ __('web/microstock.usage.loading') }}</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-center mt-2 d-none" data-fh-msu-ledger-more-wrap>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-fh-msu-ledger-more>
                            {{ __('web/microstock.usage.load_more') }}
                        </button>
                    </div>
                </div>

                <div class="tab-pane fade" id="fh-msu-usage-pane" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead><tr>
                                <th>{{ __('web/microstock.usage.col_date') }}</th>
                                <th>{{ __('web/microstock.usage.col_file') }}</th>
                                <th>{{ __('web/microstock.usage.col_stage') }}</th>
                                <th class="d-none d-md-table-cell">{{ __('web/microstock.usage.col_model') }}</th>
                                <th class="text-end">{{ __('web/microstock.usage.col_tokens') }}</th>
                                <th class="text-end">{{ __('web/microstock.usage.col_cost') }}</th>
                                <th>{{ __('web/microstock.usage.col_source') }}</th>
                            </tr></thead>
                            <tbody data-fh-msu-usage-body>
                                <tr><td colspan="7" class="text-muted">{{ __('web/microstock.usage.loading') }}</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-center mt-2 d-none" data-fh-msu-usage-more-wrap>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-fh-msu-usage-more>
                            {{ __('web/microstock.usage.load_more') }}
                        </button>
                    </div>
                </div>

                @if ($canViewPlatformBilling ?? false)
                    <div class="tab-pane fade" id="fh-msu-platform-pane" role="tabpanel">
                        <div class="row g-2 mb-3">
                            <div class="col-6 col-md-4">
                                <div class="card h-100"><div class="card-body py-2 px-3">
                                    <div class="text-muted small">{{ __('web/microstock.usage.platform_provider_cost') }}</div>
                                    <div class="fw-semibold" data-fh-msu-platform-provider>â€”</div>
                                </div></div>
                            </div>
                            <div class="col-6 col-md-4">
                                <div class="card h-100"><div class="card-body py-2 px-3">
                                    <div class="text-muted small">{{ __('web/microstock.usage.platform_billable') }}</div>
                                    <div class="fw-semibold" data-fh-msu-platform-billable>â€”</div>
                                </div></div>
                            </div>
                            <div class="col-6 col-md-4">
                                <div class="card h-100"><div class="card-body py-2 px-3">
                                    <div class="text-muted small">{{ __('web/microstock.usage.platform_margin') }}</div>
                                    <div class="fw-semibold" data-fh-msu-platform-margin>â€”</div>
                                </div></div>
                            </div>
                            <div class="col-6 col-md-4">
                                <div class="card h-100"><div class="card-body py-2 px-3">
                                    <div class="text-muted small">{{ __('web/microstock.usage.platform_events') }}</div>
                                    <div class="fw-semibold" data-fh-msu-platform-events>â€”</div>
                                </div></div>
                            </div>
                            <div class="col-6 col-md-4">
                                <div class="card h-100"><div class="card-body py-2 px-3">
                                    <div class="text-muted small">{{ __('web/microstock.usage.platform_users') }}</div>
                                    <div class="fw-semibold" data-fh-msu-platform-users>â€”</div>
                                </div></div>
                            </div>
                            <div class="col-6 col-md-4">
                                <div class="card h-100"><div class="card-body py-2 px-3">
                                    <div class="text-muted small">{{ __('web/microstock.usage.platform_daily_free_cost') }}</div>
                                    <div class="fw-semibold" data-fh-msu-platform-daily-free>â€”</div>
                                </div></div>
                            </div>
                        </div>

                        <h2 class="h6 mb-1">{{ __('web/microstock.usage.platform_by_source') }}</h2>
                        <div class="table-responsive mb-3">
                            <table class="table table-sm table-hover mb-0">
                                <thead><tr>
                                    <th>{{ __('web/microstock.usage.col_source') }}</th>
                                    <th class="text-end">{{ __('web/microstock.usage.platform_events') }}</th>
                                    <th class="text-end">{{ __('web/microstock.usage.col_provider_cost') }}</th>
                                    <th class="text-end">{{ __('web/microstock.usage.col_cost') }}</th>
                                </tr></thead>
                                <tbody data-fh-msu-platform-source-body>
                                    <tr><td colspan="4" class="text-muted">{{ __('web/microstock.usage.loading') }}</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <h2 class="h6 mb-1">{{ __('web/microstock.usage.platform_by_stage') }}</h2>
                        <div class="table-responsive mb-3">
                            <table class="table table-sm table-hover mb-0">
                                <thead><tr>
                                    <th>{{ __('web/microstock.usage.col_stage') }}</th>
                                    <th class="text-end">{{ __('web/microstock.usage.platform_events') }}</th>
                                    <th class="text-end">{{ __('web/microstock.usage.col_provider_cost') }}</th>
                                </tr></thead>
                                <tbody data-fh-msu-platform-stage-body>
                                    <tr><td colspan="3" class="text-muted">{{ __('web/microstock.usage.loading') }}</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <h2 class="h6 mb-1">{{ __('web/microstock.usage.platform_by_model') }}</h2>
                        <div class="table-responsive mb-3">
                            <table class="table table-sm table-hover mb-0">
                                <thead><tr>
                                    <th>{{ __('web/microstock.usage.col_model') }}</th>
                                    <th class="text-end">{{ __('web/microstock.usage.platform_events') }}</th>
                                    <th class="text-end">{{ __('web/microstock.usage.col_tokens') }}</th>
                                </tr></thead>
                                <tbody data-fh-msu-platform-model-body>
                                    <tr><td colspan="3" class="text-muted">{{ __('web/microstock.usage.loading') }}</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <h2 class="h6 mb-1">{{ __('web/microstock.usage.platform_all_usage') }}</h2>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead><tr>
                                    <th>{{ __('web/microstock.usage.col_date') }}</th>
                                    <th class="d-none d-md-table-cell">{{ __('web/microstock.usage.col_user') }}</th>
                                    <th>{{ __('web/microstock.usage.col_file') }}</th>
                                    <th>{{ __('web/microstock.usage.col_stage') }}</th>
                                    <th class="text-end">{{ __('web/microstock.usage.col_provider_cost') }}</th>
                                    <th class="text-end">{{ __('web/microstock.usage.col_cost') }}</th>
                                    <th>{{ __('web/microstock.usage.col_source') }}</th>
                                </tr></thead>
                                <tbody data-fh-msu-platform-usage-body>
                                    <tr><td colspan="7" class="text-muted">{{ __('web/microstock.usage.loading') }}</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-center mt-2 d-none" data-fh-msu-platform-usage-more-wrap>
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-fh-msu-platform-usage-more>
                                {{ __('web/microstock.usage.load_more') }}
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
    @can('create file hosting folder')
        <div class="modal fade" id="fhNewFolderModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('file-hosting.folders.store') }}" class="modal-content" data-fh-ajax-form>
                    @csrf
                    @if ($currentFolderId)
                        <input type="hidden" name="parent_id" value="{{ $currentFolderId }}">
                    @endif
                    @if ($ownerUserId)
                        <input type="hidden" name="user_id" value="{{ $ownerUserId }}">
                        @if($workspaceId)
                            <input type="hidden" name="workspace_id" value="{{ $workspaceId }}">
                        @endif
                    @endif
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-folder-plus me-1"></i>{{ __('web/file-hosting.new_folder') }}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label" for="fh-folder-name">{{ __('web/file-hosting.folder_name') }}</label>
                        <input type="text" name="name" id="fh-folder-name" class="form-control" required maxlength="255" autofocus>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('web/file-hosting.cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('web/file-hosting.create') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endcan
