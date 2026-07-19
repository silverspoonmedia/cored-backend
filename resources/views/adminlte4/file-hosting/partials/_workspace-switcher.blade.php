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
    $authId = auth()->id();
    $isTeamContext = $workspaceId !== null && $workspaceId !== '';
    $plansUrl = Route::has('services.index') ? route('services.index') : url('/services');

    $roleLabel = static function (?string $role): string {
        return match ($role) {
            'owner' => __('web/file-hosting.collab.role_owner_short'),
            'admin' => __('web/file-hosting.collab.role_admin_short'),
            'editor' => __('web/file-hosting.collab.role_editor_short'),
            'viewer' => __('web/file-hosting.collab.role_viewer_short'),
            default => $role ?? '',
        };
    };

    $switcherLabel = ! $isTeamContext
        ? __('web/file-hosting.workspace_personal')
        : ($currentWorkspace['name'] ?? __('web/file-hosting.workspace_team'));
@endphp

<div class="dropdown fh-workspace-switcher">
    <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button"
            data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
        <i class="bi {{ $isTeamContext ? 'bi-people' : 'bi-person' }} me-1"></i>
        <span class="fh-workspace-switcher__label">{{ $switcherLabel }}</span>
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
           class="dropdown-item {{ ! $isTeamContext ? 'active' : '' }}">
            <i class="bi bi-person me-2"></i>{{ __('web/file-hosting.workspace_personal') }}
        </a>

        @if ($workspaces->isNotEmpty())
            <div class="dropdown-divider"></div>
            @foreach ($workspaces as $ws)
                @php
                    $wsId = $ws['id'] ?? null;
                    $wsRole = $ws['my_role'] ?? null;
                    $wsIsOwner = ($ws['is_owner'] ?? false) === true
                        || (($ws['owner_user_id'] ?? null) === $authId);
                    $wsActive = $workspaceId === $wsId;
                @endphp
                @if ($wsId)
                    <a href="{{ route('file-hosting.index', array_filter(['user_id' => $ownerUserId, 'workspace_id' => $wsId])) }}"
                       class="dropdown-item d-flex align-items-center justify-content-between gap-2 {{ $wsActive ? 'active' : '' }}">
                        <span class="text-truncate">
                            <i class="bi bi-people me-2"></i>{{ $ws['name'] ?? 'Workspace' }}
                        </span>
                        <span class="d-flex gap-1 flex-shrink-0">
                            @if ($wsRole)
                                <span class="badge {{ $wsActive ? 'text-bg-light' : 'text-bg-secondary' }}">{{ $roleLabel($wsRole) }}</span>
                            @endif
                            @if ($wsIsOwner)
                                <span class="badge text-bg-primary">{{ __('web/file-hosting.collab.badge_yours') }}</span>
                            @else
                                <span class="badge text-bg-info">{{ __('web/file-hosting.collab.badge_joined') }}</span>
                            @endif
                        </span>
                    </a>
                @endif
            @endforeach
        @endif

        <div class="dropdown-divider"></div>

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
