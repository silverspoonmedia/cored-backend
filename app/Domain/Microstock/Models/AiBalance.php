<?php

namespace App\Domain\Microstock\Models;

use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiBalance extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'microstock_ai_balances';

    protected $primaryKey = 'user_id';

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'balance_micro_usd',
        'updated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'balance_micro_usd' => 'integer',
            'updated_at' => 'datetime',
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
