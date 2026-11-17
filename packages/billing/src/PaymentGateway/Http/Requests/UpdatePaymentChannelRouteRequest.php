<?php

namespace App\Domain\PaymentGateway\Http\Requests;

use App\Domain\PaymentGateway\Models\PaymentChannelRoute;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdatePaymentChannelRouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage payment gateway') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'is_active' => ['sometimes', 'boolean'],
            'priority' => ['sometimes', 'integer', 'min:0', 'max:9999'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->has('priority')) {
                return;
            }

            $route = PaymentChannelRoute::query()->find($this->route('route'));

            if ($route?->is_policy_managed) {
                $validator->errors()->add('priority', __('api/payments.channels.policy_managed_route'));
            }
        });
    }
}
