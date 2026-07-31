<?php

namespace App\Domain\PaymentGateway\Support\Routing;

use App\Domain\PaymentGateway\Enums\ChannelRoutingType;
use App\Domain\PaymentGateway\Enums\PaymentChannelKind;
use App\Domain\PaymentGateway\Enums\PaymentRouteRole;
use App\Domain\PaymentGateway\Models\PaymentChannel;
use App\Domain\PaymentGateway\Models\PaymentChannelRoute;
use App\Domain\PaymentGateway\Models\PaymentGateway;
use App\Domain\PaymentGateway\Support\PaymentRealtime;
use Illuminate\Support\Facades\DB;

class PaymentRouteMaterializer
{
    public function __construct(
        private RoutingPolicyLoader $policyLoader,
        private VaBankCatalog $vaBankCatalog,
        private PaymentRealtime $paymentRealtime,
    ) {}

    /**
     * @return array{channels: int, routes: int, skipped: int}
     */
    public function sync(bool $dryRun = false): array
    {
        $policy = $this->policyLoader->load();
        $gateways = PaymentGateway::query()->pluck('id', 'name');

        $stats = ['channels' => 0, 'routes' => 0, 'skipped' => 0];

        $work = function () use ($policy, $gateways, $dryRun, &$stats): void {
            $sortOrder = 10;

            foreach ($this->vaBankCatalog->all() as $bank) {
                $vaPolicy = $policy->vaPolicy();
                if ($vaPolicy === null) {
                    continue;
                }

                $channel = $this->upsertChannel(
                    dryRun: $dryRun,
                    code: $bank->channelCode(),
                    displayName: $bank->displayName,
                    kind: PaymentChannelKind::Va,
                    routingType: ChannelRoutingType::Dual,
                    policyKey: 'va',
                    providerBankCode: $bank->slug,
                    cheapAltCode: null,
                    sortOrder: $sortOrder,
                );
                $sortOrder += 10;
                $stats['channels']++;

                $overrideGateway = $vaPolicy->overrideGatewayForBank($bank->slug);
                $routes = $this->buildVaRoutes($vaPolicy, $bank, $overrideGateway);

                foreach ($routes as $index => $routeDef) {
                    $gatewayName = $routeDef->gateway;
                    $gatewayId = $gateways[$gatewayName] ?? null;
                    if ($gatewayId === null) {
                        $stats['skipped']++;

                        continue;
                    }

                    [$type, $channelCode] = $this->providerCodesForVa($gatewayName, $bank);
                    if ($type === null) {
                        $stats['skipped']++;

                        continue;
                    }

                    $this->upsertRoute(
                        dryRun: $dryRun,
                        channel: $channel,
                        gatewayId: (string) $gatewayId,
                        providerType: $type,
                        providerChannel: $channelCode,
                        routeDef: $routeDef,
                        priority: ($index + 1) * 10,
                    );
                    $stats['routes']++;
                }
            }

            $nonVaChannels = [
                'qris' => ['display' => 'QRIS', 'kind' => PaymentChannelKind::Qris, 'sort' => $sortOrder],
                'gopay' => ['display' => 'GoPay', 'kind' => PaymentChannelKind::Ewallet, 'sort' => $sortOrder + 10],
                'ovo' => ['display' => 'OVO', 'kind' => PaymentChannelKind::Ewallet, 'sort' => $sortOrder + 20],
                'dana' => ['display' => 'DANA', 'kind' => PaymentChannelKind::Ewallet, 'sort' => $sortOrder + 30],
                'shopeepay' => ['display' => 'ShopeePay', 'kind' => PaymentChannelKind::Ewallet, 'sort' => $sortOrder + 40],
                'linkaja' => ['display' => 'LinkAja', 'kind' => PaymentChannelKind::Ewallet, 'sort' => $sortOrder + 50],
            ];

            foreach ($nonVaChannels as $key => $meta) {
                $channelPolicy = $policy->channel($key);
                if ($channelPolicy === null) {
                    continue;
                }

                $channel = $this->upsertChannel(
                    dryRun: $dryRun,
                    code: $key,
                    displayName: $meta['display'],
                    kind: $meta['kind'],
                    routingType: $channelPolicy->type,
                    policyKey: $key,
                    providerBankCode: null,
                    cheapAltCode: $channelPolicy->cheapAltCode,
                    sortOrder: $meta['sort'],
                );
                $stats['channels']++;

                if ($channelPolicy->type === ChannelRoutingType::QrisProxy) {
                    $qrisPolicy = $policy->qrisPolicy();
                    if ($qrisPolicy === null) {
                        continue;
                    }

                    foreach ($qrisPolicy->routes as $index => $routeDef) {
                        $gatewayId = $gateways[$routeDef->gateway] ?? null;
                        if ($gatewayId === null) {
                            $stats['skipped']++;

                            continue;
                        }

                        [$type, $providerChannel] = $this->providerCodesForQris($routeDef->gateway);
                        $this->upsertRoute(
                            dryRun: $dryRun,
                            channel: $channel,
                            gatewayId: (string) $gatewayId,
                            providerType: $type,
                            providerChannel: $providerChannel,
                            routeDef: $routeDef,
                            priority: ($index + 1) * 10,
                            feeOverride: $channelPolicy->sharedFee,
                        );
                        $stats['routes']++;
                    }

                    continue;
                }

                foreach ($channelPolicy->routes as $index => $routeDef) {
                    $gatewayId = $gateways[$routeDef->gateway] ?? null;
                    if ($gatewayId === null) {
                        $stats['skipped']++;

                        continue;
                    }

                    [$type, $providerChannel] = $this->providerCodesForChannel($key, $routeDef->gateway);
                    $this->upsertRoute(
                        dryRun: $dryRun,
                        channel: $channel,
                        gatewayId: (string) $gatewayId,
                        providerType: $type,
                        providerChannel: $providerChannel,
                        routeDef: $routeDef,
                        priority: ($index + 1) * 10,
                    );
                    $stats['routes']++;
                }
            }
        };

        if ($dryRun) {
            $work();

            return $stats;
        }

        DB::transaction($work);

        $this->paymentRealtime->routingCatalogChanged('policy_synced');

        return $stats;
    }

    /**
     * @return list<RoutePolicyDefinition>
     */
    private function buildVaRoutes(
        ChannelPolicyDefinition $vaPolicy,
        VaBankDefinition $bank,
        ?string $overrideGateway,
    ): array {
        if ($overrideGateway !== null) {
            $route = $this->findRouteForGateway($vaPolicy, $overrideGateway)
                ?? $vaPolicy->routes[0];

            return [new RoutePolicyDefinition(
                gateway: $overrideGateway,
                settlementDays: $route->settlementDays,
                fee: $route->fee,
                role: PaymentRouteRole::Only,
                policyTag: $route->policyTag,
            )];
        }

        $routes = [];
        foreach ($vaPolicy->routes as $routeDef) {
            if ($routeDef->gateway === 'ipaymu' && ! $bank->supportsIpaymu()) {
                continue;
            }
            if ($routeDef->gateway === 'midtrans' && ! $bank->supportsMidtrans()) {
                continue;
            }

            $routes[] = $routeDef;
        }

        return $routes;
    }

    private function findRouteForGateway(ChannelPolicyDefinition $policy, string $gateway): ?RoutePolicyDefinition
    {
        foreach ($policy->routes as $route) {
            if ($route->gateway === $gateway) {
                return $route;
            }
        }

        return null;
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function providerCodesForVa(string $gateway, VaBankDefinition $bank): array
    {
        if ($gateway === 'midtrans') {
            return $bank->midtransCode !== null
                ? ['bank_transfer', $bank->midtransCode]
                : [null, null];
        }

        if ($gateway === 'ipaymu') {
            return $bank->ipaymuCode !== null
                ? ['va', $bank->ipaymuCode]
                : [null, null];
        }

        return [null, null];
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    private function providerCodesForQris(string $gateway): array
    {
        return match ($gateway) {
            'midtrans' => ['qris', null],
            'ipaymu' => ['qris', 'qris'],
            default => ['qris', null],
        };
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    private function providerCodesForChannel(string $channelKey, string $gateway): array
    {
        if ($channelKey === 'qris') {
            return $this->providerCodesForQris($gateway);
        }

        if ($gateway !== 'midtrans') {
            return ['qris', $channelKey];
        }

        return match ($channelKey) {
            'gopay' => ['gopay', null],
            'ovo' => ['ovo', null],
            'dana' => ['dana', null],
            'shopeepay' => ['shopeepay', null],
            default => [$channelKey, null],
        };
    }

    private function upsertChannel(
        bool $dryRun,
        string $code,
        string $displayName,
        PaymentChannelKind $kind,
        ChannelRoutingType $routingType,
        ?string $policyKey,
        ?string $providerBankCode,
        ?string $cheapAltCode,
        int $sortOrder,
    ): PaymentChannel {
        $attributes = [
            'display_name' => $displayName,
            'kind' => $kind,
            'is_active' => true,
            'sort_order' => $sortOrder,
            'provider_bank_code' => $providerBankCode,
            'cheap_alt_code' => $cheapAltCode,
            'routing_type' => $routingType,
            'policy_key' => $policyKey,
        ];

        if ($dryRun) {
            $channel = PaymentChannel::query()->firstOrNew(['code' => $code]);
            $channel->fill($attributes);

            return $channel;
        }

        return PaymentChannel::query()->updateOrCreate(['code' => $code], $attributes);
    }

    private function upsertRoute(
        bool $dryRun,
        PaymentChannel $channel,
        string $gatewayId,
        string $providerType,
        ?string $providerChannel,
        RoutePolicyDefinition $routeDef,
        int $priority,
        ?FeePolicyDefinition $feeOverride = null,
    ): PaymentChannelRoute {
        $fee = $feeOverride ?? $routeDef->fee;

        $attributes = [
            'provider_payment_type' => $providerType,
            'provider_payment_channel' => $providerChannel,
            'priority' => $priority,
            'route_role' => $routeDef->role,
            'settlement_days' => $routeDef->settlementDays,
            'fee_flat' => $fee->flatIdr,
            'fee_percent' => $fee->percent,
            'fee_vat_inclusive' => $fee->vatInclusive,
            'policy_tag' => $routeDef->policyTag,
            'is_policy_managed' => true,
            'is_active' => true,
        ];

        if ($dryRun) {
            $route = PaymentChannelRoute::query()->firstOrNew([
                'payment_channel_id' => $channel->id,
                'payment_gateway_id' => $gatewayId,
            ]);
            $route->fill($attributes);

            return $route;
        }

        return PaymentChannelRoute::query()->updateOrCreate(
            [
                'payment_channel_id' => $channel->id,
                'payment_gateway_id' => $gatewayId,
            ],
            $attributes,
        );
    }
}
