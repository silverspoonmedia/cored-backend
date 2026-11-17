<?php

namespace App\Domain\ErrorLog\Services;

use App\Domain\ErrorLog\Models\ErrorLog;
use App\Domain\ErrorLog\Repositories\Contracts\ErrorLogRepositoryInterface;
use Illuminate\Support\Collection;

class ErrorLogService
{
    public function __construct(
        private readonly ErrorLogRepositoryInterface $errorLogs,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, ErrorLog>
     */
    public function list(array $filters, int $limit = 50): Collection
    {
        return $this->errorLogs->list($filters, $limit);
    }

    public function get(string $id): ErrorLog
    {
        return $this->errorLogs->findByIdOrFail($id);
    }

    public function updateStatus(ErrorLog $errorLog, string $status, ?string $resolvedBy = null): ErrorLog
    {
        return $this->errorLogs->updateStatus($errorLog, $status, $resolvedBy);
    }

    public function clean(int $days): int
    {
        return $this->errorLogs->deleteOlderThan($days);
    }
}
