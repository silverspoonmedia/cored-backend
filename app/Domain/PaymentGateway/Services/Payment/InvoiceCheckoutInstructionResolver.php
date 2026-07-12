<?php

namespace App\Domain\PaymentGateway\Services\Payment;

use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\PaymentGateway\Models\PaymentChannel;
use App\Support\Encryption\DecryptSourceContext;

class InvoiceCheckoutInstructionResolver
{
    public function __construct(
        private PaymentInstructionNormalizer $instructionNormalizer,
    ) {}

    /**
     * Rebuild channel checkout instructions from a persisted invoice session.
     *
     * @return array<string, mixed>|null Same shape as channel-checkout POST `data`
     */
    public function resolve(?Invoice $invoice): ?array
    {
        return DecryptSourceContext::run(
            'invoice.checkout_session_resolve',
            fn (): ?array => $this->resolveWithinContext($invoice),
            ['invoice_id' => $invoice?->id],
        );
    }

    /**
     * @return array<string, mixed>|null Same shape as channel-checkout POST `data`
     */
    private function resolveWithinContext(?Invoice $invoice): ?array
    {
        if ($invoice === null) {
            return null;
        }

        if (! in_array($invoice->status, ['pending', 'challenge'], true)) {
            return null;
        }

        $channelCode = $invoice->payment_channel_code;
        $payload = $invoice->gateway_response_payload;

        if (! is_string($channelCode) || $channelCode === '' || ! is_array($payload) || $payload === []) {
            return null;
        }

        $mode = data_get($invoice->gateway_request_payload, 'mode');

        if ($mode === 'redirect') {
            return null;
        }

        $channel = PaymentChannel::query()
            ->where('code', $channelCode)
            ->first();

        if ($channel === null || ! $channel->is_active) {
            return null;
        }

        $gateway = $invoice->paymentGateway;

        if ($gateway === null) {
            return null;
        }

        $normalized = $this->instructionNormalizer->normalize($channel, $gateway, $invoice, $payload);

        if (! $this->hasRenderableInstructions($normalized['instructions'] ?? [])) {
            return null;
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $instructions
     */
    private function hasRenderableInstructions(array $instructions): bool
    {
        return match ($instructions['type'] ?? '') {
            'qris' => ! empty($instructions['qr_string']) || ! empty($instructions['qr_url']),
            'va' => ! empty($instructions['va_number']),
            'ewallet' => ! empty($instructions['deeplink_url'])
                || ! empty($instructions['qr_url'])
                || ! empty($instructions['qr_string']),
            default => false,
        };
    }
}
