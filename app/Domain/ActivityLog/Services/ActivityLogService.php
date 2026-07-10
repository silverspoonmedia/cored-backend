<?php

namespace App\Domain\ActivityLog\Services;

use App\Domain\ActivityLog\Repositories\Contracts\ActivityLogRepositoryInterface;
use App\Domain\ActivityLog\Support\SubjectTypeResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

class ActivityLogService
{
    public function __construct(
        private readonly ActivityLogRepositoryInterface $activityLogs,
        private readonly SubjectTypeResolver $subjectTypes,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Activity>
     */
    public function list(array $filters, int $limit = 50): Collection
    {
        $normalized = $filters;

        if (! empty($filters['subject']) && is_string($filters['subject'])) {
            $morphClass = $this->subjectTypes->resolveMorphClass($filters['subject']);
            if ($morphClass !== null && ! empty($filters['subject_id']) && is_string($filters['subject_id'])) {
                $normalized['subject_type'] = $morphClass;
                $normalized['subject_id'] = $filters['subject_id'];
            }
        }

        return $this->activityLogs->list($normalized, $limit);
    }

    public function get(int $id): Activity
    {
        return $this->activityLogs->findByIdOrFail($id);
    }

    /**
     * @return Collection<int, Activity>
     */
    public function listForSubject(Model $subject, int $limit = 50): Collection
    {
        return $this->activityLogs->listForSubject($subject, $limit);
    }
}
