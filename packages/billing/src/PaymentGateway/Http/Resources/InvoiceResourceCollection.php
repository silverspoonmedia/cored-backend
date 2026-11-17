<?php

namespace App\Domain\PaymentGateway\Http\Resources;

use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\PaymentGateway\Models\PaymentChannel;
use App\Domain\PaymentGateway\Services\Payment\InvoiceCheckoutInstructionResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class InvoiceResourceCollection extends ResourceCollection
{
    public $collects = InvoiceResource::class;

    /**
     * @return list<array<string, mixed>>
     */
    public function toArray(Request $request): array
    {
        $codes = [];
        foreach ($this->collection as $item) {
            $invoice = $item instanceof InvoiceResource ? $item->resource : $item;
            if (! $invoice instanceof Invoice) {
                continue;
            }
            if (! in_array($invoice->status, ['pending', 'challenge'], true)) {
                continue;
            }
            $code = $invoice->payment_channel_code;
            if (is_string($code) && $code !== '') {
                $codes[$code] = true;
            }
        }

        if ($codes !== []) {
            $channels = PaymentChannel::query()
                ->whereIn('code', array_keys($codes))
                ->get()
                ->keyBy('code');
            $request->attributes->set(InvoiceCheckoutInstructionResolver::REQUEST_CHANNELS, $channels);
        }

        return parent::toArray($request);
    }
}
