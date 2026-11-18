<?php

namespace App\Domain\Currency\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Currency catalog changed (activation/settlement toggle) — currency
 * admin list soft-refetch. Same permission surface as settings admin.
 */
class CurrencyChanged implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $action,
        public readonly ?string $currencyCode = null,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('settings.currencies')];
    }

    public function broadcastAs(): string
    {
        // Wire name kept from the Service namespace: SPA
        // useCurrenciesRealtime.ts subscribes to it, and an event rename is a
        // client contract change, not part of an internal namespace move.
        return 'service.currency.changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'currency_code' => $this->currencyCode,
        ];
    }
}
