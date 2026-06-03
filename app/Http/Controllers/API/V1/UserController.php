<?php

namespace App\Http\Controllers\API\V1;

use App\Exceptions\User\CannotDeleteSelfException;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\User;
use App\Services\Rbac\RoleService;
use App\Services\User\UserManagementService;
use App\Support\User\UserManagementPresenter;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly UserManagementService $userManagement,
        private readonly RoleService $roleService,
    ) {}

    public function index(): JsonResponse
    {
        $actor = $this->actor();
        $this->authorize('viewAny', User::class);

        $users = $this->userManagement->list($actor);

        return $this->successResponse(
            UserManagementPresenter::presentCollection($users),
            'Daftar pengguna berhasil diambil.',
        );
    }

    public function show(User $user): JsonResponse
    {
        $this->authorize('view', $user);

        $user->load('roles');

        return $this->successResponse(
            UserManagementPresenter::present($user),
            'Detail pengguna berhasil diambil.',
        );
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $actor = $this->actor();
        $this->authorize('create', User::class);

        $user = $this->userManagement->create($actor, $request->validated());

        return $this->successResponse(
            UserManagementPresenter::present($user),
            'Pengguna berhasil dibuat.',
            201,
        );
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $updated = $this->userManagement->update($this->actor(), $user->id, $request->validated());

        return $this->successResponse(
            UserManagementPresenter::present($updated),
            'Pengguna berhasil diperbarui.',
        );
    }

    public function destroy(User $user): JsonResponse
    {
        if ($this->actor()->id === $user->id) {
            throw new CannotDeleteSelfException;
        }

        $this->authorize('delete', $user);

        $this->userManagement->delete($this->actor(), $user->id);

        return $this->successResponse(null, 'Pengguna berhasil dihapus.');
    }

    public function assignableRoles(): JsonResponse
    {
        $actor = $this->actor();
        $this->authorize('assignableRoles', User::class);

        $roles = $this->roleService->getAssignableRoles($actor);

        return $this->successResponse(
            UserManagementPresenter::presentAssignableRoles($roles),
            'Daftar role yang dapat ditetapkan berhasil diambil.',
        );
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = Auth::guard('api')->user();

        return $user;
    }
}
