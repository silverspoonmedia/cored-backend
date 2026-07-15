<?php

namespace App\Domain\Notification\Http\Controllers\Web;

use App\Http\Controllers\Web\Controller;

use App\Http\Controllers\Web\Concerns\ConsumesApi;
use App\Support\Broadcasting\BroadcastConnection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

/**
 * Web notifications — a pure consumer of the versioned API.
 *
 * No business logic lives here: every action forwards to
 * `/api/v1/notifications` (see {@see \App\Domain\Notification\Http\Controllers\API\V1\NotificationController})
 * via {@see ConsumesApi}. Role-based visibility and read state are owned by the
 * API. The navbar bell uses polling or realtime websocket delivery depending on
 * {@see BroadcastConnection}; see docs/domains/notification/README.md.
 */
class NotificationController extends Controller
{
    use ConsumesApi;

    /**
     * Full notifications page.
     */
    public function index(): View|RedirectResponse
    {
        $response = $this->api()->get('notifications', ['limit' => 100]);

        if (! $response->successful()) {
            return redirect()->route('dashboard')->with('error', $this->apiMessage($response));
        }

        return $this->webView('notifications.index', [
            'notifications' => $this->withWebUrls($response->json('data', [])),
        ]);
    }

    /**
     * Unread badge count (polled by the bell).
     */
    public function unreadCount(): JsonResponse
    {
        $response = $this->api()->get('notifications/unread-count');

        if (! $response->successful()) {
            return response()->json(['unread' => 0]);
        }

        return response()->json(['unread' => (int) $response->json('data.unread', 0)]);
    }

    /**
     * Recent notifications for the bell dropdown.
     */
    public function recent(): JsonResponse
    {
        $response = $this->api()->get('notifications', ['limit' => 10]);

        if (! $response->successful()) {
            return response()->json(['data' => []]);
        }

        return response()->json(['data' => $this->withWebUrls($response->json('data', []))]);
    }

    public function markAsRead(Request $request, string $notification): RedirectResponse|JsonResponse
    {
        $response = $this->api()->post("notifications/{$notification}/read");

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => back()->with('success', __('api/notifications.marked_read')),
            __('api/notifications.marked_read'),
            stayOnPageOnSuccess: true,
        );
    }

    public function markAllAsRead(Request $request): RedirectResponse|JsonResponse
    {
        $response = $this->api()->post('notifications/read-all');

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => back()->with('success', __('api/notifications.marked_all_read')),
            __('api/notifications.marked_all_read'),
        );
    }

    /**
     * Resolve each notification's deep link (a registered route name + params)
     * into a clickable web URL. Unregistered/empty routes degrade to null.
     *
     * @param  list<array<string, mixed>>  $notifications
     * @return list<array<string, mixed>>
     */
    private function withWebUrls(array $notifications): array
    {
        return array_map(function (array $n): array {
            $route = $n['action_route'] ?? null;
            $params = $n['action_params'] ?? [];

            $n['url'] = is_string($route) && $route !== '' && Route::has($route)
                ? route($route, $params)
                : null;

            return $n;
        }, $notifications);
    }
}
