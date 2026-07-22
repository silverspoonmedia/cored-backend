<x-adminlte4::layouts.app :title="__('web/vtual.claims.staff_registry')">
    <x-adminlte4::card :title="__('web/vtual.claims.staff_registry')">
        <p class="text-muted small mb-3">{{ __('web/vtual.claims.staff_subtitle') }}</p>
        @include('adminlte4.vtual.partials.nav')

        <div class="border rounded p-3 mb-4">
            <h6 class="mb-2">{{ __('web/vtual.claims.orphan_create') }}</h6>
            <p class="text-muted small">{{ __('web/vtual.claims.orphan_help') }}</p>
            <form method="POST" action="{{ route('vtual.claims.orphan') }}" class="row g-2 align-items-end">
                @csrf
                <div class="col-md-5">
                    <label class="form-label">{{ __('web/vtual.display_name') }}</label>
                    <input type="text" name="display_name" class="form-control form-control-sm" required maxlength="120">
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('web/vtual.slug') }}</label>
                    <input type="text" name="slug" class="form-control form-control-sm" maxlength="120">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-warning btn-sm w-100">{{ __('web/vtual.create') }}</button>
                </div>
            </form>
        </div>

        <h5 class="mb-2">{{ __('web/vtual.claims.pending_queue') }}</h5>
        <p class="text-muted small mb-2">{{ __('web/vtual.claims.expires_help') }}</p>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('web/vtual.claims.claimant') }}</th>
                        <th>{{ __('web/vtual.claims.profile') }}</th>
                        <th>{{ __('web/vtual.claims.channel') }}</th>
                        <th>{{ __('web/vtual.claims.attempts') }}</th>
                        <th>{{ __('web/vtual.claims.expires') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pending as $claim)
                        <tr>
                            <td>
                                {{ $claim['user']['name'] ?? $claim['user']['email'] ?? $claim['user_id'] ?? '—' }}
                                @if (!empty($claim['user']['email']))
                                    <div class="small text-muted">{{ $claim['user']['email'] }}</div>
                                @endif
                            </td>
                            <td>{{ $claim['creator']['display_name'] ?? $claim['creator_id'] ?? '' }}</td>
                            <td class="small">
                                <span class="badge text-bg-light border">
                                    {{ $claim['channel']['platform']['name'] ?? $claim['channel']['platform']['key'] ?? '—' }}
                                </span>
                                <div class="mt-1">
                                    {{ $claim['channel']['handle'] ?? $claim['channel']['display_name'] ?? $claim['channel']['external_id'] ?? '' }}
                                </div>
                            </td>
                            <td>{{ $claim['attempt_count'] ?? 0 }}</td>
                            <td class="small">{{ $claim['expires_at'] ?? '—' }}</td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('vtual.claims.show', $claim['id']) }}" class="btn btn-outline-primary btn-sm">
                                    {{ __('web/vtual.view') }}
                                </a>
                                @if ($acceptAnywayEnabled)
                                    <form method="POST" action="{{ route('vtual.claims.accept-anyway', $claim['id']) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-warning btn-sm">{{ __('web/vtual.claims.accept_anyway') }}</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <x-adminlte4::empty-state icon="bi-inbox" :title="__('web/vtual.claims.empty_pending')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-adminlte4::card>
</x-adminlte4::layouts.app>
