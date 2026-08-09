@php
    $routeKey = $creatorRouteKey ?? $creator['slug'] ?? $creator['id'] ?? '';
    $creatorSlug = $creator['slug'] ?? $routeKey;
    $discordVerifyEnabled = $discordVerifyEnabled ?? false;
    $discordConnected = $discordConnected ?? false;
    $statusLabels = [
        'pending' => __('web/vtual-orphans.channels.status_pending'),
        'active' => __('web/vtual-orphans.channels.status_active'),
        'disconnected' => __('web/vtual-orphans.channels.status_disconnected'),
        'archived' => __('web/vtual-orphans.channels.status_archived'),
    ];
    $actionLabels = [
        'promote' => __('web/vtual.profiles.promote_channel'),
        'refresh' => __('web/vtual.refresh_channel'),
        'archive' => __('web/vtual.archive_channel'),
        'restore' => __('web/vtual.restore_channel'),
        'private' => __('web/vtual.profiles.channel_private_discovered'),
        'confirm' => [
            'archive_title' => __('web/vtual.confirm.archive_channel_title'),
            'archive_body' => __('web/vtual.confirm.archive_channel_body'),
            'archive_submit' => __('web/vtual.archive_channel'),
            'restore_title' => __('web/vtual.confirm.restore_channel_title'),
            'restore_body' => __('web/vtual.confirm.restore_channel_body'),
            'restore_submit' => __('web/vtual.restore_channel'),
        ],
    ];
@endphp
<h5 class="mb-2">{{ __('web/vtual.profiles.section_channels') }}</h5>
@if ($canMutate)
    @include(config('web.layout').'.vtual.partials.channel-add-form', [
        'action' => route('vtual.profiles.channels.store', $routeKey),
        'transPrefix' => 'web/vtual',
        'formKind' => 'profiles',
        'helpElId' => 'profile-channel-form-help',
        'platforms' => $platforms,
    ])
@endif

<h6 class="mb-2">{{ __('web/vtual.profiles.section_channel_claims') }}</h6>
<div class="table-responsive mb-3">
    <table class="table table-sm table-hover align-middle mb-0" aria-label="{{ __('web/vtual.table.caption_channel_claims') }}">
        <caption class="visually-hidden">{{ __('web/vtual.table.caption_channel_claims') }}</caption>
        <thead>
            <tr>
                <th>{{ __('web/vtual.platform') }}</th>
                <th>{{ __('web/vtual.external_id') }}</th>
                <th>{{ __('web/vtual.handle') }}</th>
                <th>{{ __('web/vtual.profiles.claim_token') }}</th>
                <th>{{ __('web/vtual.profiles.claim_expires') }}</th>
                @if ($canMutate)<th class="text-end">{{ __('web/vtual.table.label_actions') }}</th>@endif
            </tr>
        </thead>
        <tbody id="profile-channel-claims-tbody">
            @forelse ($channelClaims as $claim)
                @php
                    $platformKey = $claim['platform']['key'] ?? '';
                    $showDiscordVerify = $discordVerifyEnabled && $platformKey !== '' && $platformKey !== 'kick';
                @endphp
                <tr>
                    <td>{{ $claim['platform']['name'] ?? $platformKey }}</td>
                    <td><code>{{ $claim['external_id'] ?? '' }}</code></td>
                    <td>{{ $claim['handle'] ?? '-' }}</td>
                    <td>
                        <code class="user-select-all">{{ $claim['token'] ?? '' }}</code>
                        <div class="form-text">{{ __('web/vtual.profiles.claim_token_help') }}</div>
                    </td>
                    <td class="small text-muted">{{ $claim['expires_at'] ?? '-' }}</td>
                    @if ($canMutate)
                        <td class="text-end text-nowrap">
                            <div class="btn-group" role="group" aria-label="{{ __('web/vtual.table.label_actions') }}">
                                <x-adminlte4::form.ajax :action="route('vtual.profiles.channel-claims.verify', [$routeKey, $claim['id']])" method="POST" class="d-inline">
                                    <button type="submit" class="btn btn-outline-primary btn-sm">
                                        {{ __('web/vtual.profiles.verify_channel') }}
                                    </button>
                                </x-adminlte4::form.ajax>
                                @if ($showDiscordVerify)
                                    @if ($discordConnected)
                                        <x-adminlte4::form.ajax :action="route('vtual.profiles.channel-claims.verify-discord', [$routeKey, $claim['id']])" method="POST" class="d-inline">
                                            <button type="submit" class="btn btn-outline-primary btn-sm">
                                                {{ __('web/vtual-vtuber.claim_discord_verify_button') }}
                                            </button>
                                        </x-adminlte4::form.ajax>
                                    @else
                                        <a
                                            href="{{ route('auth.discord.redirect', ['intent' => 'channel_claim_verify', 'slug' => $creatorSlug, 'claim_id' => $claim['id']]) }}"
                                            class="btn btn-outline-primary btn-sm"
                                        >
                                            {{ __('web/vtual-vtuber.claim_discord_connect_cta') }}
                                        </a>
                                    @endif
                                @endif
                                <x-adminlte4::form.ajax :action="route('vtual.profiles.channel-claims.cancel', [$routeKey, $claim['id']])" method="POST" class="d-inline">
                                    <button type="button"
                                            class="btn btn-outline-secondary btn-sm"
                                            data-bs-toggle="modal"
                                            data-bs-target="#vtualConfirmModal"
                                            data-confirm-title="{{ __('web/vtual.confirm.cancel_claim_title') }}"
                                            data-confirm-body="{{ __('web/vtual.confirm.cancel_claim_body') }}"
                                            data-confirm-submit-label="{{ __('web/vtual.profiles.cancel_channel_claim') }}">
                                        {{ __('web/vtual.profiles.cancel_channel_claim') }}
                                    </button>
                                </x-adminlte4::form.ajax>
                                @if (!empty($canAcceptAnyway))
                                    <x-adminlte4::form.ajax :action="route('vtual.profiles.channel-claims.accept-anyway', [$routeKey, $claim['id']])" method="POST" class="d-inline">
                                        <button type="button"
                                                class="btn btn-outline-warning btn-sm"
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
                    @endif
                </tr>
            @empty
                <tr id="profile-channel-claims-empty" data-empty-row>
                    <td colspan="{{ $canMutate ? 6 : 5 }}">
                        <span class="text-muted small">{{ __('web/vtual.profiles.empty_channel_claims') }}</span>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<h6 class="mb-2">{{ __('web/vtual.profiles.section_active_channels') }}</h6>
<div class="table-responsive mb-4" id="profile-active-channels-table" data-status-labels='@json($statusLabels)' data-action-labels='@json($actionLabels)'>
    <table class="table table-hover align-middle mb-0" aria-label="{{ __('web/vtual.table.caption_channels') }}">
        <caption class="visually-hidden">{{ __('web/vtual.table.caption_channels') }}</caption>
        <thead>
            <tr>
                <th class="col-1">{{ __('web/vtual-orphans.channels.avatar') }}</th>
                <th>{{ __('web/vtual.platform') }}</th>
                <th>{{ __('web/vtual-orphans.channels.profile_or_id') }}</th>
                <th>{{ __('web/vtual.handle') }}</th>
                <th>{{ __('web/vtual.bio') }}</th>
                <th>{{ __('web/vtual.status') }}</th>
                @if ($canMutate)<th class="text-end">{{ __('web/vtual.table.label_actions') }}</th>@endif
            </tr>
        </thead>
        <tbody id="profile-active-channels-tbody">
            @forelse ($channels as $channel)
                @php
                    $desc = (string) ($channel['description'] ?? '');
                    $descShort = $desc === '' ? '—' : \Illuminate\Support\Str::limit($desc, 80);
                @endphp
                <tr @if (!empty($channel['id'])) data-channel-id="{{ $channel['id'] }}" @endif>
                    <td class="text-nowrap">
                        @if (! empty($channel['avatar_url']))
                            <img src="{{ $channel['avatar_url'] }}" alt="{{ $channel['display_name'] ?? $channel['handle'] ?? '' }}"
                                 class="rounded-circle object-fit-cover"
                                 width="36" height="36" loading="lazy"
                                 referrerpolicy="no-referrer">
                        @else
                            <span class="text-muted small">—</span>
                        @endif
                    </td>
                    <td>{{ $channel['platform']['name'] ?? $channel['platform']['key'] ?? '' }}</td>
                    <td>
                        @if (!empty($channel['profile_input']))
                            <code class="user-select-all">{{ $channel['profile_input'] }}</code>
                        @else
                            <code>{{ $channel['external_id'] ?? '' }}</code>
                        @endif
                    </td>
                    <td>{{ $channel['handle'] ?? '—' }}</td>
                    <td class="small text-muted" title="{{ $desc }}">{{ $descShort }}</td>
                    <td>
                        <x-adminlte4::vtual.status-badge :status="$channel['status'] ?? ''" />
                        @if (($channel['visibility'] ?? 'public') === 'private')
                            <span class="badge text-bg-secondary ms-1">{{ __('web/vtual.profiles.channel_private_discovered') }}</span>
                        @endif
                        @if (!empty($channel['resolve_error']))
                            <div class="form-text text-danger">{{ $channel['resolve_error'] }}</div>
                        @endif
                    </td>
                    @if ($canMutate)
                        <td class="text-end text-nowrap">
                            <div class="btn-group" role="group" aria-label="{{ __('web/vtual.table.label_actions') }}">
                                @if (($channel['visibility'] ?? 'public') === 'private' && ($channel['discovery_source'] ?? '') === 'discord_connection')
                                    <x-adminlte4::form.ajax :action="route('vtual.profiles.channels.promote', [$routeKey, $channel['id']])" method="POST" class="d-inline">
                                        <button type="submit" class="btn btn-outline-primary btn-sm">
                                            {{ __('web/vtual.profiles.promote_channel') }}
                                        </button>
                                    </x-adminlte4::form.ajax>
                                @endif
                                <x-adminlte4::form.ajax :action="route('vtual.profiles.channels.refresh', [$routeKey, $channel['id']])" method="POST" class="d-inline">
                                    <button type="submit" class="btn btn-outline-secondary btn-sm">
                                        {{ __('web/vtual.refresh_channel') }}
                                    </button>
                                </x-adminlte4::form.ajax>
                                <x-adminlte4::form.ajax :action="route('vtual.profiles.channels.archive', [$routeKey, $channel['id']])" method="POST" class="d-inline">
                                    <button type="button"
                                            class="btn btn-outline-danger btn-sm"
                                            data-bs-toggle="modal"
                                            data-bs-target="#vtualConfirmModal"
                                            data-confirm-title="{{ __('web/vtual.confirm.archive_channel_title') }}"
                                            data-confirm-body="{{ __('web/vtual.confirm.archive_channel_body') }}"
                                            data-confirm-submit-label="{{ __('web/vtual.archive_channel') }}">
                                        {{ __('web/vtual.archive_channel') }}
                                    </button>
                                </x-adminlte4::form.ajax>
                            </div>
                        </td>
                    @endif
                </tr>
            @empty
                <tr id="profile-active-channels-empty" data-empty-row>
                    <td colspan="{{ $canMutate ? 7 : 6 }}">
                        <x-adminlte4::empty-state icon="bi-broadcast" :title="__('web/vtual.empty_channels')" />
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<h6 class="mb-2">{{ __('web/vtual.profiles.section_archived_channels') }}</h6>
<div class="table-responsive mb-4">
    <table class="table table-hover align-middle mb-0" aria-label="{{ __('web/vtual.table.caption_archived_channels') }}">
        <caption class="visually-hidden">{{ __('web/vtual.table.caption_archived_channels') }}</caption>
        <thead>
            <tr>
                <th>{{ __('web/vtual.platform') }}</th>
                <th>{{ __('web/vtual.external_id') }}</th>
                <th>{{ __('web/vtual.handle') }}</th>
                <th>{{ __('web/vtual.status') }}</th>
                @if ($canMutate)<th class="text-end">{{ __('web/vtual.table.label_actions') }}</th>@endif
            </tr>
        </thead>
        <tbody id="profile-archived-channels-tbody">
            @forelse (($archivedChannels ?? collect()) as $channel)
                <tr data-channel-id="{{ $channel['id'] ?? '' }}">
                    <td>{{ $channel['platform']['name'] ?? $channel['platform']['key'] ?? '' }}</td>
                    <td><code>{{ $channel['external_id'] ?? '' }}</code></td>
                    <td>{{ $channel['handle'] ?? '—' }}</td>
                    <td><x-adminlte4::vtual.status-badge :status="$channel['status'] ?? ''" /></td>
                    @if ($canMutate)
                        <td class="text-end text-nowrap">
                            <x-adminlte4::form.ajax :action="route('vtual.profiles.channels.restore', [$routeKey, $channel['id']])" method="POST" class="d-inline">
                                <button type="button"
                                        class="btn btn-outline-success btn-sm"
                                        data-bs-toggle="modal"
                                        data-bs-target="#vtualConfirmModal"
                                        data-confirm-title="{{ __('web/vtual.confirm.restore_channel_title') }}"
                                        data-confirm-body="{{ __('web/vtual.confirm.restore_channel_body') }}"
                                        data-confirm-submit-label="{{ __('web/vtual.restore_channel') }}"
                                        data-confirm-submit-class="btn-success">
                                    {{ __('web/vtual.restore_channel') }}
                                </button>
                            </x-adminlte4::form.ajax>
                        </td>
                    @endif
                </tr>
            @empty
                <tr id="profile-archived-channels-empty" data-empty-row>
                    <td colspan="{{ $canMutate ? 5 : 4 }}">
                        <span class="text-muted small">{{ __('web/vtual.profiles.empty_archived_channels') }}</span>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
