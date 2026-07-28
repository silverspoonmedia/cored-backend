<?php

namespace App\Domain\Microstock\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Microstock\Enums\AiCreditTransactionType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiCreditTransaction extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'microstock_ai_credit_transactions';

    protected $fillable = [
        'user_id',
        'amount_micro_usd',
        'type',
        'reference_type',
        'reference_id',
        'balance_after',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_micro_usd' => 'integer',
            'balance_after' => 'integer',
            'type' => AiCreditTransactionType::class,
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
