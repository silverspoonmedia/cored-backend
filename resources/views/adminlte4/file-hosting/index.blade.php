@php
    $breadcrumb = $browse['breadcrumb'] ?? [];
    $folders = collect($browse['folders'] ?? []);
    $files = collect($browse['files'] ?? []);
    $workspaceId = $workspaceId ?? null;
    $workspaces = collect($workspaces ?? []);
    $workspaceActivity = collect($workspaceActivity ?? []);
    $ownerQuery = array_filter([
        'user_id' => $ownerUserId,
        'workspace_id' => $workspaceId,
    ]);
    $visPrivate = \App\Domain\FileHosting\Enums\Visibility::Private->value;
    $visLink = \App\Domain\FileHosting\Enums\Visibility::AnyoneWithLink->value;
    $regions = (array) config('file-hosting.regions');
    $uploadableRegions = array_filter(
        $regions,
        fn ($disk, $key): bool => ! \App\Domain\FileHosting\Support\RegionResolver::isDisabled((string) $key),
        ARRAY_FILTER_USE_BOTH,
    );
    $showRegion = \App\Domain\FileHosting\Support\RegionResolver::shouldDisplayRegion();
    $tableColspan = $showRegion ? 8 : 7;

    // Virtual "..." parent row when nested (second-to-last breadcrumb = parent; null = root).
    $parentFolderShow = filled($currentFolderId);
    $parentFolderId = null;
    $parentFolderHref = route('file-hosting.index', $ownerQuery);
    $parentFolderDrop = 'root';
    if ($parentFolderShow) {
        $crumbCount = count($breadcrumb);
        if ($crumbCount >= 2) {
            $parentFolderId = $breadcrumb[$crumbCount - 2]['id'] ?? null;
        }
        if (filled($parentFolderId)) {
            $parentFolderHref = route('file-hosting.index', array_filter(array_merge($ownerQuery, ['folder_id' => $parentFolderId])));
            $parentFolderDrop = (string) $parentFolderId;
        }
    }

    $fhRealtime = app(\App\Domain\FileHosting\Support\FileHostingRealtime::class);
    $fhBroadcast = $fhRealtime->clientConfig();
    $authUser = auth('api')->user();
    $isMicrostock = (($currentWorkspace['kind'] ?? null) === \App\Domain\FileHosting\Enums\WorkspaceKind::Microstock->value);

    // Auth FH lives under /apps/file-hosting — derive mutation bases from the named index route.
    $fhBase = rtrim(route('file-hosting.index'), '/');

    $fmConfig = [
        'spaShell' => true,
        'fhBase' => $fhBase,
        'searchUrl' => route('file-hosting.search'),
        'browseUrl' => route('file-hosting.index', $ownerQuery),
        'quotaUrl' => route('file-hosting.quota', $ownerQuery),
        'trashUrl' => route('file-hosting.trash', $ownerQuery),
        'workspacesUrl' => route('file-hosting.workspaces.index'),
        'workspacesBase' => $fhBase.'/workspaces',
        'membersUrl' => $workspaceId
            ? route('file-hosting.workspaces.members.index', $workspaceId)
            : null,
        'activityUrl' => $workspaceId
            ? route('file-hosting.workspaces.activity', $workspaceId)
            : null,
        'fileShowBase' => $fhBase.'/files',
        'fileBase' => $fhBase.'/files',
        'folderBase' => $fhBase.'/folders',
        'fileRenameBase' => $fhBase.'/files',
        'folderRenameBase' => $fhBase.'/folders',
        'bulkFileVisibilityUrl' => route('file-hosting.files.bulk-visibility'),
        'bulkFolderVisibilityUrl' => route('file-hosting.folders.bulk-visibility'),
        'bulkFileDeleteUrl' => route('file-hosting.files.bulk-destroy'),
        'bulkFolderDeleteUrl' => route('file-hosting.folders.bulk-destroy'),
        'ownerUserId' => $ownerUserId,
        'workspaceId' => $workspaceId,
        'currentFolderId' => $currentFolderId,
        'workspaces' => $workspaces,
        'currentWorkspace' => $currentWorkspace ?? null,
        'collectionBase' => $fhBase.'/microstock/collections',
        'assetsBase' => $fhBase.'/microstock/assets',
        'billingUrl' => route('file-hosting.microstock.billing'),
        'usageUrl' => route('file-hosting.microstock.usage', array_filter(['workspace_id' => $workspaceId])),
        'usageUrlBase' => route('file-hosting.microstock.usage'),
        'toolbarBalanceLabel' => __('web/microstock.usage.toolbar_balance'),
        'usagePanel' => [
            'summaryUrl' => route('file-hosting.microstock.billing'),
            'ledgerUrl' => route('file-hosting.microstock.billing.ledger'),
            'usageEventsUrl' => route('file-hosting.microstock.billing.usage-events'),
            'servicesUrl' => route('services.index'),
            'canViewPlatformBilling' => $canViewPlatformBilling ?? false,
            'adminOverviewUrl' => ($canViewPlatformBilling ?? false)
                ? route('file-hosting.microstock.billing.admin.overview')
                : null,
            'adminUsageEventsUrl' => ($canViewPlatformBilling ?? false)
                ? route('file-hosting.microstock.billing.admin.usage-events')
                : null,
            'labels' => [
                'title' => __('web/microstock.usage.title'),
                'subtitle' => __('web/microstock.usage.subtitle'),
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
        ],
        'collectionStatus' => $browse['folder']['collection_status'] ?? null,
        'currentFolderHint' => $browse['folder']['collection_hint'] ?? null,
        'collectionDescribe' => [
            'sectionsIntro' => __('web/file-hosting.microstock.describe_sections_intro'),
            'primaryConcept' => __('web/file-hosting.microstock.describe_primary_concept'),
            'primaryPlaceholder' => __('web/file-hosting.microstock.describe_primary_placeholder'),
            'visibleFacts' => __('web/file-hosting.microstock.describe_visible_facts'),
            'buyerIntent' => __('web/file-hosting.microstock.describe_buyer_intent'),
            'styleMood' => __('web/file-hosting.microstock.describe_style_mood'),
            'additionalNotes' => __('web/file-hosting.microstock.describe_additional_notes'),
            'additionalPlaceholder' => __('web/file-hosting.microstock.describe_additional_placeholder'),
            'otherPlaceholder' => __('web/file-hosting.microstock.describe_other_placeholder'),
            'preview' => __('web/file-hosting.microstock.describe_preview'),
            'charCount' => __('web/file-hosting.microstock.describe_char_count'),
            'validation' => [
                'primary' => __('web/file-hosting.microstock.describe_validation_primary', ['min' => 10]),
                'visible' => __('web/file-hosting.microstock.describe_validation_visible'),
                'intent' => __('web/file-hosting.microstock.describe_validation_intent'),
                'style' => __('web/file-hosting.microstock.describe_validation_style'),
                'tooLong' => __('web/file-hosting.microstock.describe_validation_too_long'),
                'blindWarning' => __('web/file-hosting.microstock.describe_warning_blind'),
            ],
            'visibleOptions' => __('web/file-hosting.microstock.describe_visible_options'),
            'intentOptions' => __('web/file-hosting.microstock.describe_intent_options'),
            'styleOptions' => __('web/file-hosting.microstock.describe_style_options'),
        ],
        'collectionLabels' => [
            'lockDescribe' => __('web/file-hosting.microstock.lock_describe'),
            'analyze' => __('web/file-hosting.microstock.analyze'),
            'exportCsv' => __('web/file-hosting.microstock.export_csv'),
            'downloadCsv' => __('web/file-hosting.microstock.download_csv'),
            'processing' => __('web/file-hosting.microstock.status_processing'),
            'processingDegraded' => __('web/file-hosting.microstock.status_processing_degraded'),
            'review' => __('web/file-hosting.microstock.status_review'),
            'exported' => __('web/file-hosting.microstock.status_exported'),
            'failed' => __('web/file-hosting.microstock.status_failed'),
            'locked' => __('web/file-hosting.microstock.status_locked'),
            'progressFailed' => __('web/file-hosting.microstock.progress_failed'),
            'billingLine' => __('web/file-hosting.microstock.billing_line'),
            'dailyFreeLine' => __('web/file-hosting.microstock.daily_free_line'),
            'billingMixedLine' => __('web/file-hosting.microstock.billing_mixed_line'),
            'processingFreeTier' => __('web/file-hosting.microstock.status_processing_free_tier'),
            'insufficientCredits' => __('web/file-hosting.microstock.insufficient_credits'),
            'billingHistoryLink' => __('web/microstock.usage.billing_history_link'),
        ],
        'microstockPanel' => [
            'progress' => __('web/file-hosting.microstock.progress'),
            'reviewFiles' => __('web/file-hosting.microstock.review_files'),
            'review' => __('web/file-hosting.microstock.review'),
            'noMetadata' => __('web/file-hosting.microstock.no_metadata'),
            'statusPending' => __('web/file-hosting.microstock.status_pending'),
            'statusGenerated' => __('web/file-hosting.microstock.status_generated'),
            'statusFinalized' => __('web/file-hosting.microstock.status_finalized'),
            'statusFailed' => __('web/file-hosting.microstock.status_failed_item'),
            'stageVision' => __('web/file-hosting.microstock.stage_vision'),
            'stageThinking' => __('web/file-hosting.microstock.stage_thinking'),
            'stageQueuedThinking' => __('web/file-hosting.microstock.stage_queued_thinking'),
            'stageDegraded' => __('web/file-hosting.microstock.stage_degraded'),
            'stageVisionLabel' => __('web/file-hosting.microstock.stage_vision_label'),
            'stageThinkingLabel' => __('web/file-hosting.microstock.stage_thinking_label'),
            'stageResultLabel' => __('web/file-hosting.microstock.stage_result_label'),
            'saved' => __('web/file-hosting.microstock.panel_saved'),
            'queued' => __('web/file-hosting.microstock.panel_queued'),
            'stepUpload' => __('web/file-hosting.microstock.step_upload'),
            'stepLockDescribe' => __('web/file-hosting.microstock.step_lock_describe'),
            'stepAnalyze' => __('web/file-hosting.microstock.step_analyze'),
            'stepReview' => __('web/file-hosting.microstock.step_review'),
            'stepExport' => __('web/file-hosting.microstock.step_export'),
            'stepUploadHint' => __('web/file-hosting.microstock.step_upload_hint'),
            'stepLockHint' => __('web/file-hosting.microstock.step_lock_hint'),
            'stepAnalyzeHint' => __('web/file-hosting.microstock.step_analyze_hint'),
            'stepReviewHint' => __('web/file-hosting.microstock.step_review_hint'),
            'stepExportHint' => __('web/file-hosting.microstock.step_export_hint'),
            'stepUploadEmpty' => __('web/file-hosting.microstock.step_upload_empty'),
        ],
        'personalLabel' => __('web/file-hosting.workspace_personal'),
        'badgeYours' => __('web/file-hosting.collab.badge_yours'),
        'badgeJoined' => __('web/file-hosting.collab.badge_joined'),
        'roleLabels' => [
            'owner' => __('web/file-hosting.collab.role_owner_short'),
            'admin' => __('web/file-hosting.collab.role_admin_short'),
            'editor' => __('web/file-hosting.collab.role_editor_short'),
            'viewer' => __('web/file-hosting.collab.role_viewer_short'),
        ],
        'planState' => $planState ?? 'free',
        'canManageMembers' => isset($currentWorkspace) && is_array($currentWorkspace)
            && in_array($currentWorkspace['my_role'] ?? null, ['owner', 'admin'], true),
        'visLink' => $visLink,
        // Soft-refetch must match SSR column count (region column is conditional).
        'showRegion' => $showRegion,
        // Soft-refetch row actions must mirror Blade @can gates (not server-enforced — UI only).
        'permissions' => [
            'editFile' => $authUser?->can('edit file hosting file') ?? false,
            'deleteFile' => $authUser?->can('delete file hosting file') ?? false,
            'editFolder' => $authUser?->can('edit file hosting folder') ?? false,
            'deleteFolder' => $authUser?->can('delete file hosting folder') ?? false,
        ],
        'realtime' => [
            'enabled' => $fhBroadcast !== null,
            'broadcast' => $fhBroadcast,
            'userId' => $authUser?->id,
            'page' => 'index',
        ],
        'quotaLabels' => [
            'used' => __('web/file-hosting.quota_used'),
            'unlimited' => __('web/file-hosting.quota_unlimited'),
            'states' => [
                'free' => __('web/file-hosting.collab.badge_free'),
                'paid' => __('web/file-hosting.collab.badge_paid'),
                'grace' => __('web/file-hosting.collab.badge_grace'),
            ],
        ],
        'messages' => [
            'searchNoResults' => __('web/file-hosting.search_no_results'),
            'rootLabel' => __('web/file-hosting.root_label'),
            'loadFailed' => __('web/file-hosting.load_failed'),
            'linkCopied' => __('web/file-hosting.link_copied'),
            'success' => __('web/file-hosting.ajax_success'),
            'error' => __('web/file-hosting.ajax_error'),
            'networkError' => __('web/file-hosting.network_error'),
            'typeFolder' => __('web/file-hosting.type_folder'),
            'typeFile' => __('web/file-hosting.type_file'),
            'emptyFolder' => __('web/file-hosting.empty'),
            'parentFolder' => __('web/file-hosting.parent_folder'),
            // Soft-refetch row render needs the same labels Blade SSR uses.
            'visibility' => [
                'private' => __('web/file-hosting.visibility.private'),
                'anyone_with_link' => __('web/file-hosting.visibility.anyone_with_link'),
            ],
            'regions' => collect(array_keys((array) config('file-hosting.regions')))
                ->mapWithKeys(fn (string $key) => [$key => __('web/file-hosting.regions.'.$key)])
                ->all(),
            'actions' => [
                'view' => __('web/file-hosting.view'),
                'open' => __('web/file-hosting.open'),
                'rename' => __('web/file-hosting.rename'),
                'move' => __('web/file-hosting.move'),
                'share' => __('web/file-hosting.share'),
                'delete' => __('web/file-hosting.delete'),
                'confirmDeleteFile' => __('web/file-hosting.confirm_delete_file'),
                'confirmDeleteFolder' => __('web/file-hosting.confirm_delete_folder'),
            ],
            'moved' => __('web/file-hosting.moved'),
            'moveFailed' => __('web/file-hosting.move_failed'),
            'moveInvalid' => __('web/file-hosting.move_invalid'),
            'bulkConfirmShare' => __('web/file-hosting.bulk.confirm_share'),
            'bulkConfirmDelete' => __('web/file-hosting.bulk.confirm_delete'),
            'bulkUnshare' => __('web/file-hosting.bulk.unshare'),
            'restore' => __('web/file-hosting.restore'),
            'forceDelete' => __('web/file-hosting.force_delete'),
            'confirmRestore' => __('web/file-hosting.confirm_restore'),
            'confirmForceDelete' => __('web/file-hosting.confirm_force_delete'),
            'trashEmpty' => __('web/file-hosting.trash_empty'),
            'collab' => [
                'remove' => __('web/file-hosting.collab.remove'),
                'confirmRemove' => __('web/file-hosting.collab.confirm_remove'),
                'noMembers' => __('web/file-hosting.collab.no_members'),
            ],
        ],
        'showLabels' => [
            'name' => __('web/file-hosting.columns.name'),
            'type' => __('web/file-hosting.columns.type'),
            'size' => __('web/file-hosting.columns.size'),
            'visibility' => __('web/file-hosting.columns.visibility'),
            'views' => __('web/file-hosting.stats.views'),
            'downloads' => __('web/file-hosting.stats.downloads'),
            'stats' => __('web/file-hosting.stats.views'),
            'back' => __('web/file-hosting.back'),
            'download' => __('web/file-hosting.download'),
            'save' => __('web/file-hosting.save'),
            'private' => __('web/file-hosting.visibility.private'),
            'anyone_with_link' => __('web/file-hosting.visibility.anyone_with_link'),
        ],
    ];
@endphp

<x-adminlte4::layouts.app :title="__('web/file-hosting.title')">
    <div class="fh-manager" data-file-manager data-fh-spa data-fh-app data-config="{{ json_encode($fmConfig) }}">

        {{-- Toolbar --}}
        <div class="fh-toolbar">
            <div class="fh-toolbar__search">
                <i class="bi bi-search fh-toolbar__search-icon"></i>
                <input type="text"
                       class="form-control form-control-sm fh-toolbar__search-input"
                       data-fh-search-input
                       placeholder="{{ __('web/file-hosting.search_placeholder') }}"
                       autocomplete="off">
                <div class="fh-toolbar__search-results" data-fh-search-results></div>
            </div>

            <div class="fh-toolbar__view-toggle">
                <button type="button" class="fh-toolbar__view-btn" data-fh-view-btn="table" title="{{ __('web/file-hosting.view_list') }}">
                    <i class="bi bi-list"></i>
                </button>
                <button type="button" class="fh-toolbar__view-btn" data-fh-view-btn="grid" title="{{ __('web/file-hosting.view_grid') }}">
                    <i class="bi bi-grid"></i>
                </button>
            </div>

            <a href="{{ route('file-hosting.trash', $ownerQuery) }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-trash3 me-1"></i> {{ __('web/file-hosting.trash') }}
            </a>
            <a href="{{ route('file-hosting.microstock.usage', array_filter(['workspace_id' => $workspaceId])) }}"
               class="btn btn-outline-secondary btn-sm {{ $isMicrostock ? '' : 'd-none' }}"
               data-fh-toolbar-balance>
                <i class="bi bi-wallet2 me-1"></i> {{ __('web/microstock.usage.toolbar_balance') }}
            </a>
            @can('create file hosting folder')
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#fhNewFolderModal">
                    <i class="bi bi-folder-plus me-1"></i> {{ __('web/file-hosting.new_folder') }}
                </button>
            @endcan

            {{-- Microstock collection action (visible only inside a microstock collection; JS-driven) --}}
            <div class="d-none" data-fh-collection-action>
                <button type="button" class="btn btn-outline-primary btn-sm" data-fh-collection-primary data-bs-toggle="modal" data-bs-target="#fhCollectionDescribeModal">
                    <i class="bi bi-lock me-1"></i><span data-fh-collection-primary-label>{{ __('web/file-hosting.microstock.lock_describe') }}</span>
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm d-none" data-fh-collection-review>
                    <i class="bi bi-stars me-1"></i>{{ __('web/file-hosting.microstock.review_files') }}
                </button>
            </div>

            @include('adminlte4.file-hosting.partials._workspace-switcher', [
                'planState' => $planState ?? 'free',
                'canCreateWorkspace' => $canCreateWorkspace ?? false,
                'workspaceId' => $workspaceId,
                'currentWorkspace' => $currentWorkspace ?? null,
                'workspaces' => $workspaces,
                'ownerUserId' => $ownerUserId,
                'ownedWorkspaceUsed' => $ownedWorkspaceUsed ?? 0,
                'ownedWorkspaceLimit' => $ownedWorkspaceLimit ?? 0,
            ])
        </div>

        @include('adminlte4.file-hosting.partials._team-offcanvas', [
            'workspaceId' => $workspaceId,
            'currentWorkspace' => $currentWorkspace ?? null,
            'workspaceMembers' => $workspaceMembers ?? [],
            'workspaceActivity' => $workspaceActivity ?? [],
        ])

        {{-- Breadcrumb --}}
        <nav class="fh-breadcrumb" aria-label="breadcrumb" data-fh-breadcrumb data-fh-browse-chrome>
            @if (empty($breadcrumb))
                <span class="fh-breadcrumb__item is-active">
                    <i class="bi bi-folder2-open fh-breadcrumb__root-icon me-1"></i>
                    @if($workspaceId)
                        {{ $currentWorkspace['name'] ?? __('web/file-hosting.workspace_team') }}
                    @else
                        {{ __('web/file-hosting.root_label') }}
                    @endif
                </span>
            @else
                <a href="{{ route('file-hosting.index', $ownerQuery) }}"
                   class="fh-breadcrumb__item"
                   data-fh-soft-nav
                   data-fh-drop-folder="root">
                    <i class="bi bi-folder2-open fh-breadcrumb__root-icon me-1"></i>
                    @if($workspaceId)
                        {{ $currentWorkspace['name'] ?? __('web/file-hosting.workspace_team') }}
                    @else
                        {{ __('web/file-hosting.root_label') }}
                    @endif
                </a>
            @endif
            @foreach ($breadcrumb as $crumb)
                <i class="bi bi-chevron-right fh-breadcrumb__sep"></i>
                @if ($loop->last)
                    <span class="fh-breadcrumb__item is-active">{{ $crumb['name'] }}</span>
                @else
                    <a href="{{ route('file-hosting.index', array_filter(array_merge($ownerQuery, ['folder_id' => $crumb['id']]))) }}"
                       class="fh-breadcrumb__item"
                       data-fh-soft-nav
                       data-fh-drop-folder="{{ $crumb['id'] }}">
                        {{ $crumb['name'] }}
                    </a>
                @endif
            @endforeach
        </nav>

        {{-- Quota --}}
        <div data-fh-quota>
            @include('adminlte4.file-hosting.partials._quota-bar', ['quota' => $quota])
        </div>

        {{-- Upload dropzone --}}
        @can('create file hosting file')
            <div
                class="fh-dropzone"
                data-fh-dropzone
                data-direct-upload
                data-reload-on-complete="1"
                data-folder-id="{{ $currentFolderId }}"
                data-user-id="{{ $ownerUserId }}"
                data-workspace-id="{{ $workspaceId }}"
                data-workspace-kind="{{ $currentWorkspace['kind'] ?? '' }}"
                data-collection-locked="{{ ! empty($browse['folder']['collection_locked'] ?? null) ? '1' : '0' }}"
                data-init-url="{{ route('file-hosting.uploads.store') }}"
                data-status-url="{{ route('file-hosting.uploads.show', ['upload' => '__UPLOAD__']) }}"
                data-complete-url="{{ route('file-hosting.uploads.complete', ['upload' => '__UPLOAD__']) }}"
                data-abort-url="{{ route('file-hosting.uploads.destroy', ['upload' => '__UPLOAD__']) }}"
                data-sign-parts-url="{{ route('file-hosting.uploads.sign-parts', ['upload' => '__UPLOAD__']) }}"
                data-max-files="{{ (int) config('file-hosting.max_files_per_batch', 20) }}"
                data-max-concurrent="{{ (int) config('file-hosting.max_concurrent_uploads', 2) }}"
                data-max-concurrent-parts="{{ (int) config('file-hosting.max_concurrent_parts', 3) }}"
                role="button"
                tabindex="0"
                aria-label="{{ __('web/file-hosting.upload_here') }}"
                data-messages="{{ json_encode([
                    'tooManyFiles' => __('web/file-hosting.upload.too_many'),
                    'waitForUploads' => __('web/file-hosting.upload.wait'),
                    'uploadFailed' => __('web/file-hosting.upload.failed'),
                    'pending' => __('web/file-hosting.upload.pending'),
                    'waiting' => __('web/file-hosting.upload.waiting'),
                    'uploading' => __('web/file-hosting.upload.uploading'),
                    'completing' => __('web/file-hosting.upload.completing'),
                    'done' => __('web/file-hosting.upload.done'),
                    'remove' => __('web/file-hosting.upload.remove'),
                    'requiresFolder' => __('web/file-hosting.microstock.error_requires_folder'),
                    'collectionLocked' => __('web/file-hosting.microstock.error_collection_locked'),
                ]) }}"
            >
                <div class="fh-dropzone__icon">
                    <i class="bi bi-cloud-arrow-up" aria-hidden="true"></i>
                </div>
                <div class="fh-dropzone__text">{{ __('web/file-hosting.upload_here') }}</div>
                <div class="fh-dropzone__hint">{{ __('web/file-hosting.drag_drop_hint') }}</div>
                <div class="fh-dropzone__actions">
                    <input type="file" class="d-none" data-direct-upload-input multiple>
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="this.closest('.fh-dropzone').querySelector('[data-direct-upload-input]').click()">
                        <i class="bi bi-folder2-open me-1" aria-hidden="true"></i>{{ __('web/file-hosting.upload.browse') }}
                    </button>
                    @if (count($uploadableRegions) > 1)
                        <select class="form-select form-select-sm fh-dropzone__region" data-direct-upload-region aria-label="{{ __('web/file-hosting.upload.region') }}">
                            @foreach ($uploadableRegions as $key => $diskName)
                                <option value="{{ $key }}">{{ __('web/file-hosting.regions.'.$key) }}</option>
                            @endforeach
                        </select>
                    @endif
                    <button type="button" class="btn btn-primary btn-sm" data-direct-upload-start disabled>
                        <i class="bi bi-play-fill me-1"></i>{{ __('web/file-hosting.upload.start') }}
                    </button>
                </div>
                <div class="fh-upload-queue" data-direct-upload-queue></div>
            </div>
            @vite('resources/js/file-hosting/direct-upload.js')
        @endcan

        {{-- Bulk action bar --}}
        <div class="fh-bulk-bar" data-fh-bulk-bar style="display:none;">
            <span class="fh-bulk-bar__count">
                <strong data-fh-bulk-count>0</strong> {{ __('web/file-hosting.bulk.selected') }}
            </span>
            <div class="fh-bulk-bar__actions">
                @canany(['edit file hosting file', 'edit file hosting folder'])
                    <button type="button" class="btn btn-outline-primary btn-sm" data-fh-bulk-action="move">
                        <i class="bi bi-folder-symlink me-1"></i>{{ __('web/file-hosting.bulk.move') }}
                    </button>
                @endcanany
                @can('edit file hosting file')
                    <button type="button" class="btn btn-outline-success btn-sm" data-fh-bulk-action="share"
                            data-fh-confirm="{{ __('web/file-hosting.bulk.confirm_share') }}">
                        <i class="bi bi-share me-1"></i>{{ __('web/file-hosting.bulk.share') }}
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-fh-bulk-action="unshare"
                            data-fh-confirm="{{ __('web/file-hosting.bulk.confirm_unshare') }}">
                        <i class="bi bi-shield-lock me-1"></i>{{ __('web/file-hosting.bulk.unshare') }}
                    </button>
                @endcan
                @can('delete file hosting file')
                    <button type="button" class="btn btn-outline-danger btn-sm" data-fh-bulk-action="delete"
                            data-fh-confirm="{{ __('web/file-hosting.bulk.confirm_delete') }}">
                        <i class="bi bi-trash me-1"></i>{{ __('web/file-hosting.bulk.delete') }}
                    </button>
                @endcan
            </div>
        </div>

        {{-- SPA browse panel (table + grid) --}}
        <div data-fh-panel="browse">
        {{-- Microstock collection stepper (JS-driven, hidden by default) --}}
        <div class="fh-collection-stepper d-none" data-fh-collection-stepper role="status">
            <div class="fh-collection-stepper__steps">
                <div class="fh-collection-stepper__step is-pending" data-fh-step="1">
                    <span class="fh-collection-stepper__circle">1</span>
                    <span class="fh-collection-stepper__label" data-fh-step-label>{{ __('web/file-hosting.microstock.step_upload') }}</span>
                </div>
                <div class="fh-collection-stepper__line"></div>
                <div class="fh-collection-stepper__step is-pending" data-fh-step="2">
                    <span class="fh-collection-stepper__circle">2</span>
                    <span class="fh-collection-stepper__label" data-fh-step-label>{{ __('web/file-hosting.microstock.step_lock_describe') }}</span>
                </div>
                <div class="fh-collection-stepper__line"></div>
                <div class="fh-collection-stepper__step is-pending" data-fh-step="3">
                    <span class="fh-collection-stepper__circle">3</span>
                    <span class="fh-collection-stepper__label" data-fh-step-label>{{ __('web/file-hosting.microstock.step_analyze') }}</span>
                </div>
                <div class="fh-collection-stepper__line"></div>
                <div class="fh-collection-stepper__step is-pending" data-fh-step="4">
                    <span class="fh-collection-stepper__circle">4</span>
                    <span class="fh-collection-stepper__label" data-fh-step-label>{{ __('web/file-hosting.microstock.step_review') }}</span>
                </div>
                <div class="fh-collection-stepper__line"></div>
                <div class="fh-collection-stepper__step is-pending" data-fh-step="5">
                    <span class="fh-collection-stepper__circle">5</span>
                    <span class="fh-collection-stepper__label" data-fh-step-label>{{ __('web/file-hosting.microstock.step_export') }}</span>
                </div>
            </div>
            <div class="fh-collection-stepper__footer">
                <div class="fh-collection-stepper__hint" data-fh-step-hint></div>
                <div class="fh-collection-stepper__progress d-none" data-fh-step-progress></div>
            </div>
            {{-- Legacy banner text container for progress/degraded messages --}}
            <div class="d-none" data-fh-collection-banner-text></div>
        </div>
        {{-- Legacy banner container (kept for compatibility, hidden by JS) --}}
        <div class="alert alert-secondary d-none" data-fh-collection-banner role="status" aria-hidden="true">
            <i class="bi bi-images"></i>
        </div>
        {{-- Table view --}}
        <div data-fh-view="table">
            <div class="table-responsive">
                <table class="table fh-table">
                    <thead>
                        <tr>
                            <th class="fh-table__select-cell">
                                <input type="checkbox" class="form-check-input" data-fh-select-all aria-label="{{ __('web/file-hosting.bulk.select_all') }}">
                            </th>
                            <th class="fh-table__icon-cell"></th>
                            <th>{{ __('web/file-hosting.columns.name') }}</th>
                            <th>{{ __('web/file-hosting.columns.type') }}</th>
                            <th>{{ __('web/file-hosting.columns.size') }}</th>
                            @if ($showRegion)
                                <th>{{ __('web/file-hosting.columns.region') }}</th>
                            @endif
                            <th>{{ __('web/file-hosting.columns.visibility') }}</th>
                            <th class="text-end">{{ __('web/file-hosting.columns.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if ($parentFolderShow)
                            <tr data-fh-row data-fh-entity="parent" data-fh-drop-folder="{{ $parentFolderDrop }}" class="fh-parent-row" title="{{ __('web/file-hosting.parent_folder') }}">
                                <td class="fh-table__select-cell"></td>
                                <td class="fh-table__icon-cell">
                                    <a href="{{ $parentFolderHref }}" data-fh-soft-nav aria-label="{{ __('web/file-hosting.parent_folder') }}">
                                        <i class="bi bi-folder2 fh-icon--folder fh-parent-row__icon" aria-hidden="true"></i>
                                    </a>
                                </td>
                                <td>
                                    <a href="{{ $parentFolderHref }}" class="fh-table__name" data-fh-soft-nav title="{{ __('web/file-hosting.parent_folder') }}">
                                        <span class="fh-table__name-text">...</span>
                                    </a>
                                </td>
                                <td class="fh-table__type text-muted">{{ __('web/file-hosting.parent_folder') }}</td>
                                <td class="fh-table__size">&mdash;</td>
                                @if ($showRegion)
                                    <td class="fh-table__region"><span class="text-muted">&mdash;</span></td>
                                @endif
                                <td></td>
                                <td class="fh-table__actions"></td>
                            </tr>
                        @endif
                        @forelse ($folders as $folder)
                            @php $folderVis = $folder['visibility'] ?? $visPrivate; @endphp
                            <tr data-fh-row data-fh-entity="folder" data-fh-id="{{ $folder['id'] }}">
                        <td class="fh-table__select-cell">
                            <input type="checkbox" class="form-check-input" data-fh-select value="folder:{{ $folder['id'] }}" aria-label="{{ __('web/file-hosting.bulk.select_item') }}">
                        </td>
                        <td class="fh-table__icon-cell">
                            <a href="{{ route('file-hosting.index', array_filter(array_merge($ownerQuery, ['folder_id' => $folder['id']]))) }}">
                                @include('adminlte4.file-hosting.partials._file-icon', ['mime' => null, 'isFolder' => true])
                            </a>
                        </td>
                        <td>
                            <a href="{{ route('file-hosting.index', array_filter(array_merge($ownerQuery, ['folder_id' => $folder['id']]))) }}"
                               class="fh-table__name">
                                <span class="fh-table__name-text" data-fh-name>{{ $folder['name'] ?? '' }}</span>
                            </a>
                        </td>
                        <td class="fh-table__type">{{ __('web/file-hosting.type_folder') }}</td>
                        <td class="fh-table__size">—</td>
                        @if ($showRegion)
                            <td class="fh-table__region"><span class="text-muted">—</span></td>
                        @endif
                        <td data-fh-visibility-cell>@include('adminlte4.file-hosting.partials._visibility-badge', ['visibility' => $folderVis])</td>
                        <td class="fh-table__actions">
                            @can('edit file hosting folder')
                                <button type="button" class="fh-table__action-btn" title="{{ __('web/file-hosting.rename') }}"
                                        data-bs-toggle="modal" data-bs-target="#fhRenameFolderModal"
                                        data-id="{{ $folder['id'] }}" data-name="{{ $folder['name'] ?? '' }}">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button type="button" class="fh-table__action-btn" title="{{ __('web/file-hosting.move') }}"
                                        data-bs-toggle="modal" data-bs-target="#fhMoveFolderModal"
                                        data-id="{{ $folder['id'] }}">
                                    <i class="bi bi-folder-symlink"></i>
                                </button>
                                <button type="button" class="fh-table__action-btn" title="{{ __('web/file-hosting.share') }}"
                                        data-bs-toggle="modal" data-bs-target="#fhShareFolderModal"
                                        data-id="{{ $folder['id'] }}"
                                        data-visibility="{{ $folderVis }}"
                                        data-share-url="{{ $folder['share_url'] ?? '' }}">
                                    <i class="bi bi-share"></i>
                                </button>
                            @endcan
                            @can('delete file hosting folder')
                                <button type="button" class="fh-table__action-btn is-danger" title="{{ __('web/file-hosting.delete') }}"
                                        data-fh-ajax-action="delete-folder"
                                        data-fh-action-url="{{ route('file-hosting.folders.destroy', $folder['id']) }}"
                                        data-fh-method="DELETE"
                                        data-fh-confirm="{{ __('web/file-hosting.confirm_delete_folder') }}"
                                        >
                                    <i class="bi bi-trash"></i>
                                </button>
                            @endcan
                        </td>
                    </tr>
                @empty
                @endforelse

                        @foreach ($files as $file)
                            @php $fileVis = $file['visibility'] ?? $visPrivate; @endphp
                            <tr data-fh-row data-fh-entity="file" data-fh-id="{{ $file['id'] }}">
                        <td class="fh-table__select-cell">
                            <input type="checkbox" class="form-check-input" data-fh-select value="file:{{ $file['id'] }}" aria-label="{{ __('web/file-hosting.bulk.select_item') }}">
                        </td>
                        <td class="fh-table__icon-cell">
                            <a href="{{ route('file-hosting.files.show', array_filter(array_merge($ownerQuery, ['file' => $file['id']]))) }}">
                                @include('adminlte4.file-hosting.partials._file-icon', ['mime' => $file['mime'] ?? null, 'isFolder' => false])
                            </a>
                        </td>
                        <td>
                            <a href="{{ route('file-hosting.files.show', array_filter(array_merge($ownerQuery, ['file' => $file['id']]))) }}"
                               class="fh-table__name" title="{{ $file['name'] ?? '' }}">
                                <span class="fh-table__name-text" data-fh-name>{{ $file['name'] ?? '' }}</span>
                            </a>
                            @include('adminlte4.file-hosting.partials._microstock-badge', ['microstock' => $file['microstock'] ?? null, 'show' => $isMicrostock])
                        </td>
                        <td class="fh-table__type">{{ $file['mime'] ?? __('web/file-hosting.type_file') }}</td>
                        <td class="fh-table__size">{{ $file['size_human'] ?? '—' }}</td>
                        @if ($showRegion)
                            <td class="fh-table__region">
                                @php
                                    $regionKey = $file['region']
                                        ?? \App\Domain\FileHosting\Support\RegionResolver::keyForDisk((string) ($file['disk'] ?? ''));
                                @endphp
                                @if ($regionKey)
                                    <span class="badge text-bg-secondary fh-region-badge">{{ __('web/file-hosting.regions.'.$regionKey) }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                        @endif
                        <td data-fh-visibility-cell>@include('adminlte4.file-hosting.partials._visibility-badge', ['visibility' => $fileVis])</td>
                        <td class="fh-table__actions">
                            @if ($isMicrostock)
                                <button type="button" class="fh-table__action-btn" title="{{ __('web/file-hosting.microstock.review') }}"
                                        data-fh-ms-review="{{ $file['id'] }}">
                                    <i class="bi bi-stars"></i>
                                </button>
                            @endif
                            <a href="{{ route('file-hosting.files.show', array_filter(array_merge($ownerQuery, ['file' => $file['id']]))) }}"
                               class="fh-table__action-btn" title="{{ __('web/file-hosting.view') }}">
                                <i class="bi bi-eye"></i>
                            </a>
                            @can('edit file hosting file')
                                <button type="button" class="fh-table__action-btn" title="{{ __('web/file-hosting.rename') }}"
                                        data-bs-toggle="modal" data-bs-target="#fhRenameFileModal"
                                        data-id="{{ $file['id'] }}" data-name="{{ $file['name'] ?? '' }}">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button type="button" class="fh-table__action-btn" title="{{ __('web/file-hosting.move') }}"
                                        data-bs-toggle="modal" data-bs-target="#fhMoveFileModal"
                                        data-id="{{ $file['id'] }}">
                                    <i class="bi bi-folder-symlink"></i>
                                </button>
                                <button type="button" class="fh-table__action-btn" title="{{ __('web/file-hosting.share') }}"
                                        data-bs-toggle="modal" data-bs-target="#fhShareFileModal"
                                        data-id="{{ $file['id'] }}"
                                        data-visibility="{{ $fileVis }}"
                                        data-share-url="{{ $file['share_url'] ?? '' }}">
                                    <i class="bi bi-share"></i>
                                </button>
                            @endcan
                            @can('delete file hosting file')
                                <button type="button" class="fh-table__action-btn is-danger" title="{{ __('web/file-hosting.delete') }}"
                                        data-fh-ajax-action="delete-file"
                                        data-fh-action-url="{{ route('file-hosting.files.destroy', $file['id']) }}"
                                        data-fh-method="DELETE"
                                        data-fh-confirm="{{ __('web/file-hosting.confirm_delete_file') }}"
                                        >
                                    <i class="bi bi-trash"></i>
                                </button>
                            @endcan
                        </td>
                    </tr>
                @endforeach

                        @if ($folders->isEmpty() && $files->isEmpty())
                            <tr data-fh-empty>
                                <td colspan="{{ $tableColspan }}">
                                    <div class="fh-empty{{ $parentFolderShow ? ' fh-empty--compact' : '' }}">
                                        @unless ($parentFolderShow)
                                            <i class="bi bi-folder2-open fh-empty__icon"></i>
                                        @endunless
                                        <div class="fh-empty__text">{{ __('web/file-hosting.empty') }}</div>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Grid view --}}
        <div data-fh-view="grid" class="d-none">
            @if ($folders->isEmpty() && $files->isEmpty() && ! $parentFolderShow)
                <div class="fh-empty">
                    <i class="bi bi-folder2-open fh-empty__icon"></i>
                    <div class="fh-empty__text">{{ __('web/file-hosting.empty') }}</div>
                </div>
            @else
                <div class="fh-grid">
                    @if ($parentFolderShow)
                        <div class="fh-file-card is-parent" data-fh-row data-fh-entity="parent" data-fh-drop-folder="{{ $parentFolderDrop }}" title="{{ __('web/file-hosting.parent_folder') }}">
                            <div class="fh-file-card__icon">
                                <a href="{{ $parentFolderHref }}" data-fh-soft-nav aria-label="{{ __('web/file-hosting.parent_folder') }}">
                                    <i class="bi bi-folder2 fh-icon--folder fh-parent-row__icon" aria-hidden="true"></i>
                                </a>
                            </div>
                            <a href="{{ $parentFolderHref }}" class="fh-file-card__name" data-fh-soft-nav title="{{ __('web/file-hosting.parent_folder') }}">...</a>
                            <div class="fh-file-card__meta">{{ __('web/file-hosting.parent_folder') }}</div>
                        </div>
                    @endif
                    @foreach ($folders as $folder)
                        @php $folderVis = $folder['visibility'] ?? $visPrivate; @endphp
                        <div class="fh-file-card" data-fh-row data-fh-entity="folder" data-fh-id="{{ $folder['id'] }}">
                            <input type="checkbox" class="form-check-input fh-file-card__select" data-fh-select value="folder:{{ $folder['id'] }}" aria-label="{{ __('web/file-hosting.bulk.select_item') }}">
                            <div class="fh-file-card__badge">
                                @include('adminlte4.file-hosting.partials._visibility-badge', ['visibility' => $folderVis])
                            </div>
                            <div class="fh-file-card__icon">
                                <a href="{{ route('file-hosting.index', array_filter(array_merge($ownerQuery, ['folder_id' => $folder['id']]))) }}">
                                    @include('adminlte4.file-hosting.partials._file-icon', ['mime' => null, 'isFolder' => true])
                                </a>
                            </div>
                            <a href="{{ route('file-hosting.index', array_filter(array_merge($ownerQuery, ['folder_id' => $folder['id']]))) }}"
                               class="fh-file-card__name" title="{{ $folder['name'] ?? '' }}" data-fh-name>
                                {{ $folder['name'] ?? '' }}
                            </a>
                            <div class="fh-file-card__meta">{{ __('web/file-hosting.type_folder') }}</div>
                            <div class="fh-file-card__actions">
                                @can('edit file hosting folder')
                                    <button type="button" class="fh-table__action-btn" title="{{ __('web/file-hosting.rename') }}"
                                            data-bs-toggle="modal" data-bs-target="#fhRenameFolderModal"
                                            data-id="{{ $folder['id'] }}" data-name="{{ $folder['name'] ?? '' }}">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                @endcan
                                @can('delete file hosting folder')
                                    <button type="button" class="fh-table__action-btn is-danger" title="{{ __('web/file-hosting.delete') }}"
                                            data-fh-ajax-action="delete-folder"
                                            data-fh-action-url="{{ route('file-hosting.folders.destroy', $folder['id']) }}"
                                            data-fh-method="DELETE"
                                            data-fh-confirm="{{ __('web/file-hosting.confirm_delete_folder') }}"
                                            >
                                        <i class="bi bi-trash"></i>
                                    </button>
                                @endcan
                            </div>
                        </div>
                    @endforeach

                    @foreach ($files as $file)
                        @php $fileVis = $file['visibility'] ?? $visPrivate; @endphp
                        <div class="fh-file-card" data-fh-row data-fh-entity="file" data-fh-id="{{ $file['id'] }}">
                            <input type="checkbox" class="form-check-input fh-file-card__select" data-fh-select value="file:{{ $file['id'] }}" aria-label="{{ __('web/file-hosting.bulk.select_item') }}">
                            <div class="fh-file-card__badge">
                                @include('adminlte4.file-hosting.partials._visibility-badge', ['visibility' => $fileVis])
                                @include('adminlte4.file-hosting.partials._microstock-badge', ['microstock' => $file['microstock'] ?? null, 'show' => $isMicrostock])
                            </div>
                            <div class="fh-file-card__icon">
                                <a href="{{ route('file-hosting.files.show', array_filter(array_merge($ownerQuery, ['file' => $file['id']]))) }}">
                                    @include('adminlte4.file-hosting.partials._file-icon', ['mime' => $file['mime'] ?? null, 'isFolder' => false])
                                </a>
                            </div>
                            <a href="{{ route('file-hosting.files.show', array_filter(array_merge($ownerQuery, ['file' => $file['id']]))) }}"
                               class="fh-file-card__name" title="{{ $file['name'] ?? '' }}" data-fh-name>
                                {{ $file['name'] ?? '' }}
                            </a>
                            <div class="fh-file-card__meta">
                                {{ $file['size_human'] ?? '—' }}
                                @if ($showRegion)
                                    @php
                                        $gridRegionKey = $file['region']
                                            ?? \App\Domain\FileHosting\Support\RegionResolver::keyForDisk((string) ($file['disk'] ?? ''));
                                    @endphp
                                    @if ($gridRegionKey) · <span class="fh-file-card__region">{{ __('web/file-hosting.regions.'.$gridRegionKey) }}</span>@endif
                                @endif
                            </div>
                            <div class="fh-file-card__actions">
                                @if ($isMicrostock)
                                    <button type="button" class="fh-table__action-btn" title="{{ __('web/file-hosting.microstock.review') }}"
                                            data-fh-ms-review="{{ $file['id'] }}">
                                        <i class="bi bi-stars"></i>
                                    </button>
                                @endif
                                <a href="{{ route('file-hosting.files.show', array_filter(array_merge($ownerQuery, ['file' => $file['id']]))) }}"
                                   class="fh-table__action-btn" title="{{ __('web/file-hosting.view') }}">
                                    <i class="bi bi-eye"></i>
                                </a>
                                @can('edit file hosting file')
                                    <button type="button" class="fh-table__action-btn" title="{{ __('web/file-hosting.rename') }}"
                                            data-bs-toggle="modal" data-bs-target="#fhRenameFileModal"
                                            data-id="{{ $file['id'] }}" data-name="{{ $file['name'] ?? '' }}">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                @endcan
                                @can('delete file hosting file')
                                    <button type="button" class="fh-table__action-btn is-danger" title="{{ __('web/file-hosting.delete') }}"
                                            data-fh-ajax-action="delete-file"
                                            data-fh-action-url="{{ route('file-hosting.files.destroy', $file['id']) }}"
                                            data-fh-method="DELETE"
                                            data-fh-confirm="{{ __('web/file-hosting.confirm_delete_file') }}"
                                            >
                                        <i class="bi bi-trash"></i>
                                    </button>
                                @endcan
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
        </div>{{-- /data-fh-panel=browse --}}

        {{-- SPA trash panel (soft-rendered; empty shell on cold load of index) --}}
        <div data-fh-panel="trash" class="d-none" hidden>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">{{ __('web/file-hosting.trash_title') }}</h5>
                <a href="{{ route('file-hosting.index', $ownerQuery) }}" class="btn btn-outline-secondary btn-sm" data-fh-soft-nav>
                    <i class="bi bi-arrow-left me-1"></i> {{ __('web/file-hosting.back') }}
                </a>
            </div>
            <div class="table-responsive">
                <table class="table fh-table">
                    <thead>
                        <tr>
                            <th class="fh-table__icon-cell"></th>
                            <th>{{ __('web/file-hosting.columns.name') }}</th>
                            <th>{{ __('web/file-hosting.columns.type') }}</th>
                            <th>{{ __('web/file-hosting.columns.deleted_at') }}</th>
                            <th class="text-end">{{ __('web/file-hosting.columns.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody data-fh-trash-body>
                        <tr>
                            <td colspan="5">
                                <div class="fh-empty">
                                    <i class="bi bi-trash3 fh-empty__icon"></i>
                                    <div class="fh-empty__text">{{ __('web/file-hosting.trash_empty') }}</div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- SPA show panel (file detail soft-rendered) --}}
        <div data-fh-panel="show" class="d-none" hidden></div>
    </div>

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
                        <div class="border rounded p-2 bg-light">
                            <button class="btn btn-link btn-sm p-0 text-decoration-none" type="button"
                                    data-bs-toggle="collapse" data-bs-target="#fhDescribePreviewCollapse"
                                    aria-expanded="false" aria-controls="fhDescribePreviewCollapse">
                                {{ __('web/file-hosting.microstock.describe_preview') }}
                            </button>
                            <div class="collapse" id="fhDescribePreviewCollapse">
                                <pre class="small mb-1 mt-2 text-wrap" style="white-space: pre-wrap;" data-fh-describe-preview-text>—</pre>
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
                        <div class="form-control-plaintext small border rounded px-2 py-1 bg-light" data-fh-ms-description>—</div>
                    </div>
                    <div>
                        <label class="form-label small text-muted mb-0">{{ __('web/file-hosting.microstock.panel_category_label') }}</label>
                        <div class="form-control-plaintext small border rounded px-2 py-1 bg-light" data-fh-ms-category>—</div>
                    </div>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-primary" data-fh-ms-save>{{ __('web/file-hosting.microstock.panel_save') }}</button>
                <button type="button" class="btn btn-outline-secondary" data-fh-ms-reanalyze>{{ __('web/file-hosting.microstock.panel_reanalyze') }}</button>
            </div>
        </div>
    </div>

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
                        <div class="fs-5 fw-semibold" data-fh-msu-balance>—</div>
                    </div></div>
                </div>
                <div class="col-6">
                    <div class="card h-100"><div class="card-body py-2 px-3">
                        <div class="text-muted small">{{ __('web/microstock.usage.estimate') }}</div>
                        <div class="fs-6 fw-semibold" data-fh-msu-estimate>—</div>
                    </div></div>
                </div>
            </div>
            <div class="row g-2 mb-3 d-none" data-fh-msu-daily-card>
                <div class="col-12">
                    <div class="card"><div class="card-body py-2 px-3">
                        <div class="text-muted small">{{ __('web/microstock.usage.daily_free') }}</div>
                        <div class="fs-6 fw-semibold" data-fh-msu-daily>—</div>
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
                                    <div class="fw-semibold" data-fh-msu-platform-provider>—</div>
                                </div></div>
                            </div>
                            <div class="col-6 col-md-4">
                                <div class="card h-100"><div class="card-body py-2 px-3">
                                    <div class="text-muted small">{{ __('web/microstock.usage.platform_billable') }}</div>
                                    <div class="fw-semibold" data-fh-msu-platform-billable>—</div>
                                </div></div>
                            </div>
                            <div class="col-6 col-md-4">
                                <div class="card h-100"><div class="card-body py-2 px-3">
                                    <div class="text-muted small">{{ __('web/microstock.usage.platform_margin') }}</div>
                                    <div class="fw-semibold" data-fh-msu-platform-margin>—</div>
                                </div></div>
                            </div>
                            <div class="col-6 col-md-4">
                                <div class="card h-100"><div class="card-body py-2 px-3">
                                    <div class="text-muted small">{{ __('web/microstock.usage.platform_events') }}</div>
                                    <div class="fw-semibold" data-fh-msu-platform-events>—</div>
                                </div></div>
                            </div>
                            <div class="col-6 col-md-4">
                                <div class="card h-100"><div class="card-body py-2 px-3">
                                    <div class="text-muted small">{{ __('web/microstock.usage.platform_users') }}</div>
                                    <div class="fw-semibold" data-fh-msu-platform-users>—</div>
                                </div></div>
                            </div>
                            <div class="col-6 col-md-4">
                                <div class="card h-100"><div class="card-body py-2 px-3">
                                    <div class="text-muted small">{{ __('web/microstock.usage.platform_daily_free_cost') }}</div>
                                    <div class="fw-semibold" data-fh-msu-platform-daily-free>—</div>
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

    {{-- Rename File Modal --}}
    @can('edit file hosting file')
        <div class="modal fade" id="fhRenameFileModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="#" class="modal-content" id="fhRenameFileForm">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="return_folder_id" value="{{ $currentFolderId }}">
                    @if ($ownerUserId)
                        <input type="hidden" name="user_id" value="{{ $ownerUserId }}">
                        @if($workspaceId)
                            <input type="hidden" name="workspace_id" value="{{ $workspaceId }}">
                        @endif
                    @endif
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-pencil me-1"></i>{{ __('web/file-hosting.rename') }}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label" for="fh-rename-file-name">{{ __('web/file-hosting.file_name') }}</label>
                        <input type="text" name="name" id="fh-rename-file-name" class="form-control" required maxlength="255">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('web/file-hosting.cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('web/file-hosting.save_rename') }}</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Share File Modal --}}
        <div class="modal fade" id="fhShareFileModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="#" class="modal-content" id="fhShareFileForm">
                    @csrf
                    @method('PATCH')
                    @if ($ownerUserId)
                        <input type="hidden" name="user_id" value="{{ $ownerUserId }}">
                        @if($workspaceId)
                            <input type="hidden" name="workspace_id" value="{{ $workspaceId }}">
                        @endif
                    @endif
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-share me-1"></i>{{ __('web/file-hosting.share') }}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label" for="fh-share-file-vis">{{ __('web/file-hosting.visibility.label') }}</label>
                        <select name="visibility" id="fh-share-file-vis" class="form-select mb-3">
                            <option value="{{ $visPrivate }}">{{ __('web/file-hosting.visibility.private') }}</option>
                            <option value="{{ $visLink }}">{{ __('web/file-hosting.visibility.anyone_with_link') }}</option>
                        </select>
                        <div id="fh-share-file-link-wrap" class="d-none">
                            <div class="fh-share-box">
                                <div class="fh-share-box__label">{{ __('web/file-hosting.share_link') }}</div>
                                <div class="input-group input-group-sm">
                                    <input type="text" class="form-control" id="fh-share-file-link" readonly>
                                    <button type="button" class="btn btn-outline-primary" data-fh-copy="fh-share-file-link">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                </div>
                                <div class="form-text mt-2">{{ __('web/file-hosting.visibility.hint_link') }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('web/file-hosting.cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('web/file-hosting.save') }}</button>
                    </div>
                </form>
            </div>
        </div>

        @include('adminlte4.file-hosting.partials._move-modal', [
            'modalId' => 'fhMoveFileModal',
            'formId' => 'fhMoveFileForm',
            'title' => __('web/file-hosting.move_title'),
            'ownerUserId' => $ownerUserId,
        ])
    @endcan

    {{-- Rename / Share / Move Folder Modals --}}
    @can('edit file hosting folder')
        <div class="modal fade" id="fhRenameFolderModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="#" class="modal-content" id="fhRenameFolderForm">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="return_folder_id" value="{{ $currentFolderId }}">
                    @if ($ownerUserId)
                        <input type="hidden" name="user_id" value="{{ $ownerUserId }}">
                        @if($workspaceId)
                            <input type="hidden" name="workspace_id" value="{{ $workspaceId }}">
                        @endif
                    @endif
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-pencil me-1"></i>{{ __('web/file-hosting.rename') }}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label" for="fh-rename-folder-name">{{ __('web/file-hosting.folder_name') }}</label>
                        <input type="text" name="name" id="fh-rename-folder-name" class="form-control" required maxlength="255">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('web/file-hosting.cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('web/file-hosting.save_rename') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="fhShareFolderModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="#" class="modal-content" id="fhShareFolderForm">
                    @csrf
                    @method('PATCH')
                    @if ($ownerUserId)
                        <input type="hidden" name="user_id" value="{{ $ownerUserId }}">
                        @if($workspaceId)
                            <input type="hidden" name="workspace_id" value="{{ $workspaceId }}">
                        @endif
                    @endif
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-share me-1"></i>{{ __('web/file-hosting.share') }}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label" for="fh-share-folder-vis">{{ __('web/file-hosting.visibility.label') }}</label>
                        <select name="visibility" id="fh-share-folder-vis" class="form-select mb-3">
                            <option value="{{ $visPrivate }}">{{ __('web/file-hosting.visibility.private') }}</option>
                            <option value="{{ $visLink }}">{{ __('web/file-hosting.visibility.anyone_with_link') }}</option>
                        </select>
                        <div id="fh-share-folder-link-wrap" class="d-none">
                            <div class="fh-share-box">
                                <div class="fh-share-box__label">{{ __('web/file-hosting.share_link') }}</div>
                                <div class="input-group input-group-sm">
                                    <input type="text" class="form-control" id="fh-share-folder-link" readonly>
                                    <button type="button" class="btn btn-outline-primary" data-fh-copy="fh-share-folder-link">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                </div>
                                <div class="form-text mt-2">{{ __('web/file-hosting.visibility.hint_folder') }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('web/file-hosting.cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('web/file-hosting.save') }}</button>
                    </div>
                </form>
            </div>
        </div>

        @include('adminlte4.file-hosting.partials._move-modal', [
            'modalId' => 'fhMoveFolderModal',
            'formId' => 'fhMoveFolderForm',
            'title' => __('web/file-hosting.move_title'),
            'ownerUserId' => $ownerUserId,
        ])
    @endcan

    @vite(['resources/css/file-hosting/file-hosting.css', 'resources/js/file-hosting/file-manager.js'])
</x-adminlte4::layouts.app>
