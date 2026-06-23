<?php

namespace App\Services\User;

use App\Events\ActivityNotificationEvent;
use App\Models\User;
use App\Notifications\Activity\NotificationType;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Rbac\RoleService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;

class UserManagementService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly RoleService $roleService,
    ) {}

    /**
     * @return Collection<int, User>
     */
    public function list(User $actor): Collection
    {
        $actorLevel = $this->roleService->getLowestRoleLevel($actor);

        if ($actorLevel === null) {
            return new Collection;
        }

        return $this->users->listVisibleToActorLevel($actorLevel);
    }

    public function get(string $id): User
    {
        return $this->users->findByIdOrFailWithRoles($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): User
    {
        $roleName = $data['role'];
        $this->assertRoleAssignable($actor, $roleName);

        $user = $this->users->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        $user->syncRoles([$roleName]);

        activity()
            ->useLog('user')
            ->causedBy($actor)
            ->performedOn($user)
            ->event('role_assigned')
            ->withProperties(['role' => $roleName])
            ->log('role_assigned');

        // Fan out a role-targeted notification (Root + Admin by default). Both the
        // API and the Blade UI funnel user creation through here, so one dispatch
        // covers every entry point.
        ActivityNotificationEvent::dispatch(
            NotificationType::UserCreated,
            [
                'name' => $user->name,
                'email' => $user->email,
                'actor' => $actor->name,
                'role' => $roleName,
            ],
            actionRoute: 'users.edit',
            actionParams: ['user' => $user->id],
        );

        return $user->load('roles');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, string $id, array $data): User
    {
        if (isset($data['role'])) {
            $this->assertRoleAssignable($actor, $data['role']);
        }

        $attributes = Arr::only($data, ['name', 'email']);

        if (isset($data['password']) && $data['password'] !== '') {
            $attributes['password'] = Hash::make($data['password']);
        }

        $user = $this->users->update($id, $attributes);

        if (isset($data['role'])) {
            $previousRole = $user->roles->first()?->name;
            $user->syncRoles([$data['role']]);

            if ($previousRole !== $data['role']) {
                activity()
                    ->useLog('user')
                    ->causedBy($actor)
                    ->performedOn($user)
                    ->event('role_assigned')
                    ->withProperties([
                        'previous_role' => $previousRole,
                        'role' => $data['role'],
                    ])
                    ->log('role_assigned');
            }
        }

        // Personal notification: tell the affected user their account changed —
        // but not when they edited themselves (they already know). Audience is
        // resolved from notification type settings (notify_subject_user).
        if ($actor->id !== $user->id) {
            ActivityNotificationEvent::dispatch(
                NotificationType::AccountUpdated,
                [
                    'name' => $user->name,
                    'actor' => $actor->name,
                    'subject_user_id' => $user->id,
                ],
            );
        }

        return $user->load('roles');
    }

    public function delete(User $actor, string $id): void
    {
        $this->users->softDelete($id);
    }

    private function assertRoleAssignable(User $actor, string $roleName): void
    {
        if (! $this->roleService->isRoleAssignableBy($actor, $roleName)) {
            abort(403, __('forms/users.role_not_assignable'));
        }
    }
}
