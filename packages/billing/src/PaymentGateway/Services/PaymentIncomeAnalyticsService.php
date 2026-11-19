<?php

namespace App\Domain\PaymentGateway\Services;

use App\Domain\PaymentGateway\Enums\DisbursementStatus;
use App\Domain\PaymentGateway\Models\Invoice;
use App\Support\Presentation\MoneyPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PaymentIncomeAnalyticsService
{
    private const PAID_STATUSES = ['paid', 'partially_refunded'];

    /**
     * @param  array{
     *     from?: string|null,
     *     to?: string|null,
     *     basis?: string|null,
     *     gateway?: string|null,
     * }  $filters
     * @return array<string, mixed>
     */
    public function overview(array $filters = []): array
    {
        $basis = $this->resolveBasis($filters);
        $query = $this->paidInvoicesQuery($filters);

        $totals = (clone $query)->selectRaw('
            COUNT(*) as invoice_count,
            COALESCE(SUM(subtotal), 0) as service_revenue,
            COALESCE(SUM(payment_channel_fee), 0) as buyer_fees,
            COALESCE(SUM(customer_gross_total), 0) as cash_collected,
            COALESCE(AVG(subtotal), 0) as avg_order_value
        ')->first();

        $currency = 'IDR';

        return [
            'filters' => [
                'from' => $filters['from'] ?? null,
                'to' => $filters['to'] ?? null,
                'basis' => $basis,
                'gateway' => $filters['gateway'] ?? null,
            ],
            'summary' => $this->moneySummary($totals, $currency),
            'pipeline' => $this->pipelineBuckets($filters),
            'by_gateway' => $this->breakdownByGateway($query, $currency),
            'by_channel' => $this->breakdownByChannel($query, $currency),
            'by_invoiceable_type' => $this->breakdownByInvoiceableType($query, $currency),
            'daily' => $this->dailySeries($query, $basis, $currency),
        ];
    }

    /**
     * @param  array{
     *     from?: string|null,
     *     to?: string|null,
     *     basis?: string|null,
     *     gateway?: string|null,
     * }  $filters
     * @return LengthAwarePaginator<int, Invoice>
     */
    public function invoicesPaginated(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->paidInvoicesQuery($filters)
            ->with(['paymentGateway', 'paymentChannelRouteSnapshot.paymentGateway'])
            ->orderByDesc($this->resolveBasis($filters) === 'withdrawable_at' ? 'withdrawable_at' : 'paid_at')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * @param  array{
     *     from?: string|null,
     *     to?: string|null,
     *     basis?: string|null,
     *     gateway?: string|null,
     * }  $filters
     * @return Builder<Invoice>
     */
    private function paidInvoicesQuery(array $filters): Builder
    {
        $basis = $this->resolveBasis($filters);
        $dateColumn = $basis === 'withdrawable_at' ? 'withdrawable_at' : 'paid_at';

        $query = Invoice::query()
            ->whereIn('status', self::PAID_STATUSES)
            ->whereNotNull($dateColumn);

        $tz = $this->reportingTimezone();

        if (filled($filters['from'] ?? null)) {
            $query->where($dateColumn, '>=', Carbon::parse($filters['from'], $tz)->startOfDay());
        }

        if (filled($filters['to'] ?? null)) {
            $query->where($dateColumn, '<=', Carbon::parse($filters['to'], $tz)->endOfDay());
        }

        if (filled($filters['gateway'] ?? null)) {
            $gateway = (string) $filters['gateway'];
            $query->whereHas('paymentGateway', fn (Builder $q): Builder => $q->where('name', $gateway));
        }

        return $query;
    }

    /**
     * @param  array{
     *     from?: string|null,
     *     to?: string|null,
     *     basis?: string|null,
     *     gateway?: string|null,
     * }  $filters
     * @return array<string, array{invoice_count: int, service_revenue: float, service_revenue_formatted: string|null}>
     */
    private function pipelineBuckets(array $filters): array
    {
        $scoped = $this->paidInvoicesQuery($filters);

        $buckets = [
            'awaiting_settlement' => (clone $scoped)
                ->whereNull('withdrawable_at'),
            'ready_to_disburse' => (clone $scoped)
                ->whereNotNull('withdrawable_at')
                ->whereIn('disbursement_status', [
                    DisbursementStatus::None,
                    DisbursementStatus::Eligible,
                    DisbursementStatus::Failed,
                ]),
            'in_payout' => (clone $scoped)
                ->where('disbursement_status', DisbursementStatus::Processing),
            'disbursed' => (clone $scoped)
                ->where('disbursement_status', DisbursementStatus::Completed),
            'failed_payout' => (clone $scoped)
                ->where('disbursement_status', DisbursementStatus::Failed),
        ];

        $result = [];

        foreach ($buckets as $key => $query) {
            $row = $query->selectRaw('COUNT(*) as invoice_count, COALESCE(SUM(subtotal), 0) as service_revenue')->first();
            $result[$key] = [
                'invoice_count' => (int) ($row->invoice_count ?? 0),
                'service_revenue' => (float) ($row->service_revenue ?? 0),
                'service_revenue_formatted' => MoneyPresenter::formatted((float) ($row->service_revenue ?? 0), 'IDR'),
            ];
        }

        return $result;
    }

    /**
     * @param  Builder<Invoice>  $query
     * @return list<array<string, mixed>>
     */
    private function breakdownByGateway(Builder $query, string $currency): array
    {
        return (clone $query)
            ->leftJoin('payment_gateways', 'payment_gateways.id', '=', 'invoices.payment_gateway_id')
            ->selectRaw("
                COALESCE(payment_gateways.name, 'unknown') as label,
                COUNT(*) as invoice_count,
                COALESCE(SUM(invoices.subtotal), 0) as service_revenue,
                COALESCE(SUM(invoices.payment_channel_fee), 0) as buyer_fees,
                COALESCE(SUM(invoices.customer_gross_total), 0) as cash_collected
            ")
            ->groupBy('payment_gateways.name')
            ->orderByDesc('service_revenue')
            ->get()
            ->map(fn ($row): array => $this->breakdownRow($row, $currency))
            ->values()
            ->all();
    }

    /**
     * @param  Builder<Invoice>  $query
     * @return list<array<string, mixed>>
     */
    private function breakdownByChannel(Builder $query, string $currency): array
    {
        return (clone $query)
            ->selectRaw("
                COALESCE(payment_channel_code, 'unknown') as label,
                COUNT(*) as invoice_count,
                COALESCE(SUM(subtotal), 0) as service_revenue,
                COALESCE(SUM(payment_channel_fee), 0) as buyer_fees,
                COALESCE(SUM(customer_gross_total), 0) as cash_collected
            ")
            ->groupBy('payment_channel_code')
            ->orderByDesc('service_revenue')
            ->limit(15)
            ->get()
            ->map(fn ($row): array => $this->breakdownRow($row, $currency))
            ->values()
            ->all();
    }

    /**
     * @param  Builder<Invoice>  $query
     * @return list<array<string, mixed>>
     */
    private function breakdownByInvoiceableType(Builder $query, string $currency): array
    {
        return (clone $query)
            ->selectRaw("
                COALESCE(invoiceable_type, 'unknown') as label,
                COUNT(*) as invoice_count,
                COALESCE(SUM(subtotal), 0) as service_revenue,
                COALESCE(SUM(payment_channel_fee), 0) as buyer_fees,
                COALESCE(SUM(customer_gross_total), 0) as cash_collected
            ")
            ->groupBy('invoiceable_type')
            ->orderByDesc('service_revenue')
            ->get()
            ->map(fn ($row): array => $this->breakdownRow($row, $currency))
            ->values()
            ->all();
    }

    /**
     * @param  Builder<Invoice>  $query
     * @return list<array<string, mixed>>
     */
    private function dailySeries(Builder $query, string $basis, string $currency): array
    {
        $dateColumn = $basis === 'withdrawable_at' ? 'withdrawable_at' : 'paid_at';
        $driver = DB::connection()->getDriverName();
        $dateExpr = $driver === 'sqlite'
            ? "strftime('%Y-%m-%d', {$dateColumn})"
            : "DATE({$dateColumn})";

        return (clone $query)
            ->selectRaw("
                {$dateExpr} as day,
                COUNT(*) as invoice_count,
                COALESCE(SUM(subtotal), 0) as service_revenue,
                COALESCE(SUM(customer_gross_total), 0) as cash_collected
            ")
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->map(fn ($row): array => [
                'day' => (string) ($row->day ?? ''),
                'invoice_count' => (int) ($row->invoice_count ?? 0),
                'service_revenue' => (float) ($row->service_revenue ?? 0),
                'service_revenue_formatted' => MoneyPresenter::formatted((float) ($row->service_revenue ?? 0), $currency),
                'cash_collected' => (float) ($row->cash_collected ?? 0),
                'cash_collected_formatted' => MoneyPresenter::formatted((float) ($row->cash_collected ?? 0), $currency),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function moneySummary(object $totals, string $currency): array
    {
        $serviceRevenue = (float) ($totals->service_revenue ?? 0);
        $buyerFees = (float) ($totals->buyer_fees ?? 0);
        $cashCollected = (float) ($totals->cash_collected ?? 0);
        $invoiceCount = (int) ($totals->invoice_count ?? 0);
        $avgOrderValue = (float) ($totals->avg_order_value ?? 0);

        return [
            'invoice_count' => $invoiceCount,
            'service_revenue' => $serviceRevenue,
            'service_revenue_formatted' => MoneyPresenter::formatted($serviceRevenue, $currency),
            'buyer_fees' => $buyerFees,
            'buyer_fees_formatted' => MoneyPresenter::formatted($buyerFees, $currency),
            'cash_collected' => $cashCollected,
            'cash_collected_formatted' => MoneyPresenter::formatted($cashCollected, $currency),
            'avg_order_value' => $avgOrderValue,
            'avg_order_value_formatted' => MoneyPresenter::formatted($avgOrderValue, $currency),
            'currency' => $currency,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function breakdownRow(object $row, string $currency): array
    {
        $serviceRevenue = (float) ($row->service_revenue ?? 0);
        $buyerFees = (float) ($row->buyer_fees ?? 0);
        $cashCollected = (float) ($row->cash_collected ?? 0);

        return [
            'label' => (string) ($row->label ?? 'unknown'),
            'invoice_count' => (int) ($row->invoice_count ?? 0),
            'service_revenue' => $serviceRevenue,
            'service_revenue_formatted' => MoneyPresenter::formatted($serviceRevenue, $currency),
            'buyer_fees' => $buyerFees,
            'buyer_fees_formatted' => MoneyPresenter::formatted($buyerFees, $currency),
            'cash_collected' => $cashCollected,
            'cash_collected_formatted' => MoneyPresenter::formatted($cashCollected, $currency),
        ];
    }

    /**
     * @param  array{basis?: string|null}  $filters
     */
    private function resolveBasis(array $filters): string
    {
        $basis = (string) ($filters['basis'] ?? 'paid_at');

        return $basis === 'withdrawable_at' ? 'withdrawable_at' : 'paid_at';
    }

    /**
     * @return array{from: string, to: string, basis: string}
     */
    public function defaultFilters(): array
    {
        $now = Carbon::now($this->reportingTimezone());

        return [
            'from' => $now->copy()->subDays(29)->toDateString(),
            'to' => $now->toDateString(),
            'basis' => 'paid_at',
            'gateway' => null,
        ];
    }

    private function reportingTimezone(): string
    {
        return (string) setting_config('payment.reporting_timezone', 'Asia/Jakarta');
    }
}
