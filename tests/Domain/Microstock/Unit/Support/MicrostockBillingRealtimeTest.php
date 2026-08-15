<?php

namespace Tests\Domain\Microstock\Unit\Support;

use App\Domain\Microstock\Events\MicrostockBillingChanged;
use App\Domain\Microstock\Support\MicrostockRealtime;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class MicrostockBillingRealtimeTest extends TestCase
{
    private function enableBroadcast(): void
    {
        config([
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher' => [
                'driver' => 'pusher',
                'key' => 'key',
                'secret' => 'secret',
                'app_id' => 'app',
                'options' => ['cluster' => 'ap1', 'port' => 443, 'scheme' => 'https'],
            ],
        ]);
    }

    private function disableBroadcast(): void
    {
        config(['broadcasting.default' => 'null']);
    }

    public function test_noop_when_unconfigured(): void
    {
        Event::fake([MicrostockBillingChanged::class]);
        $this->disableBroadcast();

        app(MicrostockRealtime::class)->billingChanged('user-1', 'purchase');

        Event::assertNotDispatched(MicrostockBillingChanged::class);
    }

    public function test_dispatches_when_configured(): void
    {
        Event::fake([MicrostockBillingChanged::class]);
        $this->enableBroadcast();

        app(MicrostockRealtime::class)->billingChanged('user-1', 'analysis_debit');

        Event::assertDispatched(MicrostockBillingChanged::class, function (MicrostockBillingChanged $e): bool {
            return $e->ownerUserId === 'user-1'
                && $e->action === 'analysis_debit';
        });
    }
}
