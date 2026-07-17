<?php

namespace App\Domain\ErrorLog\Repositories\Eloquent;

use App\Domain\ErrorLog\Models\ErrorLog;
use App\Domain\ErrorLog\Repositories\Contracts\ErrorLogRepositoryInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ErrorLogRepository implements ErrorLogRepositoryInterface
{
    /**
     * @param  array{
     *     type: string,
     *     level: string,
     *     exception_class: ?string,
     *     message: string,
     *     file: ?string,
     *     line: ?int,
     *     trace: ?string,
     *     context: array<string, mixed>|null
     * }  $attributes
     */
    public function upsertOccurrence(string $fingerprint, array $attributes): ErrorLog
    {
        $now = Carbon::now();

        try {
            $existing = ErrorLog::query()->where('fingerprint', $fingerprint)->first();

            if ($existing === null) {
                return ErrorLog::query()->create([
                    'fingerprint' => $fingerprint,
                    'type' => $attributes['type'],
                    'level' => $attributes['level'],
                    'exception_class' => $attributes['exception_class'],
                    'message' => $attributes['message'],
                    'file' => $attributes['file'],
                    'line' => $attributes['line'],
                    'trace' => $attributes['trace'],
                    'context' => $attributes['context'],
                    'occurrence_count' => 1,
                    'first_seen_at' => $now,
                    'last_seen_at' => $now,
                    'status' => ErrorLog::STATUS_OPEN,
                ]);
            }

            $existing->fill([
                'type' => $attributes['type'],
                'level' => $attributes['level'],
                'exception_class' => $attributes['exception_class'],
                'message' => $attributes['message'],
                'file' => $attributes['file'],
                'line' => $attributes['line'],
                'trace' => $attributes['trace'],
                'context' => $attributes['context'],
                'last_seen_at' => $now,
                'occurrence_count' => $existing->occurrence_count + 1,
            ]);

            // Re-open ignored/resolved groups when the error fires again.
            if ($existing->status !== ErrorLog::STATUS_OPEN) {
                $existing->status = ErrorLog::STATUS_OPEN;
                $existing->resolved_at = null;
                $existing->resolved_by = null;
            }

            $existing->save();

            return $existing->refresh();
        } catch (QueryException $e) {
            // Race on unique fingerprint: retry as update.
            $existing = ErrorLog::query()->where('fingerprint', $fingerprint)->first();

            if ($existing === null) {
                throw $e;
            }

            $existing->fill([
                'type' => $attributes['type'],
                'level' => $attributes['level'],
                'exception_class' => $attributes['exception_class'],
                'message' => $attributes['message'],
                'file' => $attributes['file'],
                'line' => $attributes['line'],
                'trace' => $attributes['trace'],
                'context' => $attributes['context'],
                'last_seen_at' => $now,
                'occurrence_count' => $existing->occurrence_count + 1,
            ]);

            if ($existing->status !== ErrorLog::STATUS_OPEN) {
                $existing->status = ErrorLog::STATUS_OPEN;
                $existing->resolved_at = null;
                $existing->resolved_by = null;
            }

            $existing->save();

            return $existing->refresh();
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, ErrorLog>
     */
    public function list(array $filters, int $limit = 50): Collection
    {
        $query = ErrorLog::query()
            ->with(['resolver:id,name,email'])
            ->latest('last_seen_at');

        if (! empty($filters['status']) && is_string($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['level']) && is_string($filters['level'])) {
            $query->where('level', $filters['level']);
        }

        if (! empty($filters['type']) && is_string($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['q']) && is_string($filters['q'])) {
            $term = '%'.$filters['q'].'%';
            $query->where(function ($q) use ($term): void {
                $q->where('message', 'like', $term)
                    ->orWhere('exception_class', 'like', $term)
                    ->orWhere('file', 'like', $term);
            });
        }

        if (! empty($filters['from']) && is_string($filters['from'])) {
            $query->where('last_seen_at', '>=', Carbon::parse($filters['from'])->startOfDay());
        }

        if (! empty($filters['to']) && is_string($filters['to'])) {
            $query->where('last_seen_at', '<=', Carbon::parse($filters['to'])->endOfDay());
        }

        return $query->limit($limit)->get();
    }

    public function findByIdOrFail(string $id): ErrorLog
    {
        return ErrorLog::query()
            ->with(['resolver:id,name,email'])
            ->findOrFail($id);
    }

    public function updateStatus(ErrorLog $errorLog, string $status, ?string $resolvedBy = null): ErrorLog
    {
        $errorLog->status = $status;

        if ($status === ErrorLog::STATUS_OPEN) {
            $errorLog->resolved_at = null;
            $errorLog->resolved_by = null;
        } else {
            $errorLog->resolved_at = Carbon::now();
            $errorLog->resolved_by = $resolvedBy;
        }

        $errorLog->save();

        return $errorLog->refresh()->load(['resolver:id,name,email']);
    }

    public function deleteOlderThan(int $days): int
    {
        $cutoff = Carbon::now()->subDays(max(1, $days));

        return ErrorLog::query()
            ->where('last_seen_at', '<', $cutoff)
            ->delete();
    }
}
