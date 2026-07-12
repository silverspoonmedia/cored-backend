<?php

namespace App\Domain\PaymentGateway\Console\Commands;

use App\Domain\PaymentGateway\Services\Payment\Ipaymu\IpaymuNotifyUrl;
use App\Domain\PaymentGateway\Services\Payment\Midtrans\MidtransNotifyUrl;
use Illuminate\Console\Command;

class PaymentsWebhookUrlsCommand extends Command
{
    protected $signature = 'payments:webhook-urls';

    protected $description = 'Print public webhook URLs to register with Midtrans Dashboard and iPaymu (from env or route defaults)';

    public function handle(): int
    {
        $this->line('Register these URLs on the <fg=cyan>Monolith Gateway</> public host (not Monolith directly in production).');
        $this->newLine();
        $this->line('Midtrans (Dashboard → Settings → Configuration → Payment Notification URL):');
        $this->info(MidtransNotifyUrl::webhookUrl());
        $this->newLine();
        $this->line('iPaymu (sent automatically as unotify when IPAYMU_NOTIFY_URL is set):');
        $this->info(IpaymuNotifyUrl::webhookUrl());

        return self::SUCCESS;
    }
}
