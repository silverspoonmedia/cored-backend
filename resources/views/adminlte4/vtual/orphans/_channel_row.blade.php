@php
    $status = $channel['status'] ?? 'pending';
@endphp
<tr @if (!empty($channel['id'])) data-channel-id="{{ $channel['id'] }}" @endif>
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
