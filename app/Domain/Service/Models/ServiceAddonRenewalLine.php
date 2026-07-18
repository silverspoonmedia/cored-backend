<?php

namespace App\Domain\Service\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceAddonRenewalLine extends Model
{
    use HasUuids;

    protected $table = 'service_addon_renewal_lines';

    protected $fillable = [
        'service_addon_renewal_id',
        'service_id',
        'quantity',
        'unit_amount',
        'line_amount',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_amount' => 'decimal:2',
            'line_amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<ServiceAddonRenewal, $this>
     */
    public function renewal(): BelongsTo
    {
        return $this->belongsTo(ServiceAddonRenewal::class, 'service_addon_renewal_id');
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
