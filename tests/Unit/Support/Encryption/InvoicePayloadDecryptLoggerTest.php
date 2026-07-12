<?php

namespace Tests\Unit\Support\Encryption;

use App\Support\Encryption\DecryptSourceContext;
use App\Support\Encryption\DecryptionContext;
use App\Support\Encryption\InvoicePayloadDecryptLogger;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class InvoicePayloadDecryptLoggerTest extends TestCase
{
    public function test_logs_invoice_gateway_payload_decrypt_with_source_stack(): void
    {
        config(['encryption.log_invoice_payload_decrypts' => true]);

        $logged = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged): void {
            $logged[] = $event;
        });

        $context = new DecryptionContext('invoices', 'inv-1', 'gateway_response_payload', 'payment');

        DecryptSourceContext::run('payment.ipaymu.webhook', function () use ($context): void {
            app(InvoicePayloadDecryptLogger::class)->log($context, false, 'read');
        });

        $this->assertCount(1, $logged);
        $this->assertSame('info', $logged[0]->level);
        $this->assertSame('invoice_payload_decrypt', $logged[0]->message);
        $this->assertSame('inv-1', $logged[0]->context['invoice_id']);
        $this->assertSame('gateway_response_payload', $logged[0]->context['field']);
        $this->assertSame('read', $logged[0]->context['operation']);
        $this->assertFalse($logged[0]->context['cache_hit']);
        $this->assertSame(['payment.ipaymu.webhook'], $logged[0]->context['sources']);
    }

    public function test_skips_logging_when_disabled(): void
    {
        config(['encryption.log_invoice_payload_decrypts' => false]);

        $logged = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged): void {
            $logged[] = $event;
        });

        $context = new DecryptionContext('invoices', 'inv-1', 'gateway_response_payload', 'payment');

        app(InvoicePayloadDecryptLogger::class)->log($context, true, 'compare');

        $this->assertCount(0, $logged);
    }

    public function test_skips_non_invoice_gateway_fields(): void
    {
        config(['encryption.log_invoice_payload_decrypts' => true]);

        $logged = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged): void {
            $logged[] = $event;
        });

        $context = new DecryptionContext('users', 'user-1', 'two_factor_secret', null);

        app(InvoicePayloadDecryptLogger::class)->log($context, false, 'read');

        $this->assertCount(0, $logged);
    }
}
