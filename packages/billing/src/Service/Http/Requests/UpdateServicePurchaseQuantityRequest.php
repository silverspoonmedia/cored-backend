<?php

namespace App\Domain\Service\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateServicePurchaseQuantityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $max = max(1, (int) setting_config('service.purchase.max_quantity', 99));

        return [
            'quantity' => ['required', 'integer', 'min:1', 'max:'.$max],
        ];
    }
}
