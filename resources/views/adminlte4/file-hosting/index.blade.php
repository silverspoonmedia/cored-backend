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
        'billing' => [
            'daily_free' => [
                'enabled' => (bool) setting_config('microstock.daily_free.enabled', false),
            ],
        ],
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
            'adminUsageUrl' => ($canViewPlatformBilling ?? false)
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
                'billingMode' => __('web/file-hosting.microstock.describe_validation_billing_mode'),
            ],
            'billingMode' => [
                'label' => __('web/file-hosting.microstock.describe_billing_mode_label'),
                'free' => __('web/file-hosting.microstock.describe_billing_mode_free'),
                'paid' => __('web/file-hosting.microstock.describe_billing_mode_paid'),
                'hint' => __('web/file-hosting.microstock.describe_billing_mode_hint'),
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

        @include('adminlte4.file-hosting.partials._toolbar-zone')

        @include('adminlte4.file-hosting.partials._browse-views')

        @include('adminlte4.file-hosting.partials._trash-show-panels')

    </div>

    @include('adminlte4.file-hosting.partials._workspace-modals')

    @include('adminlte4.file-hosting.partials._microstock-review-panel')

    @include('adminlte4.file-hosting.partials._usage-offcanvas')

    @include('adminlte4.file-hosting.partials._file-folder-modals')

    @vite('resources/css/file-hosting/file-hosting.css')

    <x-slot:scripts>
        @vite('resources/js/file-hosting/file-manager.js')
    </x-slot:scripts>
</x-adminlte4::layouts.app>
