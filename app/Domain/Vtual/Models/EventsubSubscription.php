<?php

namespace App\Domain\Vtual\Models;

use App\Domain\Vtual\Enums\TwitchEventSubStatus;
use App\Domain\Vtual\Enums\TwitchEventSubType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Twitch EventSub webhook subscription for a channel — one row per
 * (channel, event type). Secret is the per-subscription HMAC key Twitch signs
 * every delivery with (sha256 over message_id + timestamp + body).
 */
class EventsubSubscription extends Model
{
    use HasUuids;

    protected $table = 'vtual_eventsub_subscriptions';

    protected $fillable = [
        'channel_id',
        'external_id',
        'event_type',
        'twitch_subscription_id',
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
            'status' => TwitchEventSubStatus::class,
            'event_type' => TwitchEventSubType::class,
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
