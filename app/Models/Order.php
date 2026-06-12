<?php

namespace App\Models;

use App\Repositories\Eloquent\OrderRepository;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Order master — the aggregate root of the master-detail pair. Owns many
 * {@see OrderDetail} line items. Monetary totals are derived from the lines and
 * persisted by {@see OrderRepository} inside a single
 * DB transaction, so a master never exists without its computed detail rows.
 */
#[Fillable([
    'order_number',
    'customer_name',
    'customer_email',
    'customer_phone',
    'attachment',
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
    use HasFactory, HasUuids;

    protected $table = 'order_master';

    /**
     * Each attachment is stored as `{path, name, size, mime}`. `attachment`
     * holds a JSON array so one order can carry multiple files; it defaults to
     * an empty array so the non-nullable column is always satisfied.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'attachment' => '[]',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attachment' => 'array',
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
}
