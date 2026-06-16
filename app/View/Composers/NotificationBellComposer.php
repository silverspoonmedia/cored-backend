<?php

namespace App\View\Composers;

use App\Models\User;
use App\Support\Broadcasting\BroadcastConnection;
use App\Support\Web\WebLayout;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Supplies the navbar notification bell with delivery mode + websocket client config.
 */
class NotificationBellComposer
{
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
                    'read' => url('notifications').'/',
                ],
                'labels' => [
                    'empty' => __('web/notifications.empty'),
                    'header' => __('web/notifications.header_count'),
                ],
                'realtime' => $realtime,
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
