<?php

namespace App\Domain\ErrorLog\Repositories\Contracts;

use App\Domain\ErrorLog\Models\ErrorLog;
use Illuminate\Support\Collection;

interface ErrorLogRepositoryInterface
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
    public function upsertOccurrence(string $fingerprint, array $attributes): ErrorLog;

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, ErrorLog>
     */
    public function list(array $filters, int $limit = 50): Collection;

    public function findByIdOrFail(string $id): ErrorLog;

    public function updateStatus(ErrorLog $errorLog, string $status, ?string $resolvedBy = null): ErrorLog;

    public function deleteOlderThan(int $days): int;
}
