@php
    $creatorId = $creator['id'] ?? '';
    $channels = $creator['channels'] ?? [];
    $statusLabels = [
        'pending' => __('web/vtual-orphans.channels.status_pending'),
        'active' => __('web/vtual-orphans.channels.status_active'),
        'disconnected' => __('web/vtual-orphans.channels.status_disconnected'),
        'archived' => __('web/vtual-orphans.channels.status_archived'),
    ];
@endphp

<hr class="my-4">

<h5 class="mb-2">{{ __('web/vtual-orphans.channels.section_title') }}</h5>
<p class="text-muted small mb-3">{{ __('web/vtual-orphans.channels.section_help') }}</p>

<x-adminlte4::form.ajax
    :action="route('vtual.orphans.channels.store', $creatorId)"
    method="POST"
    class="border rounded p-3 mb-3"
    data-orphan-channels-form
    :showAlert="false"
    data-help-youtube="{{ __('web/vtual-orphans.channels.profile_help_youtube') }}"
    data-help-twitch="{{ __('web/vtual-orphans.channels.profile_help_twitch') }}"
    data-placeholder-youtube="{{ __('web/vtual-orphans.channels.profile_placeholder_youtube') }}"
    data-placeholder-twitch="{{ __('web/vtual-orphans.channels.profile_placeholder_twitch') }}"
>
    <div class="row g-2 align-items-end">
        <div class="col-md-3" data-form-field="platform_key">
            <label class="form-label" for="platform_key">{{ __('web/vtual-orphans.channels.platform') }}</label>
            <select id="platform_key" name="platform_key" class="form-select form-select-sm" required>
                @foreach ($platforms as $platform)
                    @if (!empty($platform['enabled']))
                        <option value="{{ $platform['key'] }}">{{ $platform['name'] }}</option>
                    @endif
                @endforeach
            </select>
        </div>
        <div class="col-md-6" data-form-field="profile">
            <label class="form-label" for="profile_input">{{ __('web/vtual-orphans.channels.profile') }}</label>
            <input type="text" id="profile_input" name="profile" class="form-control form-control-sm font-monospace"
                   required maxlength="512" placeholder="{{ __('web/vtual-orphans.channels.profile_placeholder_youtube') }}">
        </div>
        <div class="col-md-3">
            <label class="form-label invisible" aria-hidden="true">&nbsp;</label>
            <x-adminlte4::form.submit :label="__('web/vtual-orphans.channels.add_button')" class="btn btn-primary btn-sm w-100" />
        </div>
    </div>
    <div class="row g-1">
        <div class="col-md-3">
            <x-adminlte4::form.error name="platform_key" />
        </div>
        <div class="col-md-6">
            <x-adminlte4::form.error name="profile" />
        </div>
        <div class="col-md-3"></div>
    </div>
    <div class="row g-2">
        <div class="col-12">
            <div class="form-text mb-0" id="orphan-channel-profile-help">{{ __('web/vtual-orphans.channels.profile_help_youtube') }}</div>
        </div>
    </div>
</x-adminlte4::form.ajax>

<div class="table-responsive" id="orphan-channels-table" data-status-labels='@json($statusLabels)'>
    <table class="table table-sm table-hover align-middle mb-0" aria-label="{{ __('web/vtual-orphans.channels.table_caption') }}">
        <caption class="visually-hidden">{{ __('web/vtual-orphans.channels.table_caption') }}</caption>
        <thead>
            <tr>
                <th>{{ __('web/vtual-orphans.channels.platform') }}</th>
                <th>{{ __('web/vtual-orphans.channels.profile_or_id') }}</th>
                <th>{{ __('web/vtual-orphans.channels.handle') }}</th>
                <th>{{ __('web/vtual-orphans.channels.status') }}</th>
            </tr>
        </thead>
        <tbody id="orphan-channels-tbody">
            @forelse ($channels as $channel)
                @include(config('web.layout').'.vtual.orphans._channel_row', ['channel' => $channel])
            @empty
                <tr id="orphan-channels-empty" data-empty-row>
                    <td colspan="4" class="text-muted">{{ __('web/vtual-orphans.channels.empty') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@push('scripts')
<script>
(function () {
    'use strict';

    const tableWrapper = document.getElementById('orphan-channels-table');
    const tbody = document.getElementById('orphan-channels-tbody');
    const channelForm = document.querySelector('[data-orphan-channels-form]');
    if (!tableWrapper || !tbody) {
        return;
    }

    function applyPlatformHints() {
        if (!channelForm) {
            return;
        }

        const platformSelect = channelForm.querySelector('[name="platform_key"]');
        const profileInput = channelForm.querySelector('#profile_input');
        const helpEl = document.getElementById('orphan-channel-profile-help');
        if (!platformSelect || !profileInput || !helpEl) {
            return;
        }

        const key = platformSelect.value;
        if (key === 'twitch') {
            profileInput.placeholder = channelForm.dataset.placeholderTwitch || '';
            helpEl.textContent = channelForm.dataset.helpTwitch || '';
        } else {
            profileInput.placeholder = channelForm.dataset.placeholderYoutube || '';
            helpEl.textContent = channelForm.dataset.helpYoutube || '';
        }
    }

    if (channelForm) {
        const platformSelect = channelForm.querySelector('[name="platform_key"]');
        if (platformSelect) {
            platformSelect.addEventListener('change', applyPlatformHints);
            applyPlatformHints();
        }
    }

    let statusLabels = {};
    try {
        statusLabels = JSON.parse(tableWrapper.dataset.statusLabels || '{}');
    } catch (e) {
        statusLabels = {};
    }

    function extractChannel(payload) {
        if (!payload || typeof payload !== 'object') {
            return null;
        }

        if (payload.id && (payload.platform || payload.platform_id)) {
            return payload;
        }

        if (payload.data && payload.data.id) {
            return payload.data;
        }

        return null;
    }

    function badgeClass(status) {
        if (status === 'active') {
            return 'success';
        }
        if (status === 'pending') {
            return 'warning';
        }

        return 'secondary';
    }

    function profileCell(channel) {
        const code = document.createElement('code');
        code.className = 'user-select-all';
        code.textContent = channel.profile_input || channel.external_id || '';

        return code;
    }

    function appendChannelRow(channel) {
        if (!channel || typeof channel !== 'object') {
            return;
        }

        if (channel.id && tbody.querySelector('[data-channel-id="' + CSS.escape(channel.id) + '"]')) {
            return;
        }

        const emptyRow = document.getElementById('orphan-channels-empty');
        if (emptyRow) {
            emptyRow.remove();
        }

        const status = channel.status || 'pending';
        const platformName = (channel.platform && (channel.platform.name || channel.platform.key)) || '';

        const row = document.createElement('tr');
        if (channel.id) {
            row.dataset.channelId = channel.id;
        }

        const platformTd = document.createElement('td');
        platformTd.textContent = platformName;
        row.appendChild(platformTd);

        const profileTd = document.createElement('td');
        profileTd.appendChild(profileCell(channel));
        row.appendChild(profileTd);

        const handleTd = document.createElement('td');
        handleTd.textContent = channel.handle || '—';
        row.appendChild(handleTd);

        const statusTd = document.createElement('td');
        const badge = document.createElement('span');
        badge.className = 'badge text-bg-' + badgeClass(status);
        badge.textContent = statusLabels[status] || status;
        statusTd.appendChild(badge);

        if (channel.resolve_error) {
            const error = document.createElement('div');
            error.className = 'form-text text-danger';
            error.textContent = channel.resolve_error;
            statusTd.appendChild(error);
        }

        row.appendChild(statusTd);
        tbody.appendChild(row);
    }

    document.addEventListener('ajax-form:success', function (event) {
        const form = event.detail?.form;
        if (!form || !form.hasAttribute('data-orphan-channels-form')) {
            return;
        }

        const channel = extractChannel(event.detail?.data);
        if (!channel) {
            return;
        }

        appendChannelRow(channel);

        form.reset();

        const platformSelect = form.querySelector('[name="platform_key"]');
        if (platformSelect && platformSelect.options.length > 0) {
            platformSelect.selectedIndex = 0;
        }

        applyPlatformHints();
    });
})();
</script>
@endpush
