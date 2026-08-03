<?php

namespace App\Domain\Service\Services;

use App\Domain\Service\Models\Currency;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CurrencyAdminService
{
    /**
     * @return Collection<int, Currency>
     */
    public function list(): Collection
    {
        return Currency::query()->orderBy('code')->get();
    }

    public function find(string $code): Currency
    {
        $currency = Currency::query()->find($code);

        if ($currency === null) {
            throw ValidationException::withMessages([
                'code' => [__('api/currencies.not_found')],
            ]);
        }

        return $currency;
    }

    /**
     * @param  array{is_active?: bool, is_settlement?: bool}  $data
     */
    public function update(string $code, array $data): Currency
    {
        $currency = $this->find($code);

        if (array_key_exists('is_settlement', $data) && $data['is_settlement']) {
            Currency::query()->where('code', '!=', $code)->update(['is_settlement' => false]);
        }

        $currency->fill(array_intersect_key($data, array_flip(['is_active', 'is_settlement'])));
        $currency->save();

        return $currency->fresh() ?? $currency;
    }
}
