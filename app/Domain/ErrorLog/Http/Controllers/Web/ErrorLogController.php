<?php

namespace App\Domain\ErrorLog\Http\Controllers\Web;

use App\Http\Controllers\Web\Concerns\ConsumesApi;
use App\Http\Controllers\Web\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Web error log — pure consumer of the versioned API.
 *
 * Grouped persistent application errors. Distinct from activity log (audit).
 */
class ErrorLogController extends Controller
{
    use ConsumesApi;

    public function index(Request $request): View|RedirectResponse
    {
        $query = array_filter([
            'limit' => 100,
            'status' => $request->query('status'),
            'level' => $request->query('level'),
            'type' => $request->query('type'),
            'q' => $request->query('q'),
        ], static fn ($v) => $v !== null && $v !== '');

        $response = $this->api()->get('error-logs', $query);

        if (! $response->successful()) {
            return redirect()->route('dashboard')->with('error', $this->apiMessage($response));
        }

        return $this->webView('error-logs.index', [
            'entries' => $response->json('data', []),
            'filters' => $query,
        ]);
    }

    public function show(string $errorLog): View|RedirectResponse
    {
        $response = $this->api()->get("error-logs/{$errorLog}");

        if (! $response->successful()) {
            return redirect()->route('error-logs.index')->with('error', $this->apiMessage($response));
        }

        return $this->webView('error-logs.show', [
            'entry' => $response->json('data', []),
        ]);
    }

    public function updateStatus(Request $request, string $errorLog): RedirectResponse
    {
        $response = $this->api()->patch("error-logs/{$errorLog}/status", [
            'status' => $request->input('status'),
        ]);

        if (! $response->successful()) {
            return redirect()
                ->route('error-logs.show', $errorLog)
                ->with('error', $this->apiMessage($response));
        }

        return redirect()
            ->route('error-logs.show', $errorLog)
            ->with('success', $this->apiMessage($response) ?: __('web/error-log.status_updated'));
    }
}
