<?php

namespace App\Support\Product\Services;

use App\Domain\Archivd\Enums\WorkspaceMemberStatus;
use App\Domain\Archivd\Models\HostedFile;
use App\Domain\Archivd\Models\Workspace;
use App\Domain\Archivd\Models\WorkspaceMember;
use App\Domain\Archivd\Services\QuotaService;
use App\Support\Product\Models\ProductAnalytic;
use App\Support\Product\ProductRegistry;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Write-side analytics store. Increments/decrements per-(product, tier, metric)
 * aggregate rows on domain mutations and flushes the dashboard read cache.
 *
 * Not per-user: one row per bucket (e.g. Archivd + paid + storage_used_bytes).
 * Tier transitions move a user's contribution between buckets via moveUserTier —
 * source-of-truth is HostedFile/Workspace, so the delta is a single-user indexed
 * query, only on the rare tier-change event (purchase/grace/expiry).
 */
class ProductAnalyticsService
{
    /**
     * QuotaService resolved lazily to break the constructor cycle:
     * ProductAnalyticsService -> QuotaService -> ServiceEntitlementService -> ProductAnalyticsService.
     */
    private function quota(): QuotaService
    {
        return App::make(QuotaService::class);
    }

    /**
     * Resolve the current tier label for a user. Used on hot-path increments
     * (upload/delete) where we don't know the tier a priori.
     */
    public function tierFor(string $userId): string
    {
        return $this->quota()->storageStateFor($userId);
    }

    /**
     * Atomic increment of a bucket counter. Tier resolved from the user's
     * current entitlement state. Upserts the row if absent.
     */
    public function increment(string $productKey, string $userId, string $metricKey, int $delta): void
    {
        if ($delta === 0) {
            return;
        }

        $tier = $this->tierFor($userId);

        DB::transaction(function () use ($productKey, $tier, $metricKey, $delta): void {
            $this->upsertMetric($productKey, $tier, $metricKey, $delta);
        });

        $this->flushCache($productKey);
    }

    /**
     * Atomic decrement of a bucket counter, floored at 0. Tier resolved from
     * the user's current entitlement state.
     */
    public function decrement(string $productKey, string $userId, string $metricKey, int $delta): void
    {
        if ($delta === 0) {
            return;
        }

        $tier = $this->tierFor($userId);

        DB::transaction(function () use ($productKey, $tier, $metricKey, $delta): void {
            $this->upsertMetric($productKey, $tier, $metricKey, -$delta);
        });

        $this->flushCache($productKey);
    }

    /**
     * Move a user's entire contribution for a product from one tier bucket to
     * another. Used on tier transitions (free -> paid, paid -> grace, etc.).
     *
     * $oldTier must be captured BEFORE the entitlement mutation — the post-
     * mutation state is what determines $newTier. Caller is responsible for
     * passing the correct pair.
     *
     * If $oldTier === $newTier this is a no-op.
     */
    public function moveUserTier(string $productKey, string $userId, string $oldTier, string $newTier): void
    {
        if ($oldTier === $newTier) {
            return;
        }

        DB::transaction(function () use ($productKey, $userId, $oldTier, $newTier): void {
            $contributions = $this->userContributions($productKey, $userId);

            foreach ($contributions as $metricKey => $value) {
                if ($value <= 0) {
                    continue;
                }

                $this->upsertMetric($productKey, $oldTier, $metricKey, -$value);
                $this->upsertMetric($productKey, $newTier, $metricKey, $value);
            }
        });

        $this->flushCache($productKey);
    }

    /**
     * Flush the insight read cache for a product. Called on every write.
     */
    public function flushCache(string $productKey): void
    {
        Cache::forget("product_insights.{$productKey}.metrics");
    }

    /**
     * Recompute Archivd buckets for a single tier from source tables.
     * Idempotent: overwrites the tier's rows with fresh absolute values.
     */
    public function recomputeArchivdTier(string $tier): void
    {
        $userIds = $this->quota()->userIdsForTier($tier);

        $storage = 0;
        $files = 0;
        $workspaces = 0;
        $workspacesJoined = 0;

        foreach ($userIds as $userId) {
            $storage += (int) HostedFile::query()->where('user_id', $userId)->sum('size');
            $files += HostedFile::query()->where('user_id', $userId)->count();
            $workspaces += Workspace::query()->where('owner_user_id', $userId)->count();
            $workspacesJoined += WorkspaceMember::query()
                ->where('user_id', $userId)
                ->where('status', WorkspaceMemberStatus::Active->value)
                ->count();
        }

        DB::transaction(function () use ($tier, $storage, $files, $workspaces, $workspacesJoined): void {
            $this->upsertMetricRaw('Archivd', $tier, 'storage_used_bytes', $storage);
            $this->upsertMetricRaw('Archivd', $tier, 'file_count', $files);
            $this->upsertMetricRaw('Archivd', $tier, 'workspace_count_owned', $workspaces);
            $this->upsertMetricRaw('Archivd', $tier, 'workspace_count_joined', $workspacesJoined);
        });

        $this->flushCache('Archivd');
    }

    /**
     * Recompute every tier for a product. Used by the backfill command.
     */
    public function recomputeProduct(string $productKey): void
    {
        if (! ProductRegistry::has($productKey)) {
            return;
        }

        // Clear existing rows first so stale buckets don't linger.
        ProductAnalytic::query()->where('product_key', $productKey)->delete();

        match ($productKey) {
            'Archivd' => collect(['free', 'paid', 'grace', 'n/a'])
                ->each(fn (string $tier) => $this->recomputeArchivdTier($tier)),
            default => null,
        };
    }

    /**
     * Per-user contribution by metric. Used by moveUserTier to compute the
     * delta to transfer between buckets. Single-user indexed queries against
     * source tables — cheap, and only invoked on rare tier transitions.
     *
     * @return array<string, int>
     */
    private function userContributions(string $productKey, string $userId): array
    {
        return match ($productKey) {
            'Archivd' => [
                'storage_used_bytes' => (int) HostedFile::query()->where('user_id', $userId)->sum('size'),
                'file_count' => HostedFile::query()->where('user_id', $userId)->count(),
                'workspace_count_owned' => Workspace::query()->where('owner_user_id', $userId)->count(),
                'workspace_count_joined' => WorkspaceMember::query()
                    ->where('user_id', $userId)
                    ->where('status', WorkspaceMemberStatus::Active->value)
                    ->count(),
            ],
            default => [],
        };
    }

    /**
     * Atomic upsert with a delta. Uses a locked read-modify-write inside the
     * caller's DB::transaction to avoid the race on concurrent increments.
     * SQLite tests and MySQL prod both go through the same path — portable.
     */
    private function upsertMetric(string $productKey, string $tier, string $metricKey, int $delta): void
    {
        $row = ProductAnalytic::query()
            ->where('product_key', $productKey)
            ->where('tier', $tier)
            ->where('metric_key', $metricKey)
            ->lockForUpdate()
            ->first();

        if ($row === null) {
            ProductAnalytic::create([
                'product_key' => $productKey,
                'tier' => $tier,
                'metric_key' => $metricKey,
                'metric_value' => max(0, $delta),
            ]);

            return;
        }

        $row->metric_value = max(0, $row->metric_value + $delta);
        $row->save();
    }

    /**
     * Set an absolute value on a bucket row (used by recompute). Overwrites.
     */
    private function upsertMetricRaw(string $productKey, string $tier, string $metricKey, int $value): void
    {
        ProductAnalytic::query()->updateOrCreate(
            [
                'product_key' => $productKey,
                'tier' => $tier,
                'metric_key' => $metricKey,
            ],
            [
                'metric_value' => max(0, $value),
            ],
        );
    }
}
