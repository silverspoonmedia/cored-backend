<?php

namespace App\Support\Product\Services;

use App\Domain\Archivd\Enums\WorkspaceMemberStatus;
use App\Domain\Archivd\Models\HostedFile;
use App\Domain\Archivd\Models\Workspace;
use App\Domain\Archivd\Models\WorkspaceMember;
use App\Domain\Archivd\Services\QuotaService;
use App\Support\Product\Enums\ProductActivationState;
use App\Support\Product\Exceptions\ProductNotFoundException;
use App\Support\Product\Models\ProductAnalytic;
use App\Support\Product\ProductRegistry;
use Illuminate\Support\Facades\Cache;

/**
 * Aggregates per-product insight metrics for the admin dashboard.
 *
 * Aggregate reads hit the stored `product_analytics` table — one row per
 * (product, tier, metric) bucket, kept in sync on domain mutations by
 * ProductAnalyticsService. Per-user drill-down reads source tables directly:
 * single-user indexed queries are cheap, and the data already lives there, so
 * there is no reason to duplicate it into analytics rows.
 */
class ProductInsightService
{
    public function __construct(
        private readonly ProductActivationService $activations,
        private readonly QuotaService $quota,
    ) {}

    /**
     * Top-level summary for a product: identity + activation state counts +
     * product-specific metrics.
     *
     * @return array{
     *     product_key: string,
     *     activation_type: string,
     *     fulfillment_target: string|null,
     *     counts: array{active:int, suspended:int, pending:int, expired:int, total:int},
     *     product_metrics: array<string, mixed>
     * }
     *
     * @throws ProductNotFoundException when the product key is unknown.
     */
    public function productSummary(string $productKey): array
    {
        if (! ProductRegistry::has($productKey)) {
            throw new ProductNotFoundException(
                __('product-activations.unknown_product', ['product' => $productKey]),
            );
        }

        $counts = $this->stateCounts($productKey);

        return [
            'product_key' => $productKey,
            'activation_type' => ProductRegistry::activationType($productKey)->value,
            'fulfillment_target' => ProductRegistry::fulfillmentTarget($productKey),
            'counts' => $counts,
            'product_metrics' => $this->productMetrics($productKey),
        ];
    }

    /**
     * Product-specific aggregate metrics. Dispatches by product key.
     *
     * @return array<string, mixed>
     */
    public function productMetrics(string $productKey): array
    {
        return match ($productKey) {
            'Archivd' => $this->archivdMetrics(),
            default => [],
        };
    }

    /**
     * Per-user drill-down for a product. Returns activation-level + product
     * usage detail for one user.
     *
     * @return array<string, mixed>
     */
    public function userDetailInProduct(string $userId, string $productKey): array
    {
        $activation = $this->activations->find($userId, $productKey);

        $base = [
            'user_id' => $userId,
            'product_key' => $productKey,
            'activation' => $activation?->only(['id', 'state', 'source', 'activated_at', 'suspended_at', 'expired_at']),
        ];

        return match ($productKey) {
            'Archivd' => array_merge($base, $this->archivdUserDetail($userId)),
            default => $base,
        };
    }

    /**
     * @return array{active:int, suspended:int, pending:int, expired:int, total:int}
     */
    private function stateCounts(string $productKey): array
    {
        $active = $this->activations->countByProductState($productKey, ProductActivationState::Active);
        $suspended = $this->activations->countByProductState($productKey, ProductActivationState::Suspended);
        $pending = $this->activations->countByProductState($productKey, ProductActivationState::Pending);
        $expired = $this->activations->countByProductState($productKey, ProductActivationState::Expired);

        return [
            'active' => $active,
            'suspended' => $suspended,
            'pending' => $pending,
            'expired' => $expired,
            'total' => $active + $suspended + $pending + $expired,
        ];
    }

    /**
     * Aggregate FH metrics read directly from the per-(tier, metric) bucket
     * rows. User counts per tier come from QuotaService::userIdsForTier —
     * analytics rows hold sums, not user membership.
     *
     * @return array{
     *     total_storage_used_bytes: int,
     *     total_files: int,
     *     total_workspaces: int,
     *     paid_users_count: int,
     *     free_users_count: int,
     *     grace_users_count: int,
     *     by_tier: array<string, array{storage_used_bytes:int, file_count:int, workspaces:int}>
     * }
     */
    private function archivdMetrics(): array
    {
        return Cache::rememberForever(
            'product_insights.Archivd.metrics',
            function (): array {
                $rows = ProductAnalytic::query()
                    ->where('product_key', 'Archivd')
                    ->get();

                $byTier = [];
                foreach ($rows as $row) {
                    $tier = $row->tier;
                    $byTier[$tier] ??= ['storage_used_bytes' => 0, 'file_count' => 0, 'workspaces' => 0];

                    $value = (int) $row->metric_value;
                    match ($row->metric_key) {
                        'storage_used_bytes' => $byTier[$tier]['storage_used_bytes'] = $value,
                        'file_count' => $byTier[$tier]['file_count'] = $value,
                        'workspace_count_owned' => $byTier[$tier]['workspaces'] = $value,
                        default => null,
                    };
                }

                $tiers = ['free', 'paid', 'grace', 'n/a'];
                $sum = fn (string $metric) => array_sum(array_map(
                    fn (string $t): int => (int) ($byTier[$t][$metric] ?? 0),
                    $tiers,
                ));

                return [
                    'total_storage_used_bytes' => $sum('storage_used_bytes'),
                    'total_files' => $sum('file_count'),
                    'total_workspaces' => $sum('workspaces'),
                    'paid_users_count' => count($this->quota->userIdsForTier('paid')),
                    'free_users_count' => count($this->quota->userIdsForTier('free')),
                    'grace_users_count' => count($this->quota->userIdsForTier('grace')),
                    'by_tier' => $byTier,
                ];
            },
        );
    }

    /**
     * Per-user FH detail. Reads source tables directly (single-user indexed
     * queries) — the data already lives there, so no need to duplicate into
     * analytics rows just for the drill-down view.
     *
     * @return array{
     *     quota: array{used_bytes:int, limit_bytes:int, state:string},
     *     tier: string,
     *     file_count: int,
     *     workspace_count_owned: int,
     *     workspace_count_joined: int,
     *     last_activity_at: string|null
     * }
     */
    private function archivdUserDetail(string $userId): array
    {
        $usage = $this->quota->usage($userId);

        return [
            'quota' => [
                'used_bytes' => $usage['used_bytes'],
                'limit_bytes' => $usage['quota_bytes'],
                'state' => $usage['state'],
            ],
            'tier' => $usage['state'],
            'file_count' => HostedFile::query()->where('user_id', $userId)->count(),
            'workspace_count_owned' => Workspace::query()->where('owner_user_id', $userId)->count(),
            'workspace_count_joined' => WorkspaceMember::query()
                ->where('user_id', $userId)
                ->where('status', WorkspaceMemberStatus::Active->value)
                ->count(),
            'last_activity_at' => HostedFile::query()
                ->where('user_id', $userId)
                ->latest('updated_at')
                ->value('updated_at')?->toIso8601String(),
        ];
    }
}
