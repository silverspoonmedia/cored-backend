<?php

namespace App\Domain\PaymentGateway\Services\Payment;

use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\PaymentGateway\Models\PaymentChannel;
use App\Domain\PaymentGateway\Models\PaymentChannelRoute;
use App\Domain\PaymentGateway\Models\PaymentGateway;
use App\Domain\PaymentGateway\Repositories\Contracts\InvoiceRepositoryInterface;
use App\Domain\PaymentGateway\Services\Payment\Ipaymu\IpaymuPaymentService;
use App\Domain\PaymentGateway\Services\Payment\Midtrans\MidtransChargeParameterBuilder;
use App\Domain\PaymentGateway\Services\Payment\Midtrans\MidtransPaymentService;
use App\Domain\Service\Exceptions\ServiceException;
use App\Domain\Service\Models\ServicePurchase;
use App\Domain\Service\Services\ServicePurchaseQuantityService;
use Illuminate\Support\Facades\DB;

class InvoiceChannelCheckoutService
{
    public function __construct(
        private PaymentChannelResolver $channelResolver,
        private InvoiceRepositoryInterface $invoices,
        private InvoiceChannelFeeService $channelFees,
        private MidtransPaymentService $midtransPayments,
        private IpaymuPaymentService $ipaymuPayments,
        private MidtransChargeParameterBuilder $midtransChargeBuilder,
        private PaymentInstructionNormalizer $instructionNormalizer,
        private InvoiceActiveCheckoutCancellationService $checkoutCancellation,
        private ServicePurchaseQuantityService $purchaseQuantities,
        private PaymentChannelRouteSnapshotService $routeSnapshots,
    ) {}

    /**
     * @param  array{channel_code: string, quantity?: int, buyer?: array{name?: string, email?: string, phone?: string}}  $input
     * @return array{ok: true, data: array<string, mixed>, message_key: string, http: int}|array{ok: false, message: string, http: int, snap_fallback_available?: bool, failed_routes?: int}
     */
    public function checkout(Invoice $invoice, array $input): array
    {
        $channelCode = $input['channel_code'];

        $quantityError = $this->applyServicePurchaseQuantityIfNeeded(
            $invoice,
            isset($input['quantity']) ? (int) $input['quantity'] : null,
        );

        if ($quantityError !== null) {
            return $quantityError;
        }

        $this->checkoutCancellation->prepareForChannelCheckout($invoice);

        $channel = PaymentChannel::query()
            ->where('code', $channelCode)
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

            $this->assignGatewayForRoute($invoice, $gateway);

            if ($gateway->name === 'midtrans') {
                $this->channelFees->applyBeforeCharge($invoice, $route, 'midtrans');
                $invoice->refresh();
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

            $this->persistSuccessfulCheckout($invoice, $gateway, $channel, $route);

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

    private function assignGatewayForRoute(Invoice $invoice, PaymentGateway $gateway): void
    {
        DB::transaction(function () use ($invoice, $gateway): void {
            $locked = $this->invoices->findOrFailByIdForUpdate($invoice->id);

            if (! in_array($locked->status, ['pending', 'challenge'], true)) {
                abort(422, __('api/payments.channels.checkout_failed'));
            }

            $locked->payment_gateway_id = $gateway->id;
            $this->invoices->save($locked);
        });

        $invoice->refresh();
    }

    private function persistSuccessfulCheckout(
        Invoice $invoice,
        PaymentGateway $gateway,
        PaymentChannel $channel,
        PaymentChannelRoute $route,
    ): void {
        DB::transaction(function () use ($invoice, $gateway, $channel, $route): void {
            $locked = $this->invoices->findOrFailByIdForUpdate($invoice->id);
            $this->routeSnapshots->capture($locked, $route);
            $locked->payment_gateway_id = $gateway->id;
            $locked->payment_channel_code = $channel->code;
            $locked->payment_channel_route_id = $route->id;
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

    /**
     * @return array{ok: false, message: string, http: int}|null
     */
    private function applyServicePurchaseQuantityIfNeeded(Invoice $invoice, ?int $quantity): ?array
    {
        if ($quantity === null) {
            return null;
        }

        if (! $invoice->isForServicePurchase()) {
            return [
                'ok' => false,
                'message' => __('api/services.quantity_addon_only'),
                'http' => 422,
            ];
        }

        $purchase = ServicePurchase::query()->with('service')->find($invoice->invoiceable_id);

        if ($purchase === null) {
            return [
                'ok' => false,
                'message' => __('api/services.quantity_not_editable'),
                'http' => 422,
            ];
        }

        if ($purchase->service === null || ! $purchase->service->isAddonPlan()) {
            return [
                'ok' => false,
                'message' => __('api/services.quantity_addon_only'),
                'http' => 422,
            ];
        }

        if ((int) $purchase->quantity === $quantity) {
            return null;
        }

        try {
            $this->purchaseQuantities->updateQuantity($purchase, $quantity);
        } catch (ServiceException $e) {
            return [
                'ok' => false,
                'message' => $e->getMessage(),
                'http' => $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 422,
            ];
        }

        $invoice->refresh();

        return null;
    }
}
