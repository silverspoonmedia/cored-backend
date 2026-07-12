<?php

namespace App\Domain\Playground\Models;

use App\Domain\Playground\Repositories\Eloquent\OrderRepository;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Order master — the aggregate root of the master-detail pair. Owns many
 * {@see OrderDetail} line items. Monetary totals are derived from the lines and
 * persisted by {@see OrderRepository} inside a single
 * DB transaction, so a master never exists without its computed detail rows.
 *
 * Attachments are kept in the shared `attachments` table (polymorphic), not a
 * JSON column, so file metadata is queryable and managed like any other row.
 */
#[Fillable([
    'order_number',
    'customer_name',
    'customer_email',
    'customer_phone',
    'subtotal',
    'discount',
    'tax',
    'grand_total',
    'status',
    'notes',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory, HasUuids, LogsActivity;

    /**
     * @return OrderFactory
     */
    protected static function newFactory(): OrderFactory
    {
        return OrderFactory::new();
    }

    protected $table = 'order_master';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax' => 'decimal:2',
            'grand_total' => 'decimal:2',
        ];
    }

    /**
     * @return HasMany<OrderDetail, $this>
     */
    public function details(): HasMany
    {
        return $this->hasMany(OrderDetail::class, 'order_master_id');
    }

    /**
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /**
     * @return MorphOne<\App\Domain\PaymentGateway\Models\Invoice, $this>
     */
    public function invoice(): MorphOne
    {
        return $this->morphOne(\App\Domain\PaymentGateway\Models\Invoice::class, 'invoiceable');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('order');
    }
}
