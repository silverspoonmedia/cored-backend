<?php

namespace App\Domain\Service\Http\Requests\Concerns;

use App\Domain\Service\Enums\BillingInterval;
use App\Domain\Service\Enums\ServiceStatus;
use App\Support\Http\Requests\Concerns\ModalFieldRules;
use Illuminate\Validation\Rule;

trait ServiceCatalogRules
{
    use ModalFieldRules;

    /**
     * @return array<string, list<mixed>>
     */
    protected function catalogRules(bool $forUpdate): array
    {
        $fields = [
            'code' => ['string', 'max:128', 'regex:/^[a-z0-9._-]+$/'],
            'name' => ['string', 'max:255'],
            'description' => [null, 'nullable', 'string', 'max:5000'],
            'billing_interval' => ['string', Rule::in(BillingInterval::values())],
            'fulfillment_target' => [null, 'nullable', 'string', 'max:64'],
            'entitlement' => [null, 'nullable', 'string', 'max:64'],
            'status' => ['string', Rule::in(ServiceStatus::values())],
            'prices' => [null, 'sometimes', 'array'],
            'prices.*.currency' => [
                null,
                'required_with:prices',
                'string',
                'size:3',
                Rule::exists('currencies', 'code')->where(fn ($q) => $q->where('is_active', true)),
            ],
            'prices.*.amount' => [null, 'required_with:prices', 'numeric', 'min:0'],
        ];

        return $forUpdate ? $this->updateRules($fields) : $this->storeRules($fields);
    }

    /**
     * Normalize prices[] for store/update (API owns shaping, not the web proxy).
     */
    protected function prepareCatalogForValidation(): void
    {
        $prices = $this->input('prices');

        if (! is_array($prices)) {
            return;
        }

        $normalized = [];

        foreach ($prices as $row) {
            if (! is_array($row)) {
                continue;
            }

            $currency = isset($row['currency']) ? strtoupper(trim((string) $row['currency'])) : '';
            $amount = $row['amount'] ?? null;

            if ($currency === '' && ($amount === null || $amount === '')) {
                continue;
            }

            $normalized[] = [
                'currency' => $currency,
                'amount' => $amount,
            ];
        }

        $this->merge(['prices' => $normalized]);
    }
}
