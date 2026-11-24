<?php

namespace App\Support\Product\Services;

use App\Support\Product\Enums\ProductActivationState;
use App\Support\Product\Exceptions\ProductNotFoundException;
use App\Support\Product\ProductRegistry;

/**
 * Aggregates per-product insight metrics for the admin dashboard.
 *
 * The summary is kernel vocabulary — activation state counts plus whatever
 * product-specific metrics the host contributes. A host with no product
 * installed resolves `[]` for the metrics half and the dashboard renders the
 * card with counts only; the dispatch below is the seam a product extends.
 *
 * Per-user drill-down reads source tables directly: single-user indexed queries
 * are cheap, and the data already lives there, so there is no reason to
 * duplicate it into analytics rows.
 */
class ProductInsightService
{
    public function __construct(
        private readonly ProductActivationService $activations,
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

        return [
            'product_key' => $productKey,
            'activation_type' => ProductRegistry::activationType($productKey)->value,
            'fulfillment_target' => ProductRegistry::fulfillmentTarget($productKey),
            'counts' => $this->stateCounts($productKey),
            'product_metrics' => $this->productMetrics($productKey),
        ];
    }

    /**
     * Product-specific aggregate metrics.
     *
     * A host that installs a product overrides this method (or the class) to add
     * its own bucket. Nothing product-shaped ships here: the key arrives from
     * `config/products.php`, which this boilerplate leaves empty.
     *
     * @return array<string, mixed>
     */
    public function productMetrics(string $productKey): array
    {
        return [];
    }

    /**
     * Per-user drill-down for a product: activation-level detail plus whatever
     * usage detail a host contributes.
     *
     * @return array<string, mixed>
     */
    public function userDetailInProduct(string $userId, string $productKey): array
    {
        $activation = $this->activations->find($userId, $productKey);

        return [
            'user_id' => $userId,
            'product_key' => $productKey,
            'activation' => $activation?->only(['id', 'state', 'source', 'activated_at', 'suspended_at', 'expired_at']),
        ];
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
}
