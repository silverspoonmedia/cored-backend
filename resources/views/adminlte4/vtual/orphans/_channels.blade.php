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

@include(config('web.layout').'.vtual.partials._channel-add-form', [
    'action' => route('vtual.orphans.channels.store', $creatorId),
    'transPrefix' => 'web/vtual-orphans.channels',
    'formKind' => 'orphans',
    'helpElId' => 'orphan-channel-profile-help',
    'platforms' => $platforms,
    'showAlert' => false,
])

<div class="table-responsive" id="orphan-channels-table" data-status-labels='@json($statusLabels)'>
    <table class="table table-sm table-hover align-middle mb-0" aria-label="{{ __('web/vtual-orphans.channels.table_caption') }}">
        <caption class="visually-hidden">{{ __('web/vtual-orphans.channels.table_caption') }}</caption>
        <thead>
            <tr>
                <th>{{ __('web/vtual-orphans.channels.avatar') }}</th>
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
                    <td colspan="5" class="text-muted">{{ __('web/vtual-orphans.channels.empty') }}</td>
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
    if (!tableWrapper || !tbody) {
        return;
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

    function avatarCell(channel) {
        const td = document.createElement('td');
        td.className = 'text-nowrap';

        if (channel.avatar_url) {
            const img = document.createElement('img');
            img.src = channel.avatar_url;
            img.alt = channel.display_name || channel.handle || '';
            img.className = 'rounded-circle object-fit-cover';
            img.width = 36;
            img.height = 36;
            img.loading = 'lazy';
            img.referrerPolicy = 'no-referrer';
            td.appendChild(img);
        } else {
            const placeholder = document.createElement('span');
            placeholder.className = 'text-muted small';
            placeholder.textContent = '—';
            td.appendChild(placeholder);
        }

        return td;
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

        row.appendChild(avatarCell(channel));

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
        if (!form || form.dataset.formKind !== 'orphans') {
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
    });
})();
</script>
@endpush
