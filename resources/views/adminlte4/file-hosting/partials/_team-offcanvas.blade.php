@php
    /**
     * Team settings offcanvas (members, invite, activity, archive).
     * Only rendered when browsing a team workspace.
     *
     * @var string|null $workspaceId
     * @var array|null $currentWorkspace
     * @var \Illuminate\Support\Collection|array $workspaceMembers
     * @var \Illuminate\Support\Collection|array $workspaceActivity
     */
    $workspaceId = $workspaceId ?? null;
    $currentWorkspace = $currentWorkspace ?? null;
    $workspaceMembers = collect($workspaceMembers ?? []);
    $workspaceActivity = collect($workspaceActivity ?? []);
    $authId = auth()->id();

    if ($workspaceId === null || $workspaceId === '' || ! is_array($currentWorkspace)) {
        return;
    }

    $isOwnerOfCurrent = ($currentWorkspace['is_owner'] ?? false) === true
        || ($currentWorkspace['owner_user_id'] ?? null) === $authId;
    $myRoleCurrent = $currentWorkspace['my_role'] ?? ($isOwnerOfCurrent ? 'owner' : null);
    $canManageMembers = in_array($myRoleCurrent, ['owner', 'admin'], true);

    $roleLabel = static function (?string $role): string {
        return match ($role) {
            'owner' => __('web/file-hosting.collab.role_owner_short'),
            'admin' => __('web/file-hosting.collab.role_admin_short'),
            'editor' => __('web/file-hosting.collab.role_editor_short'),
            'viewer' => __('web/file-hosting.collab.role_viewer_short'),
            default => $role ?? '',
        };
    };
@endphp

<div class="offcanvas offcanvas-end fh-team-offcanvas" tabindex="-1" id="fhTeamOffcanvas"
     aria-labelledby="fhTeamOffcanvasLabel">
    <div class="offcanvas-header border-bottom">
        <div>
            <h5 class="offcanvas-title mb-0" id="fhTeamOffcanvasLabel">
                {{ __('web/file-hosting.collab.team_context', ['name' => $currentWorkspace['name'] ?? 'Team']) }}
            </h5>
            @if ($myRoleCurrent)
                <span class="small text-muted">
                    {{ __('web/file-hosting.collab.you_are_role', ['role' => $roleLabel($myRoleCurrent)]) }}
                    · {{ $isOwnerOfCurrent ? __('web/file-hosting.collab.badge_yours') : __('web/file-hosting.collab.badge_joined') }}
                </span>
            @endif
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column gap-3">
        <p class="small text-muted mb-0">{{ __('web/file-hosting.collab.team_quota_note') }}</p>

        @if (session('workspace_invite_token'))
            <div class="alert alert-info py-2 small mb-0">
                <strong>{{ __('web/file-hosting.collab.invite_token_ready') }}</strong>
                @if (session('workspace_invite_email'))
                    <span class="text-muted">({{ session('workspace_invite_email') }})</span>
                @endif
                <div class="input-group input-group-sm mt-1">
                    <input type="text" class="form-control font-monospace" readonly
                           value="{{ session('workspace_invite_token') }}"
                           id="fhInviteTokenOut"
                           onclick="this.select()">
                    <button type="button" class="btn btn-outline-secondary"
                            onclick="navigator.clipboard.writeText(document.getElementById('fhInviteTokenOut').value)">
                        {{ __('web/file-hosting.copy_link') }}
                    </button>
                </div>
                <p class="mb-0 mt-1 text-muted">{{ __('web/file-hosting.collab.invite_token_share') }}</p>
            </div>
        @endif

        <div>
            <div class="d-flex justify-content-between align-items-center mb-2">
                <strong class="small">{{ __('web/file-hosting.collab.col_member') }}</strong>
                @if ($isOwnerOfCurrent)
                    <form method="post" action="{{ route('file-hosting.workspaces.archive', $workspaceId) }}"
                          data-swal-confirm="{{ __('web/file-hosting.collab.confirm_archive') }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-danger">
                            {{ __('web/file-hosting.collab.archive') }}
                        </button>
                    </form>
                @endif
            </div>

            @if ($workspaceMembers->isNotEmpty())
                <div class="table-responsive">
                    <table class="table table-sm table-borderless mb-0 small">
                        <thead>
                        <tr class="text-muted">
                            <th>{{ __('web/file-hosting.collab.col_member') }}</th>
                            <th>{{ __('web/file-hosting.collab.col_role') }}</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody data-fh-members-body>
                        @foreach ($workspaceMembers as $m)
                            @php
                                $mUserId = $m['user_id'] ?? null;
                                $mRole = $m['role'] ?? '';
                                $mEmail = $m['user']['email'] ?? $mUserId;
                                $canRemove = $canManageMembers && $mRole !== 'owner' && $mUserId;
                            @endphp
                            <tr>
                                <td class="text-break">{{ $mEmail }}</td>
                                <td><span class="badge text-bg-light border">{{ $roleLabel($mRole) ?: $mRole }}</span></td>
                                <td class="text-end">
                                    @if ($canRemove)
                                        <form method="post"
                                              action="{{ route('file-hosting.workspaces.members.destroy', [$workspaceId, $mUserId]) }}"
                                              class="d-inline"
                                              data-swal-confirm="{{ __('web/file-hosting.collab.confirm_remove') }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-link btn-sm text-danger p-0">
                                                {{ __('web/file-hosting.collab.remove') }}
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="small text-muted mb-0">{{ __('web/file-hosting.collab.no_members') }}</p>
            @endif
        </div>

        @if ($canManageMembers)
            <div class="border-top pt-3">
                <strong class="small d-block mb-2">{{ __('web/file-hosting.collab.invite_email') }}</strong>
                <form method="post" action="{{ route('file-hosting.workspaces.invites.store', $workspaceId) }}" class="row g-2">
                    @csrf
                    <div class="col-12">
                        <input type="email" name="email" class="form-control form-control-sm" required
                               placeholder="colleague@example.com">
                    </div>
                    <div class="col-7">
                        <select name="role" class="form-select form-select-sm">
                            <option value="editor">{{ __('web/file-hosting.collab.role_editor') }}</option>
                            <option value="viewer">{{ __('web/file-hosting.collab.role_viewer') }}</option>
                            <option value="admin">{{ __('web/file-hosting.collab.role_admin') }}</option>
                        </select>
                    </div>
                    <div class="col-5">
                        <button type="submit" class="btn btn-sm btn-primary w-100">
                            <i class="bi bi-send me-1"></i>{{ __('web/file-hosting.collab.send_invite') }}
                        </button>
                    </div>
                </form>
                <p class="small text-muted mb-0 mt-1">{{ __('web/file-hosting.collab.invite_result_hint') }}</p>
            </div>
        @else
            <p class="small text-muted mb-0 border-top pt-3">{{ __('web/file-hosting.collab.member_readonly_hint') }}</p>
        @endif

        <div class="border-top pt-3 flex-grow-1 d-flex flex-column min-h-0">
            <strong class="small d-block mb-2">{{ __('web/file-hosting.workspace_activity') }}</strong>
            @if ($workspaceActivity->isNotEmpty())
                <ul class="list-group list-group-flush small fh-team-offcanvas__activity" data-fh-activity-list>
                    @foreach ($workspaceActivity->take(30) as $row)
                        <li class="list-group-item px-0 py-1">
                            <span class="text-muted">{{ $row['created_at_formatted'] ?? $row['created_at'] ?? '' }}</span>
                            —
                            <strong>{{ $row['causer']['email'] ?? $row['causer']['name'] ?? 'system' }}</strong>
                            {{ $row['event'] ?? $row['description'] ?? '' }}
                            @if (! empty($row['properties']['name']))
                                <em>{{ $row['properties']['name'] }}</em>
                            @elseif (! empty($row['properties']['to']))
                                <em>{{ $row['properties']['to'] }}</em>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="small text-muted mb-0">{{ __('web/file-hosting.collab.activity_empty') }}</p>
            @endif
        </div>
    </div>
</div>
