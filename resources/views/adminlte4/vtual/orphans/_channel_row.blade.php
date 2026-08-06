@php
    $status = $channel['status'] ?? 'pending';
@endphp
<tr @if (!empty($channel['id'])) data-channel-id="{{ $channel['id'] }}" @endif>
    <td class="text-nowrap">
        @if (!empty($channel['avatar_url']))
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
    <td>
        <span class="badge text-bg-{{ $status === 'active' ? 'success' : ($status === 'pending' ? 'warning' : 'secondary') }}">
            {{ __('web/vtual-orphans.channels.status_'.$status) }}
        </span>
        @if (!empty($channel['resolve_error']))
            <div class="form-text text-danger">{{ $channel['resolve_error'] }}</div>
        @endif
    </td>
</tr>
