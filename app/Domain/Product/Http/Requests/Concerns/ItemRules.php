<?php

namespace App\Domain\Product\Http\Requests\Concerns;

use App\Domain\Product\Enums\ItemStatus;
use App\Support\Http\Requests\Concerns\ModalFieldRules;
use Illuminate\Validation\Rule;

trait ItemRules
{
    use ModalFieldRules;

    /**
     * @return array<string, list<mixed>>
     */
    protected function itemRules(bool $forUpdate): array
    {
        if ($forUpdate) {
            // Update historically used sometimes + required for present keys
            return [
                'sku' => ['sometimes', 'required', 'string', 'max:64', 'alpha_dash'],
                'name' => ['sometimes', 'required', 'string', 'max:255'],
                'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
                'unit_price_cents' => ['sometimes', 'required', 'integer', 'min:0'],
                'status' => ['sometimes', 'required', 'string', Rule::in(ItemStatus::values())],
            ];
        }

        return $this->storeRules([
            'sku' => ['string', 'max:64', 'alpha_dash'],
            'name' => ['string', 'max:255'],
            'description' => [null, 'nullable', 'string', 'max:5000'],
            'unit_price_cents' => ['integer', 'min:0'],
            'status' => ['string', Rule::in(ItemStatus::values())],
        ]);
    }
}
