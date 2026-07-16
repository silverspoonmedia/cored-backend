<?php

namespace App\Domain\Service\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExchangeRateSnapshot extends Model
{
    use HasUuids;

    protected $table = 'exchange_rate_snapshots';

    protected $fillable = [
        'base_currency',
        'quote_currency',
        'rate',
        'provider',
        'as_of_date',
        'fetched_at',
        'source_payload',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rate' => 'decimal:8',
            'as_of_date' => 'date',
            'fetched_at' => 'datetime',
            'source_payload' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function baseCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'base_currency', 'code');
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function quoteCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'quote_currency', 'code');
    }
}
