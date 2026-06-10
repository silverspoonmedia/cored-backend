<?php

namespace App\Http\Controllers\Web;

use App\Exceptions\User\CannotDeleteSelfException;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\HandlesDomainErrors;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Services\Rbac\RoleService;
use App\Services\User\UserManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Web user management. A thin mirror of the API {@see \App\Http\Controllers\API\V1\UserController}:
 * identical authorization ({@see UserPolicy}), the same
 * {@see UserManagementService}/{@see RoleService}, and the same H-RBAC form
 * requests — only the response shape differs (Blade + redirects vs JSON).
 */
class UserController extends Controller
{
    use HandlesDomainErrors;

    public function __construct(
        private readonly UserManagementService $userManagement,
        private readonly RoleService $roleService,
    ) {}

    public function index(): View
    {
        $actor = $this->actor();
        $this->authorize('viewAny', User::class);

        $users = $this->userManagement->list($actor)->load('roles');

        return view('users.index', [
            'users' => $users,
            'actorId' => $actor->id,
        ]);
    }

    public function create(): View
    {
        $actor = $this->actor();
        $this->authorize('create', User::class);

        return view('users.create', [
            'assignableRoles' => $this->roleService->getAssignableRoles($actor),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $actor = $this->actor();
        $this->authorize('create', User::class);

        return $this->handleDomain(function () use ($actor, $request): RedirectResponse {
            $this->userManagement->create($actor, $request->validated());

            return redirect()
                ->route('users.index')
                ->with('success', 'Pengguna berhasil dibuat.');
        });
    }

    public function edit(User $user): View
    {
        $actor = $this->actor();
        $this->authorize('update', $user);

        return view('users.edit', [
            'user' => $user->load('roles'),
            'currentRole' => $user->roles->first()?->name,
            'assignableRoles' => $this->roleService->getAssignableRoles($actor),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        return $this->handleDomain(function () use ($request, $user): RedirectResponse {
            $this->userManagement->update($this->actor(), $user->id, $request->validated());

            return redirect()
                ->route('users.index')
                ->with('success', 'Pengguna berhasil diperbarui.');
        });
    }

    public function destroy(User $user): RedirectResponse
    {
        return $this->handleDomain(function () use ($user): RedirectResponse {
            if ($this->actor()->id === $user->id) {
                throw new CannotDeleteSelfException;
            }

            $this->authorize('delete', $user);

            $this->userManagement->delete($this->actor(), $user->id);

            return redirect()
                ->route('users.index')
                ->with('success', 'Pengguna berhasil dihapus.');
        });
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = Auth::guard('api')->user();

        return $user;
    }
}
