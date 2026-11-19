<?php

namespace App\Domain\PaymentGateway\Models;

// Internal
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentGateway extends Model
{
    // Trait
    use HasFactory, HasUuids;

    public function getConnectionName(): ?string
    {
        return config('database.default');
    }

    protected $fillable = [
        'name',
        'display_name',
        'is_active',
        'priority',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'priority' => 'integer',
        ];
    }

    public function gatewayConfig(): array
    {
        return config('payment.gateways.'.$this->name, []);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public static function defaultForInvoice(): self
    {
        $gateway = static::query()
            ->where('is_active', true)
            ->orderBy('priority')
            ->orderBy('display_name')
            ->first();

        if ($gateway === null) {
            throw new \RuntimeException(__('api/payments.gateways.none_active'));
        }

        return $gateway;
    }
}
