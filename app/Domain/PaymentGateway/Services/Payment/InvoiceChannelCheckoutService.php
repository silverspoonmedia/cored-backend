<?php

namespace App\Domain\PaymentGateway\Services\Payment;

use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\PaymentGateway\Models\PaymentChannel;
use App\Domain\PaymentGateway\Models\PaymentChannelRoute;
use App\Domain\PaymentGateway\Repositories\Contracts\InvoiceRepositoryInterface;
use App\Domain\PaymentGateway\Services\Payment\Ipaymu\IpaymuPaymentService;
use App\Domain\PaymentGateway\Services\Payment\Midtrans\MidtransChargeParameterBuilder;
use App\Domain\PaymentGateway\Services\Payment\Midtrans\MidtransPaymentService;
use Illuminate\Support\Facades\DB;

class InvoiceChannelCheckoutService
{
    public function __construct(
        private PaymentChannelResolver $channelResolver,
        private InvoiceRepositoryInterface $invoices,
        private MidtransPaymentService $midtransPayments,
        private IpaymuPaymentService $ipaymuPayments,
        private MidtransChargeParameterBuilder $midtransChargeBuilder,
        private PaymentInstructionNormalizer $instructionNormalizer,
    ) {}

    /**
     * @param  array{channel_code: string, buyer?: array{name?: string, email?: string, phone?: string}}  $input
     * @return array{ok: true, data: array<string, mixed>, message_key: string, http: int}|array{ok: false, message: string, http: int, snap_fallback_available?: bool, failed_routes?: int}
     */
    public function checkout(Invoice $invoice, array $input): array
    {
        $channel = PaymentChannel::query()
            ->where('code', $input['channel_code'])
            ->where('is_active', true)
            ->first();

        if ($channel === null) {
            return [
                'ok' => false,
                'message' => __('api/payments.channels.not_available'),
                'http' => 422,
                'snap_fallback_available' => true,
                'failed_routes' => 0,
            ];
        }

        $routes = $this->channelResolver->listViableRoutes($channel);

        if ($routes->isEmpty()) {
            return [
                'ok' => false,
                'message' => __('api/payments.channels.not_available'),
                'http' => 422,
                'snap_fallback_available' => true,
                'failed_routes' => 0,
            ];
        }

        $lastMessage = __('api/payments.channels.checkout_failed');
        $failedRoutes = 0;

        foreach ($routes as $route) {
            $route->loadMissing('paymentGateway');
            $gateway = $route->paymentGateway;

            if ($gateway === null) {
                $failedRoutes++;

                continue;
            }

            $chargeResult = match ($gateway->name) {
                'midtrans' => $this->chargeMidtrans($invoice, $route),
                'ipaymu' => $this->chargeIpaymu($invoice, $route, $input['buyer'] ?? null),
                default => [
                    'ok' => false,
                    'message' => __('api/payments.channels.unsupported_gateway'),
                    'http' => 422,
                ],
            };

            if (! $chargeResult['ok']) {
                $lastMessage = $chargeResult['message'];
                $failedRoutes++;

                continue;
            }

            $this->persistSuccessfulCheckout($invoice, $gateway, $channel);

            $invoice->refresh();

            return [
                'ok' => true,
                'data' => $this->instructionNormalizer->normalize(
                    $channel,
                    $gateway,
                    $invoice,
                    $chargeResult['data'],
                ),
                'message_key' => 'api/payments.channels.checkout_created',
                'http' => 201,
            ];
        }

        return [
            'ok' => false,
            'message' => $lastMessage,
            'http' => 422,
            'snap_fallback_available' => true,
            'failed_routes' => $failedRoutes,
        ];
    }

    private function persistSuccessfulCheckout(Invoice $invoice, $gateway, PaymentChannel $channel): void
    {
        DB::transaction(function () use ($invoice, $gateway, $channel): void {
            $locked = $this->invoices->findOrFailByIdForUpdate($invoice->id);

            if ($this->canReassignGateway($locked) && $locked->payment_gateway_id !== $gateway->id) {
                $locked->payment_gateway_id = $gateway->id;
            }

            $locked->payment_channel_code = $channel->code;
            $this->invoices->save($locked);
        });
    }

    /**
     * @return array{ok: true, data: array<string, mixed>}|array{ok: false, message: string, http: int}
     */
    private function chargeMidtrans(Invoice $invoice, PaymentChannelRoute $route): array
    {
        $charge = $this->midtransChargeBuilder->buildFromRoute($route, $invoice);

        $result = $this->midtransPayments->charge([
            'invoice_id' => $invoice->id,
            'charge' => $charge,
        ]);

        if (! $result['ok']) {
            return $result;
        }

        return ['ok' => true, 'data' => $result['data']];
    }

    /**
     * @param  ?array{name?: string, email?: string, phone?: string}  $buyer
     * @return array{ok: true, data: array<string, mixed>}|array{ok: false, message: string, http: int}
     */
    private function chargeIpaymu(Invoice $invoice, PaymentChannelRoute $route, ?array $buyer): array
    {
        $payload = [
            'invoice_id' => $invoice->id,
            'payment_method' => $route->provider_payment_type,
            'payment_channel' => (string) ($route->provider_payment_channel ?? ''),
        ];

        if ($buyer !== null) {
            $payload['buyer'] = $buyer;
        }

        $result = $this->ipaymuPayments->charge($payload);

        if (! $result['ok']) {
            return $result;
        }

        return ['ok' => true, 'data' => $result['data']];
    }

    private function canReassignGateway(Invoice $invoice): bool
    {
        $tid = $invoice->external_transaction_id;

        return $tid === null || $tid === '';
    }
}
