<?php

namespace App\Domain\PaymentGateway\Http\Requests;

use App\Http\Requests\Concerns\InteractsWithListDisplayMode;
use Illuminate\Foundation\Http\FormRequest;

class IndexSubscriptionRequest extends FormRequest
{
    use InteractsWithListDisplayMode;

    public function authorize(): bool
    {
        return $this->user()?->can('view subscription') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->listDisplayModeRules();
    }
}
