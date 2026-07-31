<?php

namespace App\Domain\PaymentGateway\Services;

use App\Domain\PaymentGateway\Models\Invoice;
use App\Domain\PaymentGateway\Support\Routing\PaymentChannelFeeCalculator;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceCsvExportService
{
    public function __construct(
        private PaymentChannelFeeCalculator $feeCalculator,
    ) {}

    /**
     * @param  array{status?: string|null, paid_from?: string|null, paid_to?: string|null}  $filters
     */
    public function stream(array $filters = []): StreamedResponse
    {
        $filename = 'invoices-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($filters): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, $this->headers());

            $this->query($filters)
                ->orderByDesc('created_at')
                ->chunkById(200, function ($invoices) use ($handle): void {
                    foreach ($invoices as $invoice) {
                        fputcsv($handle, $this->row($invoice));
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  array{status?: string|null, paid_from?: string|null, paid_to?: string|null}  $filters
     * @return Builder<Invoice>
     */
    private function query(array $filters): Builder
    {
        return Invoice::query()
            ->with(['paymentGateway', 'paymentChannelRoute.paymentGateway'])
            ->when(
                filled($filters['status'] ?? null),
                fn (Builder $query): Builder => $query->where('status', $filters['status']),
            )
            ->when(
                filled($filters['paid_from'] ?? null),
                fn (Builder $query): Builder => $query->whereDate('paid_at', '>=', $filters['paid_from']),
            )
            ->when(
                filled($filters['paid_to'] ?? null),
                fn (Builder $query): Builder => $query->whereDate('paid_at', '<=', $filters['paid_to']),
            );
    }

    /**
     * @return list<string>
     */
    private function headers(): array
    {
        return [
            'invoice_number',
            'status',
            'created_at',
            'paid_at',
            'currency',
            'subtotal',
            'payment_channel_fee',
            'customer_gross_total',
            'payment_channel_code',
            'payment_gateway',
            'route_role',
            'settlement_days',
            'fee_label',
            'external_transaction_id',
            'invoiceable_type',
        ];
    }

    /**
     * @return list<int|float|string|null>
     */
    private function row(Invoice $invoice): array
    {
        $route = $invoice->paymentChannelRoute;

        return [
            $invoice->invoice_number,
            $invoice->status,
            $invoice->created_at?->toDateTimeString(),
            $invoice->paid_at?->toDateTimeString(),
            $invoice->currency,
            (float) $invoice->subtotal,
            (float) $invoice->payment_channel_fee,
            (float) $invoice->customer_gross_total,
            $invoice->payment_channel_code,
            $invoice->paymentGateway?->name ?? $route?->paymentGateway?->name,
            $route?->route_role?->value ?? $route?->route_role,
            $route?->settlement_days,
            $route ? $this->feeCalculator->formatFeeLabel($route) : null,
            $invoice->external_transaction_id,
            $invoice->invoiceable_type,
        ];
    }
}
