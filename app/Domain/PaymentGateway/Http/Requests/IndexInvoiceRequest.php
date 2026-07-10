<?php

namespace App\Domain\PaymentGateway\Http\Requests;

use App\Http\Requests\Concerns\InteractsWithListDisplayMode;
use Illuminate\Foundation\Http\FormRequest;

class IndexInvoiceRequest extends FormRequest
{
    use InteractsWithListDisplayMode;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->listDisplayModeRules();
    }
}
