<?php

namespace App\Domain\ActivityLog\Http\Controllers\Web;

use App\Http\Controllers\Web\Controller;

use App\Http\Controllers\Web\Concerns\ConsumesApi;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Web activity log — a pure consumer of the versioned API.
 *
 * Immutable audit entries from Spatie laravel-activitylog. Distinct from
 * {@see NotificationController} (in-app bell).
 */
class ActivityLogController extends Controller
{
    use ConsumesApi;

    public function index(): View|RedirectResponse
    {
        $response = $this->api()->get('activity-logs', ['limit' => 100]);

        if (! $response->successful()) {
            return redirect()->route('dashboard')->with('error', $this->apiMessage($response));
        }

        return $this->webView('activity-logs.index', [
            'entries' => $response->json('data', []),
        ]);
    }

    public function show(int $activity): View|RedirectResponse
    {
        $response = $this->api()->get("activity-logs/{$activity}");

        if (! $response->successful()) {
            return redirect()->route('activity-logs.index')->with('error', $this->apiMessage($response));
        }

        return $this->webView('activity-logs.show', [
            'entry' => $response->json('data', []),
        ]);
    }
}
