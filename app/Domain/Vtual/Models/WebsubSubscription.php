<?php

namespace App\Domain\Vtual\Models;

use App\Domain\Vtual\Enums\WebsubSubscriptionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsubSubscription extends Model
{
    use HasUuids;

    protected $table = 'vtual_websub_subscriptions';

    protected $fillable = [
        'channel_id',
        'topic',
        'callback_url',
        'secret',
        'status',
        'lease_expires_at',
        'subscribed_at',
        'last_notify_at',
        'last_error',
    ];

    protected $hidden = [
        'secret',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => WebsubSubscriptionStatus::class,
            'lease_expires_at' => 'datetime',
            'subscribed_at' => 'datetime',
            'last_notify_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Channel, $this>
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class, 'channel_id');
    }
}
