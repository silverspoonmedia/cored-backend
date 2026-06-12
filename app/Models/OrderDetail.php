<?php

namespace App\Models;

use Database\Factories\OrderDetailFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Order detail — a single line item belonging to an {@see Order} master.
 * Created/replaced only through the master aggregate so the parent's totals
 * always reflect the persisted lines.
 */
#[Fillable([
    'order_master_id',
    'product_name',
    'quantity',
    'unit_price',
    'discount',
    'subtotal',
    'notes',
])]
class OrderDetail extends Model
{
    /** @use HasFactory<OrderDetailFactory> */
    use HasFactory, HasUuids;

    protected $table = 'order_detail';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'discount' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_master_id');
    }
}
