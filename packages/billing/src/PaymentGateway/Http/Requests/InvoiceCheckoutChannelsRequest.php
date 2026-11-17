<?php

namespace App\Domain\PaymentGateway\Http\Requests;

use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\PaymentGateway\Support\InvoiceChannelAuthorizer;
use Illuminate\Foundation\Http\FormRequest;

class InvoiceCheckoutChannelsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $invoice = $this->route('invoice');

        if (! $invoice instanceof Invoice) {
            return false;
        }

        $user = $this->user();

        return $user !== null
            && app(InvoiceChannelAuthorizer::class)->canSubscribe($user, $invoice);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
