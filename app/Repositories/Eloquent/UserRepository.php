<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class UserRepository implements UserRepositoryInterface
{
    public function findById(string $id): ?User
    {
        return User::query()->find($id);
    }

    public function findByIdOrFail(string $id): User
    {
        return User::query()->findOrFail($id);
    }

    public function findByEmail(string $email): ?User
    {
        return User::query()->where('email', $email)->first();
    }

    public function create(array $data): User
    {
        return User::query()->create($data);
    }

    public function update(string $id, array $data): User
    {
        $user = $this->findByIdOrFail($id);
        $user->update($data);

        return $user->fresh() ?? $user;
    }

    public function markEmailAsVerified(string $id): User
    {
        return $this->update($id, [
            'email_verified_at' => now(),
        ]);
    }

    public function listVisibleToActorLevel(int $actorLevel): Collection
    {
        $userModel = new User;
        $morphKey = config('permission.column_names.model_morph_key', 'model_id');
        $rolesTable = config('permission.table_names.roles', 'roles');
        $pivotTable = config('permission.table_names.model_has_roles', 'model_has_roles');

        return User::query()
            ->with('roles')
            ->whereIn('id', function ($query) use ($actorLevel, $userModel, $morphKey, $rolesTable, $pivotTable): void {
                $query->select($pivotTable.'.'.$morphKey)
                    ->from($pivotTable)
                    ->join($rolesTable, "{$rolesTable}.id", '=', "{$pivotTable}.role_id")
                    ->where("{$pivotTable}.model_type", $userModel->getMorphClass())
                    ->groupBy("{$pivotTable}.{$morphKey}")
                    ->havingRaw('MIN('.$rolesTable.'.level) > ?', [$actorLevel]);
            })
            ->orderBy('name')
            ->get();
    }

    public function findByIdOrFailWithRoles(string $id): User
    {
        return User::query()->with('roles')->findOrFail($id);
    }

    public function softDelete(string $id): void
    {
        $user = $this->findByIdOrFail($id);
        $user->delete();
    }
}
