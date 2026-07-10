<?php

namespace App\Domain\PaymentGateway\Services\Billing;

use App\Domain\PaymentGateway\Enums\DisbursementStatus;
use App\Domain\PaymentGateway\Models\Invoice;
use Carbon\Carbon;

/**
 * Tracks gateway “balance / settlement” separately from {@see Invoice::$status}.
 * Midtrans maps {@code settlement} to local {@code paid}; iPaymu maps {@code SUCCESS_UNSETTLE} to {@code paid} too,
 * so {@code withdrawable_at} is the field that answers “sudah bisa cair ke rekening merchant”.
 */
class InvoiceFundsLedgerService
{
    /**
     * @param  array<string, mixed>  $row  Midtrans status / notification payload.
     */
    public function applyMidtrans(Invoice $invoice, array $row, string $invoiceStatus): void
    {
        $invoice->funds_last_reconciled_at = now();

        if (! in_array($invoiceStatus, ['paid', 'partially_refunded'], true)) {
            $this->revertOpenDisbursement($invoice);

            return;
        }

        if ($this->midtransRowIsWithdrawable($row) && $invoice->withdrawable_at === null) {
            $invoice->withdrawable_at = $this->midtransWithdrawableTimestamp($row) ?? now();
        }

        $this->promoteEligible($invoice);
    }

    /**
     * @param  array<string, mixed>  $data  iPaymu checkTransaction {@code Data} object.
     */
    public function applyIpaymu(Invoice $invoice, array $data, string $invoiceStatus): void
    {
        $invoice->funds_last_reconciled_at = now();

        if (! in_array($invoiceStatus, ['paid', 'partially_refunded'], true)) {
            $this->revertOpenDisbursement($invoice);

            return;
        }

        $code = isset($data['Status']) ? (int) $data['Status'] : 0;

        if ($code === 1 && $invoice->withdrawable_at === null) {
            $invoice->withdrawable_at = $this->parseIpaymuDate($data['SettlementDate'] ?? $data['SuccessDate'] ?? null)
                ?? now();
        }

        $this->promoteEligible($invoice);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function midtransRowIsWithdrawable(array $row): bool
    {
        $ts = $row['transaction_status'] ?? '';

        if (is_string($ts) && in_array($ts, ['settlement', 'partial_refund'], true)) {
            return true;
        }

        return ! empty($row['settlement_time']);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function midtransWithdrawableTimestamp(array $row): ?Carbon
    {
        if (! empty($row['settlement_time'])) {
            try {
                return Carbon::parse((string) $row['settlement_time']);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function parseIpaymuDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (is_string($value)) {
            try {
                return Carbon::parse($value);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    protected function promoteEligible(Invoice $invoice): void
    {
        if ($invoice->withdrawable_at === null) {
            return;
        }

        if ($invoice->disbursement_status !== DisbursementStatus::None) {
            return;
        }

        $invoice->disbursement_status = DisbursementStatus::Eligible;
    }

    /**
     * Clears withdrawable / queue state unless money is already mid-flight or finished.
     */
    protected function revertOpenDisbursement(Invoice $invoice): void
    {
        if (in_array($invoice->disbursement_status, [DisbursementStatus::Completed, DisbursementStatus::Processing], true)) {
            return;
        }

        $invoice->withdrawable_at = null;
        $invoice->disbursement_status = DisbursementStatus::None;
    }
}
