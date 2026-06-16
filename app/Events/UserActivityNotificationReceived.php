<?php

namespace App\Events;

use App\Notifications\Activity\NotificationType;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Lightweight ping on a user's private channel when a new activity notification
 * is visible to them. The bell refetches unread count / recent items over HTTP
 * so message copy stays locale-aware. Also broadcasts type + tableId for
 * DataTable live reload.
 */
class UserActivityNotificationReceived implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $userId,
        public readonly NotificationType $type,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('App.Models.User.'.$this->userId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'activity-notification.received';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'type' => $this->type->value,
            'table_id' => $this->type->tableId(),
        ];
    }
}
