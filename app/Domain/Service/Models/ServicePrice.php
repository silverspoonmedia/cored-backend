<?php

namespace App\Domain\Service\Models;

use Database\Factories\ServicePriceFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServicePrice extends Model
{
    /** @use HasFactory<ServicePriceFactory> */
    use HasFactory, HasUuids;

    protected $table = 'service_prices';

    protected $fillable = [
        'service_id',
        'currency',
        'amount',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    protected static function newFactory(): ServicePriceFactory
    {
        return ServicePriceFactory::new();
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
