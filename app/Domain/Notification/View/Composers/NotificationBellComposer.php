<?php

namespace App\Domain\Notification\View\Composers;

use App\Domain\Identity\Models\User;
use App\Domain\Notification\Services\NotificationService;
use App\Support\Broadcasting\BroadcastConnection;
use App\Support\Web\WebLayout;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Supplies the navbar notification bell with delivery mode + websocket client config.
 */
class NotificationBellComposer
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    public function compose(View $view): void
    {
        $user = Auth::guard('api')->user();
        $realtime = BroadcastConnection::usesRealtimeNotifications();
        $broadcast = BroadcastConnection::clientConfig();
        $userId = $user instanceof User ? $user->id : null;

        $bellConfig = null;

        if ($user instanceof User && $user->can('view notification')) {
            $bellConfig = [
                'urls' => [
                    'unread' => route('notifications.unread-count'),
                    'recent' => route('notifications.recent'),
                    // bell.js appends `{id}/read` — base must include trailing slash under /apps.
                    'read' => rtrim(route('notifications.index'), '/').'/',
                ],
                'labels' => [
                    'empty' => __('web/notifications.empty'),
                    'header' => __('web/notifications.header_count'),
                    'badge' => __('web/notifications.unread_count_label'),
                ],
                // Bootstrap badge state in the rendered page. Realtime events then
                // carry absolute counts, so a healthy WS needs no unread-count HTTP.
                'initialUnreadCount' => $this->notifications->unreadCount($user),
                'realtime' => $realtime,
                'pollingIntervalSeconds' => max(1, (int) setting_config('notifications.polling_interval')),
                'userId' => $userId,
                'broadcast' => $broadcast,
            ];
        }

        $view->with([
            'notificationRealtime' => $realtime,
            'notificationBroadcast' => $broadcast,
            'notificationUserId' => $userId,
            'notificationBellConfig' => $bellConfig,
        ]);
    }

    public static function viewName(): string
    {
        return WebLayout::navbarComposerView();
    }
}
