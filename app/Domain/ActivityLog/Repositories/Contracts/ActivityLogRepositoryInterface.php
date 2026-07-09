<?php

namespace App\Domain\ActivityLog\Repositories\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

interface ActivityLogRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Activity>
     */
    public function list(array $filters, int $limit = 50): Collection;

    public function findByIdOrFail(int $id): Activity;

    /**
     * @return Collection<int, Activity>
     */
    public function listForSubject(Model $subject, int $limit = 50): Collection;
}
