<?php

namespace App\Domain\PaymentGateway\Http\Requests\Concerns;

use Illuminate\Validation\Rule;

trait PaymentGatewayRules
{
    /**
     * @return array<string, list<mixed>>
     */
    protected function gatewayRules(bool $forUpdate, ?string $ignoreId = null): array
    {
        if ($forUpdate) {
            return [
                'name' => ['sometimes', 'string', 'max:64', Rule::unique('payment_gateways', 'name')->ignore($ignoreId)],
                'display_name' => ['sometimes', 'string', 'max:255'],
                'is_active' => ['sometimes', 'boolean'],
                'priority' => ['sometimes', 'integer', 'min:0'],
            ];
        }

        return [
            'name' => ['required', 'string', 'max:64', 'unique:payment_gateways,name'],
            'display_name' => ['required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'priority' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
