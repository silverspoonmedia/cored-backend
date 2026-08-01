@php
    $usageConfig = [
        'summaryUrl' => route('file-hosting.microstock.billing'),
        'ledgerUrl' => route('file-hosting.microstock.billing.ledger'),
        'usageUrl' => route('file-hosting.microstock.billing.usage-events'),
        'adminOverviewUrl' => $canViewPlatformBilling
            ? route('file-hosting.microstock.billing.admin.overview')
            : null,
        'adminUsageUrl' => $canViewPlatformBilling
            ? route('file-hosting.microstock.billing.admin.usage-events')
            : null,
        'canViewPlatformBilling' => $canViewPlatformBilling ?? false,
        'backUrl' => $backUrl,
        'servicesUrl' => $servicesUrl,
        'labels' => [
            'balance' => __('web/microstock.usage.balance'),
            'estimate' => __('web/microstock.usage.estimate'),
            'dailyFree' => __('web/microstock.usage.daily_free'),
            'tabLedger' => __('web/microstock.usage.tab_ledger'),
            'tabUsage' => __('web/microstock.usage.tab_usage'),
            'tabPlatform' => __('web/microstock.usage.tab_platform'),
            'colDate' => __('web/microstock.usage.col_date'),
            'colDescription' => __('web/microstock.usage.col_description'),
            'colAmount' => __('web/microstock.usage.col_amount'),
            'colBalance' => __('web/microstock.usage.col_balance'),
            'colFile' => __('web/microstock.usage.col_file'),
            'colStage' => __('web/microstock.usage.col_stage'),
            'colModel' => __('web/microstock.usage.col_model'),
            'colTokens' => __('web/microstock.usage.col_tokens'),
            'colCost' => __('web/microstock.usage.col_cost'),
            'colSource' => __('web/microstock.usage.col_source'),
            'colUser' => __('web/microstock.usage.col_user'),
            'colProviderCost' => __('web/microstock.usage.col_provider_cost'),
            'sourceDailyFree' => __('web/microstock.usage.source_daily_free'),
            'sourcePrepaid' => __('web/microstock.usage.source_prepaid'),
            'emptyLedger' => __('web/microstock.usage.empty_ledger'),
            'emptyUsage' => __('web/microstock.usage.empty_usage'),
            'emptyPlatform' => __('web/microstock.usage.empty_platform'),
            'loadMore' => __('web/microstock.usage.load_more'),
            'loading' => __('web/microstock.usage.loading'),
            'buyCredits' => __('web/microstock.usage.buy_credits'),
            'back' => __('web/microstock.usage.back'),
            'dailyFreeLine' => __('web/microstock.usage.daily_free_line'),
            'platformProviderCost' => __('web/microstock.usage.platform_provider_cost'),
            'platformBillable' => __('web/microstock.usage.platform_billable'),
            'platformMargin' => __('web/microstock.usage.platform_margin'),
            'platformEvents' => __('web/microstock.usage.platform_events'),
            'platformUsers' => __('web/microstock.usage.platform_users'),
            'platformDailyFreeCost' => __('web/microstock.usage.platform_daily_free_cost'),
            'platformBySource' => __('web/microstock.usage.platform_by_source'),
            'platformByStage' => __('web/microstock.usage.platform_by_stage'),
            'platformByModel' => __('web/microstock.usage.platform_by_model'),
            'platformAllUsage' => __('web/microstock.usage.platform_all_usage'),
        ],
    ];
@endphp

<x-adminlte4::layouts.app :title="__('web/microstock.usage.title')">
    <div class="fh-ms-usage" data-fh-ms-usage data-config="{{ json_encode($usageConfig) }}">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <div>
                <a href="{{ $backUrl }}" class="btn btn-outline-secondary btn-sm mb-2">
                    <i class="bi bi-arrow-left me-1"></i>{{ __('web/microstock.usage.back') }}
                </a>
                <h1 class="h4 mb-0">{{ __('web/microstock.usage.title') }}</h1>
                <p class="text-muted small mb-0">{{ __('web/microstock.usage.subtitle') }}</p>
            </div>
            <a href="{{ $servicesUrl }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-coin me-1"></i>{{ __('web/microstock.usage.buy_credits') }}
            </a>
        </div>

        <div class="row g-3 mb-4" data-fh-ms-usage-summary>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-muted small">{{ __('web/microstock.usage.balance') }}</div>
                        <div class="fs-4 fw-semibold" data-fh-ms-balance>—</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-muted small">{{ __('web/microstock.usage.estimate') }}</div>
                        <div class="fs-5 fw-semibold" data-fh-ms-estimate>—</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 d-none" data-fh-ms-daily-card>
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-muted small">{{ __('web/microstock.usage.daily_free') }}</div>
                        <div class="fs-5 fw-semibold" data-fh-ms-daily>—</div>
                    </div>
                </div>
            </div>
        </div>

        <ul class="nav nav-tabs mb-3" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="fh-ms-ledger-tab" data-bs-toggle="tab"
                        data-bs-target="#fh-ms-ledger-pane" type="button" role="tab">
                    {{ __('web/microstock.usage.tab_ledger') }}
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="fh-ms-usage-tab" data-bs-toggle="tab"
                        data-bs-target="#fh-ms-usage-pane" type="button" role="tab">
                    {{ __('web/microstock.usage.tab_usage') }}
                </button>
            </li>
            @if ($canViewPlatformBilling ?? false)
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="fh-ms-platform-tab" data-bs-toggle="tab"
                            data-bs-target="#fh-ms-platform-pane" type="button" role="tab">
                        {{ __('web/microstock.usage.tab_platform') }}
                    </button>
                </li>
            @endif
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="fh-ms-ledger-pane" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('web/microstock.usage.col_date') }}</th>
                                <th>{{ __('web/microstock.usage.col_description') }}</th>
                                <th class="text-end">{{ __('web/microstock.usage.col_amount') }}</th>
                                <th class="text-end">{{ __('web/microstock.usage.col_balance') }}</th>
                            </tr>
                        </thead>
                        <tbody data-fh-ms-ledger-body>
                            <tr><td colspan="4" class="text-muted">{{ __('web/microstock.usage.loading') }}</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="text-center mt-3 d-none" data-fh-ms-ledger-more-wrap>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-fh-ms-ledger-more>
                        {{ __('web/microstock.usage.load_more') }}
                    </button>
                </div>
            </div>

            <div class="tab-pane fade" id="fh-ms-usage-pane" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('web/microstock.usage.col_date') }}</th>
                                <th>{{ __('web/microstock.usage.col_file') }}</th>
                                <th>{{ __('web/microstock.usage.col_stage') }}</th>
                                <th>{{ __('web/microstock.usage.col_model') }}</th>
                                <th class="text-end">{{ __('web/microstock.usage.col_tokens') }}</th>
                                <th class="text-end">{{ __('web/microstock.usage.col_cost') }}</th>
                                <th>{{ __('web/microstock.usage.col_source') }}</th>
                            </tr>
                        </thead>
                        <tbody data-fh-ms-usage-body>
                            <tr><td colspan="7" class="text-muted">{{ __('web/microstock.usage.loading') }}</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="text-center mt-3 d-none" data-fh-ms-usage-more-wrap>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-fh-ms-usage-more>
                        {{ __('web/microstock.usage.load_more') }}
                    </button>
                </div>
            </div>

            @if ($canViewPlatformBilling ?? false)
                <div class="tab-pane fade" id="fh-ms-platform-pane" role="tabpanel">
                    <div class="row g-3 mb-4" data-fh-ms-platform-summary>
                        <div class="col-md-4 col-lg-2">
                            <div class="card h-100"><div class="card-body py-2">
                                <div class="text-muted small">{{ __('web/microstock.usage.platform_provider_cost') }}</div>
                                <div class="fw-semibold" data-fh-ms-platform-provider>—</div>
                            </div></div>
                        </div>
                        <div class="col-md-4 col-lg-2">
                            <div class="card h-100"><div class="card-body py-2">
                                <div class="text-muted small">{{ __('web/microstock.usage.platform_billable') }}</div>
                                <div class="fw-semibold" data-fh-ms-platform-billable>—</div>
                            </div></div>
                        </div>
                        <div class="col-md-4 col-lg-2">
                            <div class="card h-100"><div class="card-body py-2">
                                <div class="text-muted small">{{ __('web/microstock.usage.platform_margin') }}</div>
                                <div class="fw-semibold" data-fh-ms-platform-margin>—</div>
                            </div></div>
                        </div>
                        <div class="col-md-4 col-lg-2">
                            <div class="card h-100"><div class="card-body py-2">
                                <div class="text-muted small">{{ __('web/microstock.usage.platform_events') }}</div>
                                <div class="fw-semibold" data-fh-ms-platform-events>—</div>
                            </div></div>
                        </div>
                        <div class="col-md-4 col-lg-2">
                            <div class="card h-100"><div class="card-body py-2">
                                <div class="text-muted small">{{ __('web/microstock.usage.platform_users') }}</div>
                                <div class="fw-semibold" data-fh-ms-platform-users>—</div>
                            </div></div>
                        </div>
                        <div class="col-md-4 col-lg-2">
                            <div class="card h-100"><div class="card-body py-2">
                                <div class="text-muted small">{{ __('web/microstock.usage.platform_daily_free_cost') }}</div>
                                <div class="fw-semibold" data-fh-ms-platform-daily-free>—</div>
                            </div></div>
                        </div>
                    </div>

                    <div class="row g-4 mb-4">
                        <div class="col-lg-4">
                            <h2 class="h6">{{ __('web/microstock.usage.platform_by_source') }}</h2>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover mb-0">
                                    <thead><tr>
                                        <th>{{ __('web/microstock.usage.col_source') }}</th>
                                        <th class="text-end">{{ __('web/microstock.usage.platform_events') }}</th>
                                        <th class="text-end">{{ __('web/microstock.usage.col_provider_cost') }}</th>
                                        <th class="text-end">{{ __('web/microstock.usage.col_cost') }}</th>
                                    </tr></thead>
                                    <tbody data-fh-ms-platform-source-body>
                                        <tr><td colspan="4" class="text-muted">{{ __('web/microstock.usage.loading') }}</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <h2 class="h6">{{ __('web/microstock.usage.platform_by_stage') }}</h2>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover mb-0">
                                    <thead><tr>
                                        <th>{{ __('web/microstock.usage.col_stage') }}</th>
                                        <th class="text-end">{{ __('web/microstock.usage.platform_events') }}</th>
                                        <th class="text-end">{{ __('web/microstock.usage.col_provider_cost') }}</th>
                                    </tr></thead>
                                    <tbody data-fh-ms-platform-stage-body>
                                        <tr><td colspan="3" class="text-muted">{{ __('web/microstock.usage.loading') }}</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <h2 class="h6">{{ __('web/microstock.usage.platform_by_model') }}</h2>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover mb-0">
                                    <thead><tr>
                                        <th>{{ __('web/microstock.usage.col_model') }}</th>
                                        <th class="text-end">{{ __('web/microstock.usage.platform_events') }}</th>
                                        <th class="text-end">{{ __('web/microstock.usage.col_tokens') }}</th>
                                    </tr></thead>
                                    <tbody data-fh-ms-platform-model-body>
                                        <tr><td colspan="3" class="text-muted">{{ __('web/microstock.usage.loading') }}</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <h2 class="h6">{{ __('web/microstock.usage.platform_all_usage') }}</h2>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('web/microstock.usage.col_date') }}</th>
                                    <th>{{ __('web/microstock.usage.col_user') }}</th>
                                    <th>{{ __('web/microstock.usage.col_file') }}</th>
                                    <th>{{ __('web/microstock.usage.col_stage') }}</th>
                                    <th class="text-end">{{ __('web/microstock.usage.col_provider_cost') }}</th>
                                    <th class="text-end">{{ __('web/microstock.usage.col_cost') }}</th>
                                    <th>{{ __('web/microstock.usage.col_source') }}</th>
                                </tr>
                            </thead>
                            <tbody data-fh-ms-platform-usage-body>
                                <tr><td colspan="7" class="text-muted">{{ __('web/microstock.usage.loading') }}</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-center mt-3 d-none" data-fh-ms-platform-usage-more-wrap>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-fh-ms-platform-usage-more>
                            {{ __('web/microstock.usage.load_more') }}
                        </button>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @vite(['resources/css/file-hosting/file-hosting.css', 'resources/js/file-hosting/microstock-usage.js'])
</x-adminlte4::layouts.app>
