<?php

namespace App\Domain\Notification\Http\Controllers\Web;

use App\Http\Controllers\Web\Concerns\ConsumesApi;
use App\Http\Controllers\Web\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationAudienceController extends Controller
{
    use ConsumesApi;

    public function index(): View|RedirectResponse
    {
        $response = $this->api()->get('admin/notification-audiences');

        if (! $response->successful()) {
            return redirect()->route('settings.index')->with('error', $this->apiMessage($response));
        }

        return $this->webView('notification-audiences.index', [
            'items' => $response->json('data.items', []),
            'assignableRoles' => $response->json('data.assignable_roles', []),
        ]);
    }

    public function edit(string $type): View|RedirectResponse
    {
        $response = $this->api()->get("admin/notification-audiences/{$type}");

        if (! $response->successful()) {
            return redirect()->route('notification-audiences.index')->with('error', $this->apiMessage($response));
        }

        $listResponse = $this->api()->get('admin/notification-audiences');
        $roles = $listResponse->successful()
            ? $listResponse->json('data.assignable_roles', [])
            : [];

        return $this->webView('notification-audiences.edit', [
            'audience' => $response->json('data', []),
            'assignableRoles' => $roles,
        ]);
    }

    public function update(Request $request, string $type): RedirectResponse
    {
        $response = $this->api()->put("admin/notification-audiences/{$type}", [
            'notify_subject_user' => $request->boolean('notify_subject_user'),
            'roles' => $request->input('roles', []),
            'users' => $request->input('users', []),
        ]);

        if (! $response->successful()) {
            return redirect()->route('notification-audiences.edit', $type)
                ->with('error', $this->apiMessage($response));
        }

        return redirect()->route('notification-audiences.index')
            ->with('success', __('api/notifications.audiences.updated'));
    }
}
