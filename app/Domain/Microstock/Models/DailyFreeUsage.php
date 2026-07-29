<?php

namespace App\Domain\Microstock\Models;

use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyFreeUsage extends Model
{
    use HasUuids;

    protected $table = 'microstock_daily_free_usage';

    protected $fillable = [
        'user_id',
        'usage_date',
        'analyses_used',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'usage_date' => 'date',
            'analyses_used' => 'integer',
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
