<?php

namespace App\Domain\Archivd\Support\Billing;

use App\Domain\Archivd\Support\ArchivdCatalog;
use App\Support\Billing\Contracts\TierObserver;
use App\Support\Product\Services\ProductAnalyticsService;

/**
 * Archivd tier bookkeeping (`product_analytics` counters).
 *
 * The billing package derives the before/after tier from its generic probes;
 * only Archivd knows the counter it records into, so the mapping lives here.
 */
final class ArchivdTierObserver implements TierObserver
{
    public const PRODUCT_KEY = 'Archivd';

    public function __construct(
        private readonly ProductAnalyticsService $analytics,
    ) {}

    public function targets(): array
    {
        return [ArchivdCatalog::FULFILLMENT_TARGET];
    }

    public function tierChanged(string $userId, ?string $before, string $after): void
    {
        $this->analytics->moveUserTier(self::PRODUCT_KEY, $userId, $before, $after);
    }
}
