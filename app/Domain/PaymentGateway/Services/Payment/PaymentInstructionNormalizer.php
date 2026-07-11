<?php

namespace App\Domain\PaymentGateway\Services\Payment;

use App\Domain\PaymentGateway\Enums\PaymentChannelKind;
use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\PaymentGateway\Models\PaymentChannel;
use App\Domain\PaymentGateway\Models\PaymentGateway;
use Carbon\Carbon;

class PaymentInstructionNormalizer
{
    /**
     * @param  array<string, mixed>  $rawResponse
     * @return array<string, mixed>
     */
    public function normalize(
        PaymentChannel $channel,
        PaymentGateway $gateway,
        Invoice $invoice,
        array $rawResponse,
    ): array {
        $instructions = match ($gateway->name) {
            'midtrans' => $this->normalizeMidtrans($channel->kind, $rawResponse),
            'ipaymu' => $this->normalizeIpaymu($channel->kind, $rawResponse),
            default => ['type' => $channel->kind->value],
        };

        return [
            'channel_code' => $channel->code,
            'kind' => $channel->kind->value,
            'gateway' => $gateway->name,
            'invoice_id' => $invoice->id,
            'amount' => (float) $invoice->customer_gross_total,
            'currency' => $invoice->currency,
            'expires_at' => $this->resolveExpiresAt($gateway->name, $rawResponse, $invoice),
            'external_transaction_id' => $invoice->external_transaction_id,
            'instructions' => $instructions,
        ];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function normalizeMidtrans(PaymentChannelKind $kind, array $raw): array
    {
        $actions = is_array($raw['actions'] ?? null) ? $raw['actions'] : [];
        $qrUrl = $this->firstActionUrl($actions, ['generate-qr-code', 'generate-qr-code-v2', 'qr-code']);
        $deeplink = $this->firstActionUrl($actions, ['deeplink-redirect', 'use-app', 'download-qr']);

        return match ($kind) {
            PaymentChannelKind::Qris => [
                'type' => 'qris',
                'qr_string' => (string) ($raw['qr_string'] ?? ''),
                'qr_url' => $this->resolveQrMedia($qrUrl),
            ],
            PaymentChannelKind::Va => [
                'type' => 'va',
                'bank_name' => (string) (data_get($raw, 'va_numbers.0.bank') ?? data_get($raw, 'permata_va_number') ? 'permata' : ''),
                'va_number' => (string) (
                    data_get($raw, 'va_numbers.0.va_number')
                    ?? data_get($raw, 'permata_va_number')
                    ?? data_get($raw, 'biller_code')
                    ?? ''
                ),
            ],
            PaymentChannelKind::Ewallet => [
                'type' => 'ewallet',
                'deeplink_url' => $deeplink,
                'qr_url' => $this->resolveQrMedia($qrUrl),
                'qr_string' => (string) ($raw['qr_string'] ?? ''),
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function normalizeIpaymu(PaymentChannelKind $kind, array $raw): array
    {
        $data = is_array($raw['Data'] ?? null) ? $raw['Data'] : $raw;

        $qrString = (string) (
            data_get($data, 'QrString')
            ?? data_get($data, 'QrCode')
            ?? data_get($data, 'qr_string')
            ?? data_get($data, 'qr_code')
            ?? ''
        );
        $qrImage = (string) (
            data_get($data, 'QrImage')
            ?? data_get($data, 'QrUrl')
            ?? data_get($data, 'qr_image')
            ?? data_get($data, 'qr_url')
            ?? ''
        );

        return match ($kind) {
            PaymentChannelKind::Qris => [
                'type' => 'qris',
                'qr_string' => $qrString,
                'qr_url' => $this->resolveQrMedia($qrImage),
            ],
            PaymentChannelKind::Va => [
                'type' => 'va',
                'bank_name' => (string) (data_get($data, 'Channel') ?? data_get($data, 'PaymentChannel') ?? ''),
                'va_number' => (string) (data_get($data, 'PaymentNo') ?? data_get($data, 'Va') ?? ''),
                'payment_name' => (string) (data_get($data, 'PaymentName') ?? ''),
            ],
            PaymentChannelKind::Ewallet => [
                'type' => 'ewallet',
                'deeplink_url' => (string) (data_get($data, 'Url') ?? data_get($data, 'Deeplink') ?? ''),
                'qr_url' => $this->resolveQrMedia((string) (data_get($data, 'QrImage') ?? data_get($data, 'qr_image') ?? '')),
                'qr_string' => $qrString !== '' ? $qrString : (string) (data_get($data, 'QrString') ?? ''),
            ],
        };
    }

    private function resolveQrMedia(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (str_starts_with($value, 'http://')
            || str_starts_with($value, 'https://')
            || str_starts_with($value, 'data:')) {
            return $value;
        }

        if ($this->looksLikeBase64($value)) {
            return 'data:image/png;base64,'.$value;
        }

        return $value;
    }

    private function looksLikeBase64(string $value): bool
    {
        if (strlen($value) < 32) {
            return false;
        }

        return (bool) preg_match('/^[A-Za-z0-9+\/]+=*$/', $value);
    }

    /**
     * @param  list<array<string, mixed>>  $actions
     * @param  list<string>  $names
     */
    private function firstActionUrl(array $actions, array $names): string
    {
        foreach ($actions as $action) {
            if (! is_array($action)) {
                continue;
            }

            $name = (string) ($action['name'] ?? '');
            if (in_array($name, $names, true) && ! empty($action['url'])) {
                return (string) $action['url'];
            }
        }

        foreach ($actions as $action) {
            if (is_array($action) && ! empty($action['url'])) {
                return (string) $action['url'];
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function resolveExpiresAt(string $gatewayName, array $raw, Invoice $invoice): ?string
    {
        if ($invoice->expired_at !== null) {
            return $invoice->expired_at->toIso8601String();
        }

        $candidate = match ($gatewayName) {
            'midtrans' => data_get($raw, 'expiry_time'),
            'ipaymu' => data_get($raw, 'Data.Expired') ?? data_get($raw, 'Data.ExpiredDate'),
            default => null,
        };

        if (! is_string($candidate) || $candidate === '') {
            return null;
        }

        try {
            return Carbon::parse($candidate)->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }
}
