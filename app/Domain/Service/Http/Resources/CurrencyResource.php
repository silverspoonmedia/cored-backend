<?php

namespace App\Domain\Service\Http\Resources;

use App\Domain\Service\Models\Currency;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Currency */
class CurrencyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'symbol' => $this->symbol,
            'decimal_places' => $this->decimal_places,
            'is_active' => $this->is_active,
            'is_settlement' => $this->is_settlement,
        ];
    }
}
