<?php

namespace App\Domain\PaymentGateway\Models;

// Enum
use App\Domain\PaymentGateway\Enums\BillingCycle;
use App\Domain\PaymentGateway\Enums\SubscriptionStatus;
// Internal
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Str;

class Subscription extends Model
{
    // Trait
    use HasFactory, HasUuids;

    public function getConnectionName(): ?string
    {
        return config('database.default');
    }

    protected $fillable = [
        'subscription_number',
        'subscriber_name',
        'subscriber_email',
        'subscriber_phone',
        'plan_name',
        'plan_description',
        'billing_cycle',
        'amount',
        'currency',
        'status',
        'trial_ends_at',
        'started_at',
        'expires_at',
        'auto_renew',
        'cancelled_at',
        'cancellation_reason',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'billing_cycle' => BillingCycle::class,
            'amount' => 'decimal:2',
            'status' => SubscriptionStatus::class,
            'trial_ends_at' => 'datetime',
            'started_at' => 'datetime',
            'expires_at' => 'datetime',
            'auto_renew' => 'boolean',
            'cancelled_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return MorphOne<Invoice, $this>
     */
    public function invoice(): MorphOne
    {
        return $this->morphOne(Invoice::class, 'invoiceable');
    }

    public function isActive(): bool
    {
        return $this->status === SubscriptionStatus::Active;
    }

    public function isExpired(): bool
    {
        if ($this->status === SubscriptionStatus::Expired) {
            return true;
        }

        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function daysUntilExpiry(): ?int
    {
        if ($this->expires_at === null) {
            return null;
        }

        if ($this->expires_at->lessThanOrEqualTo(now())) {
            return 0;
        }

        return (int) now()->diffInDays($this->expires_at);
    }

    public static function generateSubscriptionNumber(): string
    {
        $attempts = 0;

        while ($attempts < 5) {
            try {
                $candidate = 'SUB-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));

                if (! static::query()->where('subscription_number', $candidate)->exists()) {
                    return $candidate;
                }
            } catch (\Throwable) {
            }

            $attempts++;
        }

        throw new \RuntimeException('Unable to generate a unique subscription number after 5 attempts.');
    }
}
