<?php

namespace App\Support\Settings\Http\Controllers\Web;

use App\Http\Controllers\Web\Concerns\ConsumesApi;
use App\Http\Controllers\Web\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    use ConsumesApi;

    public function index(): View
    {
        return $this->webView('settings.index');
    }

    public function platform(): View|RedirectResponse
    {
        return $this->groupForm('platform', 'settings.platform');
    }

    public function fileHosting(): View|RedirectResponse
    {
        return $this->groupForm('file-hosting', 'settings.file-hosting');
    }

    public function microstock(): View|RedirectResponse
    {
        return $this->groupForm('microstock', 'settings.microstock');
    }

    public function vtual(): View|RedirectResponse
    {
        return $this->groupForm('vtual', 'settings.vtual');
    }

    public function update(Request $request, string $group): RedirectResponse
    {
        $response = $this->api()->put("admin/settings/{$group}", $request->all());

        $route = match ($group) {
            'platform' => 'settings.platform',
            'file-hosting' => 'settings.file-hosting',
            'microstock' => 'settings.microstock',
            'vtual' => 'settings.vtual',
            default => 'settings.index',
        };

        if (! $response->successful()) {
            return redirect()->route($route)->with('error', $this->apiMessage($response));
        }

        return redirect()->route($route)->with('success', __('settings.updated'));
    }

    private function groupForm(string $group, string $view): View|RedirectResponse
    {
        $response = $this->api()->get("admin/settings/{$group}");

        if (! $response->successful()) {
            return redirect()->route('settings.index')->with('error', $this->apiMessage($response));
        }

        return $this->webView($view, [
            'group' => $group,
            'settings' => $response->json('data.settings', []),
        ]);
    }
}
