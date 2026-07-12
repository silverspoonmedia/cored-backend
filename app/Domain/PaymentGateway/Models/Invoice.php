<?php

namespace App\Domain\PaymentGateway\Models;

use App\Domain\PaymentGateway\Enums\DisbursementStatus;
use App\Domain\Playground\Models\Order;
use App\Support\Encryption\Casts\EnvelopeEncryptedArray;
// Internal
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/**
 * Polymorphic payment invoice.
 *
 * Monetary split for audit: {@see $subtotal} (merchant sale), {@see $payment_channel_fee} (e.g. VA surcharge),
 * {@see $customer_gross_total} (cash collected from customer = gateway gross).
 */
class Invoice extends Model
{
    // Trait
    use HasFactory, HasUuids;

    public function getConnectionName(): ?string
    {
        return config('database.default');
    }

    protected $fillable = [
        'invoiceable_id',
        'invoiceable_type',
        'payment_gateway_id',
        'invoice_number',
        'external_transaction_id',
        'payment_method',
        'payment_channel_code',
        'subtotal',
        'payment_channel_fee',
        'customer_gross_total',
        'currency',
        'status',
        'gateway_request_payload',
        'gateway_response_payload',
        'paid_at',
        'expired_at',
        'withdrawable_at',
        'disbursement_status',
        'disbursement_processing_at',
        'disbursed_at',
        'disbursement_reference',
        'funds_last_reconciled_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'payment_channel_fee' => 'decimal:2',
            'customer_gross_total' => 'decimal:2',
            'gateway_request_payload' => EnvelopeEncryptedArray::class.':invoices,gateway_request_payload,payment',
            'gateway_response_payload' => EnvelopeEncryptedArray::class.':invoices,gateway_response_payload,payment',
            'paid_at' => 'datetime',
            'expired_at' => 'datetime',
            'withdrawable_at' => 'datetime',
            'disbursement_status' => DisbursementStatus::class,
            'disbursement_processing_at' => 'datetime',
            'disbursed_at' => 'datetime',
            'funds_last_reconciled_at' => 'datetime',
        ];
    }

    public function invoiceable(): MorphTo
    {
        return $this->morphTo();
    }

    public function paymentGateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateway::class);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isFundsWithdrawable(): bool
    {
        return $this->withdrawable_at !== null;
    }

    public function isDisbursementCompleted(): bool
    {
        return $this->disbursement_status === DisbursementStatus::Completed;
    }

    public function isForOrder(): bool
    {
        return $this->invoiceable_type === 'order'
            || $this->invoiceable_type === Order::class;
    }

    public function isForSubscription(): bool
    {
        return $this->invoiceable_type === 'subscription'
            || $this->invoiceable_type === Subscription::class;
    }

    public static function generateInvoiceNumber(): string
    {
        $attempts = 0;

        while ($attempts < 5) {
            try {
                $candidate = 'INV-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));

                if (! static::query()->where('invoice_number', $candidate)->exists()) {
                    return $candidate;
                }
            } catch (\Throwable) {
                // Retry on transient DB errors.
            }

            $attempts++;
        }

        throw new \RuntimeException('Unable to generate a unique invoice number after 5 attempts.');
    }
}
