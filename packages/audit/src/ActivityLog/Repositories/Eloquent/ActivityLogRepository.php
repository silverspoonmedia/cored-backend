<?php

namespace App\Domain\ActivityLog\Repositories\Eloquent;

use App\Domain\ActivityLog\Repositories\Contracts\ActivityLogRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

class ActivityLogRepository implements ActivityLogRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Activity>
     */
    public function list(array $filters, int $limit = 50): Collection
    {
        $query = Activity::query()
            ->with(['causer', 'subject'])
            ->latest('created_at');

        if (! empty($filters['log_name']) && is_string($filters['log_name'])) {
            $query->inLog($filters['log_name']);
        }

        if (! empty($filters['subject_type']) && is_string($filters['subject_type'])
            && ! empty($filters['subject_id']) && is_string($filters['subject_id'])) {
            $query->where('subject_type', $filters['subject_type'])
                ->where('subject_id', $filters['subject_id']);
        }

        if (! empty($filters['event']) && is_string($filters['event'])) {
            $query->forEvent($filters['event']);
        }

        return $query->limit($limit)->get();
    }

    public function findByIdOrFail(int $id): Activity
    {
        return Activity::query()
            ->with(['causer', 'subject'])
            ->findOrFail($id);
    }

    /**
     * @return Collection<int, Activity>
     */
    public function listForSubject(Model $subject, int $limit = 50): Collection
    {
        return Activity::query()
            ->with(['causer', 'subject'])
            ->forSubject($subject)
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }
}
