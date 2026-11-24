<?php

namespace App\Support\Product\Services;

use App\Support\Product\Models\ProductAnalytic;
use App\Support\Product\ProductRegistry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Write-side analytics store. Increments/decrements per-(product, tier, metric)
 * aggregate rows on domain mutations and flushes the dashboard read cache.
 *
 * Each row is one bucket (`{product}` + `{tier}` + `{metric}`). A product that
 * wants buckets publishes them from its own service: the delta a bucket needs is
 * product knowledge, and this class deliberately holds none. With no product
 * installed the table stays empty and every read is zero — a legal state, not a
 * missing install step.
 */
class ProductAnalyticsService
{
    /**
     * Resolve the current tier label for a user. A host that bills by tier
     * overrides this; the kernel's answer is the neutral bucket.
     */
    public function tierFor(string $userId): string
    {
        return 'n/a';
    }

    /**
     * Atomic increment of a bucket counter. Upserts the row if absent.
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
     * Atomic decrement of a bucket counter, floored at 0.
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
     * another. Used on tier transitions (free -> paid, paid -> grace, …).
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
            foreach ($this->userContributions($productKey, $userId) as $metricKey => $value) {
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
     * Recompute every tier for a product. Used by the backfill command.
     *
     * The absolute values a tier's buckets should hold are product knowledge; a
     * host overrides this to recompute its own rows. Clearing the product's rows
     * is the kernel half and always runs, so a re-run never leaves stale buckets.
     */
    public function recomputeProduct(string $productKey): void
    {
        if (! ProductRegistry::has($productKey)) {
            return;
        }

        ProductAnalytic::query()->where('product_key', $productKey)->delete();

        $this->recomputeTiers($productKey);

        $this->flushCache($productKey);
    }

    /**
     * Per-product recompute hook. A host that owns buckets overrides this.
     */
    protected function recomputeTiers(string $productKey): void
    {
        //
    }

    /**
     * Per-user contribution by metric. A host that owns buckets overrides this.
     *
     * @return array<string, int>
     */
    protected function userContributions(string $productKey, string $userId): array
    {
        return [];
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
}
