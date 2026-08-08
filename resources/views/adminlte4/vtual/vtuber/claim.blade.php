@php
    $displayName = $profile['display_name'] ?? __('web/vtual-vtuber.title');
    $slug = $profile['slug'] ?? '';
    $creatorId = $profile['id'] ?? '';
    $bootstrap = [
        'profile' => $profile,
        'activeClaim' => $activeClaim,
        'realtime' => $claimRealtime,
        'profileUrl' => $profileUrl ?? $profile['profile_url'] ?? route('vtual.vtuber.show', $slug),
        'discordConnected' => $discordConnected ?? false,
        'discordVerifyEnabled' => $discordVerifyEnabled ?? false,
        'canAcceptAnyway' => $canAcceptAnyway ?? false,
        'routes' => [
            'startClaim' => route('vtual.vtuber.claim.start', $slug),
            'verifyClaim' => route('vtual.vtuber.claim.verify', $slug),
            'cancelClaim' => route('vtual.vtuber.claim.cancel', $slug),
            'acceptAnywayClaim' => route('vtual.vtuber.claim.accept-anyway', $slug),
            'discordVerifyClaim' => route('vtual.vtuber.claim.discord-verify', $slug),
            'discordClaimChannel' => route('vtual.vtuber.claim.discord', $slug),
            'discordRedirect' => route('auth.discord.redirect'),
        ],
        'labels' => [
            'claimPageTitle' => __('web/vtual-vtuber.claim_page_title'),
            'claimPageSubtitle' => __('web/vtual-vtuber.claim_page_subtitle'),
            'backToProfile' => __('web/vtual-vtuber.claim_back_to_profile'),
            'connected' => __('web/vtual-vtuber.claim_realtime_connected'),
            'connecting' => __('web/vtual-vtuber.claim_realtime_connecting'),
            'degraded' => __('web/vtual-vtuber.claim_realtime_degraded'),
            'realtimeNotice' => __('web/vtual-vtuber.claim_verify_queue_notice_realtime'),
            'degradedNotice' => __('web/vtual-vtuber.claim_verify_queue_notice_degraded'),
            'started' => __('web/vtual.ajax.claim_started'),
            'openLink' => __('web/vtual-vtuber.open_link'),
            'proofChannelLabel' => __('web/vtual-vtuber.claim_proof_channel_label'),
            'queued' => __('web/vtual-vtuber.claim_verify_queued'),
            'processing' => __('web/vtual-vtuber.claim_progress_processing'),
            'verified' => __('web/vtual-vtuber.claim_progress_verified'),
            'failed' => __('web/vtual-vtuber.claim_progress_failed'),
            'maxAttemptsBlocked' => __('web/vtual-vtuber.claim_progress_max_attempts'),
            'blockedUntil' => __('web/vtual-vtuber.claim_blocked_until', ['until' => ':until']),
            'queue_pending' => __('web/vtual-vtuber.claim_queue_status_pending'),
            'queue_processing' => __('web/vtual-vtuber.claim_queue_status_processing'),
            'activeHeading' => __('web/vtual-vtuber.claim_active_heading'),
            'tokenLabel' => __('web/vtual-vtuber.claim_token_label'),
            'tokenHelp' => __('web/vtual.claims.token_help'),
            'attemptsRemaining' => __('web/vtual-vtuber.claim_attempts_remaining'),
            'queueStatusLabel' => __('web/vtual-vtuber.claim_queue_status_label'),
            'verifyButton' => __('web/vtual-vtuber.claim_verify_button'),
            'cancel' => __('web/vtual-vtuber.claim_cancel'),
            'verifyWithChannel' => __('web/vtual-vtuber.claim_verify_with_channel'),
            'claimWithDiscord' => __('web/vtual-vtuber.claim_verify_with_discord'),
            'discordConnectCta' => __('web/vtual-vtuber.claim_discord_connect_cta'),
            'discordVerifyButton' => __('web/vtual-vtuber.claim_discord_verify_button'),
            'discordPanelHint' => __('web/vtual-vtuber.claim_discord_panel_hint'),
            'channelsHeading' => __('web/vtual-vtuber.claim_channels_heading'),
            'noChannelsNote' => __('web/vtual-vtuber.claim_no_channels_note'),
            'stepsHeading' => __('web/vtual-vtuber.claim_steps_heading'),
            'step1' => __('web/vtual-vtuber.claim_step_1'),
            'step2' => __('web/vtual-vtuber.claim_step_2'),
            'step3' => __('web/vtual-vtuber.claim_step_3'),
            'step4' => __('web/vtual-vtuber.claim_step_4'),
            'step5' => __('web/vtual-vtuber.claim_step_5'),
            'acceptAnyway' => __('web/vtual.claims.accept_anyway'),
            'acceptAnywayHelp' => __('web/vtual.claims.accept_anyway_help'),
            'acceptAnywayConfirmTitle' => __('web/vtual.confirm.accept_anyway_title'),
            'acceptAnywayConfirmBody' => __('web/vtual.confirm.accept_anyway_body'),
        ],
    ];
    $mountFailed = __('web/vtual-vtuber.claim_realtime_degraded');
@endphp

<x-adminlte4::layouts.app :title="__('web/vtual-vtuber.claim_page_title').' — '.$displayName">
    @php
        \Diglactic\Breadcrumbs\Breadcrumbs::setCurrentRoute('vtual.vtuber.claim', $displayName);
    @endphp

    <div id="vue-vtual-claim">
        <script type="application/json" data-vue-vtual-claim data-vue-mount-failed="{{ $mountFailed }}">@json($bootstrap)</script>
    </div>

    <x-slot:scripts>
        @vite('resources/js/vue/islands/vtual-claim/main.js')
    </x-slot:scripts>
</x-adminlte4::layouts.app>
