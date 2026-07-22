<?php

namespace App\Domain\Vtual\Http\Controllers\Web;

use App\Http\Controllers\Web\Concerns\ConsumesApi;
use App\Http\Controllers\Web\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * vTual admin surface — pure consumer of `/api/v1/vtual/*`.
 */
class VtualController extends Controller
{
    use ConsumesApi;

    public function index(): View|RedirectResponse
    {
        $creators = $this->api()->get('vtual/creators');
        $managements = $this->api()->get('vtual/managements');
        $platforms = $this->api()->get('vtual/platforms');

        if (! $creators->successful()) {
            return redirect()->route('dashboard')->with('error', $this->apiMessage($creators));
        }

        return $this->webView('vtual.index', [
            'creators' => collect($creators->json('data', [])),
            'managements' => $managements->successful() ? collect($managements->json('data', [])) : collect(),
            'platforms' => $platforms->successful() ? collect($platforms->json('data', [])) : collect(),
        ]);
    }

    public function storeCreator(Request $request): RedirectResponse
    {
        $response = $this->api()->post('vtual/creators', $this->apiPayload($request, [
            'display_name',
            'slug',
            'bio',
            'avatar_url',
        ]));

        if (! $response->successful()) {
            return redirect()->route('vtual.index')->with('error', $this->apiMessage($response));
        }

        return redirect()->route('vtual.index')->with('success', $this->apiMessage($response));
    }

    public function storeManagement(Request $request): RedirectResponse
    {
        $response = $this->api()->post('vtual/managements', $this->apiPayload($request, [
            'name',
            'slug',
            'description',
            'logo_url',
        ]));

        if (! $response->successful()) {
            return redirect()->route('vtual.index')->with('error', $this->apiMessage($response));
        }

        return redirect()->route('vtual.index')->with('success', $this->apiMessage($response));
    }

    public function showCreator(string $creator): View|RedirectResponse
    {
        $response = $this->api()->get('vtual/creators/'.$creator);

        if (! $response->successful()) {
            return redirect()->route('vtual.index')->with('error', $this->apiMessage($response));
        }

        $channels = $this->api()->get('vtual/creators/'.$creator.'/channels');
        $platforms = $this->api()->get('vtual/platforms');

        return $this->webView('vtual.creators.show', [
            'creator' => $response->json('data', []),
            'channels' => $channels->successful() ? collect($channels->json('data', [])) : collect(),
            'platforms' => $platforms->successful() ? collect($platforms->json('data', [])) : collect(),
        ]);
    }

    public function storeChannel(Request $request, string $creator): RedirectResponse
    {
        $response = $this->api()->post('vtual/creators/'.$creator.'/channels', $this->apiPayload($request, [
            'platform_key',
            'external_id',
            'handle',
            'display_name',
            'url',
        ]));

        if (! $response->successful()) {
            return redirect()->route('vtual.creators.show', $creator)->with('error', $this->apiMessage($response));
        }

        return redirect()->route('vtual.creators.show', $creator)->with('success', $this->apiMessage($response));
    }
}
