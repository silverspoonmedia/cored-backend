@php
    /**
     * Compact workspace switcher for the file manager toolbar.
     *
     * @var string $planState free|paid|grace
     * @var bool $canCreateWorkspace
     * @var string|null $workspaceId
     * @var array|null $currentWorkspace
     * @var \Illuminate\Support\Collection|array $workspaces
     * @var string|null $ownerUserId
     * @var int $ownedWorkspaceUsed
     * @var int $ownedWorkspaceLimit
     */
    $planState = $planState ?? 'free';
    $canCreateWorkspace = (bool) ($canCreateWorkspace ?? false);
    $workspaceId = $workspaceId ?? null;
    $currentWorkspace = $currentWorkspace ?? null;
    $workspaces = collect($workspaces ?? []);
    $ownerUserId = $ownerUserId ?? null;
    $ownedWorkspaceUsed = (int) ($ownedWorkspaceUsed ?? 0);
    $ownedWorkspaceLimit = (int) ($ownedWorkspaceLimit ?? 0);
    $isTeamContext = $workspaceId !== null && $workspaceId !== '';
    $plansUrl = Route::has('services.index') ? route('services.index') : url('/services');

    $switcherLabel = ! $isTeamContext
        ? __('web/file-hosting.workspace_personal')
        : ($currentWorkspace['name'] ?? __('web/file-hosting.workspace_team'));
@endphp

<div class="dropdown fh-workspace-switcher">
    <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button"
            data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
        <i class="bi {{ $isTeamContext ? 'bi-people' : 'bi-person' }} me-1" data-fh-workspace-icon></i>
        <span class="fh-workspace-switcher__label" data-fh-workspace-label>{{ $switcherLabel }}</span>
        @if ($planState === 'paid' && $ownedWorkspaceLimit > 0)
            <span class="badge text-bg-light border ms-1">{{ $ownedWorkspaceUsed }}/{{ $ownedWorkspaceLimit }}</span>
        @elseif ($planState === 'grace')
            <span class="badge text-bg-warning ms-1">{{ __('web/file-hosting.collab.badge_grace') }}</span>
        @elseif ($planState === 'free')
            <span class="badge text-bg-secondary ms-1">{{ __('web/file-hosting.collab.badge_free') }}</span>
        @endif
    </button>
    <div class="dropdown-menu dropdown-menu-end fh-workspace-switcher__menu shadow">
        <h6 class="dropdown-header">{{ __('web/file-hosting.collab.switch_label') }}</h6>

        <a href="{{ route('file-hosting.index', array_filter(['user_id' => $ownerUserId])) }}"
           class="dropdown-item {{ ! $isTeamContext ? 'active' : '' }}" data-fh-workspace-id="">
            <i class="bi bi-person me-2"></i>{{ __('web/file-hosting.workspace_personal') }}
        </a>

        {{-- Dynamic workspace items are rendered client-side by
             file-manager.js renderWorkspaceMenuItems() (grouped: microstock
             first, team second, each preceded by its own divider). Blade only
             provides the skeleton below. --}}

        <div data-fh-microstock-slot
             data-slots-url="{{ route('file-hosting.microstock.slots') }}"
             data-create-url="{{ route('file-hosting.microstock.workspaces.store') }}"
             data-services-url="{{ route('services.index') }}"
             data-label-activate="{{ __('web/file-hosting.microstock.activate_cta') }}"
             data-label-create="{{ __('web/file-hosting.microstock.create_workspace') }}"
             data-label-full="{{ __('web/file-hosting.microstock.slots_full') }}"></div>

        @if ($planState === 'paid')
            <div class="px-3 py-1 small text-muted">
                {{ __('web/file-hosting.collab.slots_counter', [
                    'used' => $ownedWorkspaceUsed,
                    'limit' => $ownedWorkspaceLimit,
                ]) }}
            </div>
            @if ($canCreateWorkspace)
                <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#fhCreateWorkspaceModal">
                    <i class="bi bi-plus-lg me-2"></i>{{ __('web/file-hosting.collab.create_workspace') }}
                </button>
            @else
                <span class="dropdown-item disabled text-muted small">
                    <i class="bi bi-lock me-2"></i>{{ __('web/file-hosting.collab.slots_full') }}
                </span>
                <a href="{{ $plansUrl }}" class="dropdown-item small">
                    <i class="bi bi-stars me-2"></i>{{ __('web/file-hosting.collab.buy_slot_cta') }}
                </a>
            @endif
        @elseif ($planState === 'grace')
            <div class="px-3 py-1 small text-warning">{{ __('web/file-hosting.collab.grace_body_short') }}</div>
            <a href="{{ $plansUrl }}" class="dropdown-item">
                <i class="bi bi-arrow-repeat me-2"></i>{{ __('web/file-hosting.collab.renew_cta') }}
            </a>
        @else
            <div class="px-3 py-1 small text-muted">{{ __('web/file-hosting.collab.free_cannot_create') }}</div>
            <a href="{{ $plansUrl }}" class="dropdown-item">
                <i class="bi bi-stars me-2"></i>{{ __('web/file-hosting.collab.upgrade_cta') }}
            </a>
        @endif

        <button type="button" class="dropdown-item" data-bs-toggle="collapse" data-bs-target="#fhAcceptInviteInline" aria-expanded="false">
            <i class="bi bi-envelope-check me-2"></i>{{ __('web/file-hosting.collab.accept_invite') }}
        </button>
        <div class="collapse px-3 pb-2" id="fhAcceptInviteInline">
            <form method="post" action="{{ route('file-hosting.workspaces.invites.accept') }}" class="mt-1">
                @csrf
                <input type="text" name="token" class="form-control form-control-sm mb-1" required
                       placeholder="{{ __('web/file-hosting.collab.invite_token_placeholder') }}"
                       maxlength="64">
                <button type="submit" class="btn btn-sm btn-primary w-100">{{ __('web/file-hosting.collab.accept') }}</button>
            </form>
        </div>
    </div>
</div>

@if ($isTeamContext && is_array($currentWorkspace))
    <button type="button" class="btn btn-outline-secondary btn-sm"
            data-bs-toggle="offcanvas" data-bs-target="#fhTeamOffcanvas" aria-controls="fhTeamOffcanvas">
        <i class="bi bi-gear me-1"></i>{{ __('web/file-hosting.collab.team_settings') }}
    </button>
@endif
