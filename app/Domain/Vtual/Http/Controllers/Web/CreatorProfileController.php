<?php

namespace App\Domain\Vtual\Http\Controllers\Web;

use App\Http\Controllers\Web\Concerns\ConsumesApi;
use App\Http\Controllers\Web\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * My vTuber profiles list + manage (ConsumesApi → /api/v1/vtual/*).
 */
class CreatorProfileController extends Controller
{
    use ConsumesApi;

    public function index(): View|RedirectResponse
    {
        $creators = $this->api()->get('vtual/creators');

        if (! $creators->successful()) {
            return redirect()->route('vtual.index')->with('error', $this->apiMessage($creators));
        }

        return $this->webView('vtual.profiles.index', [
            'profiles' => collect($creators->json('data', [])),
        ]);
    }

    public function show(string $creator): View|RedirectResponse
    {
        $response = $this->api()->get('vtual/creators/'.$creator);

        if (! $response->successful()) {
            return redirect()->route('vtual.profiles.index')->with('error', $this->apiMessage($response));
        }

        $channels = $this->api()->get('vtual/creators/'.$creator.'/channels');
        $channelClaims = $this->api()->get('vtual/creators/'.$creator.'/channel-claims');
        $links = $this->api()->get('vtual/creators/'.$creator.'/links');
        $platforms = $this->api()->get('vtual/platforms');

        $data = $response->json('data', []);
        $linkRows = $links->successful()
            ? collect($links->json('data', []))
            : collect($data['external_links'] ?? []);

        $canManageAny = auth()->user()?->can('manage any vtual resource') ?? false;

        return $this->webView('vtual.profiles.show', [
            'creator' => $data,
            'channels' => $channels->successful() ? collect($channels->json('data', [])) : collect(),
            'channelClaims' => $channelClaims->successful() ? collect($channelClaims->json('data', [])) : collect(),
            'links' => $linkRows,
            'platforms' => $platforms->successful() ? collect($platforms->json('data', [])) : collect(),
            'canMutate' => in_array($data['my_role'] ?? null, ['owner', 'editor'], true) || $canManageAny,
            'canAcceptAnyway' => $canManageAny,
        ]);
    }

    public function update(Request $request, string $creator): RedirectResponse
    {
        $response = $this->api()->patch('vtual/creators/'.$creator, $this->apiPayload($request, [
            'display_name',
            'slug',
            'bio',
            'avatar_url',
        ]));

        if (! $response->successful()) {
            return redirect()->route('vtual.profiles.show', $creator)->with('error', $this->apiMessage($response));
        }

        return redirect()->route('vtual.profiles.show', $creator)->with('success', $this->apiMessage($response));
    }

    public function storeChannel(Request $request, string $creator): RedirectResponse
    {
        // Starts channel claim (pending + token); does not bind active channel yet.
        $response = $this->api()->post('vtual/creators/'.$creator.'/channels', $this->apiPayload($request, [
            'platform_key',
            'profile',
        ]));

        if (! $response->successful()) {
            return redirect()->route('vtual.profiles.show', $creator)->with('error', $this->apiMessage($response));
        }

        return redirect()->route('vtual.profiles.show', $creator)->with('success', $this->apiMessage($response));
    }

    public function verifyChannelClaim(string $creator, string $claim): RedirectResponse
    {
        $response = $this->api()->post('vtual/channel-claims/'.$claim.'/verify');

        if (! $response->successful()) {
            return redirect()->route('vtual.profiles.show', $creator)->with('error', $this->apiMessage($response));
        }

        return redirect()->route('vtual.profiles.show', $creator)->with('success', $this->apiMessage($response));
    }

    public function cancelChannelClaim(string $creator, string $claim): RedirectResponse
    {
        $response = $this->api()->post('vtual/channel-claims/'.$claim.'/cancel');

        if (! $response->successful()) {
            return redirect()->route('vtual.profiles.show', $creator)->with('error', $this->apiMessage($response));
        }

        return redirect()->route('vtual.profiles.show', $creator)->with('success', $this->apiMessage($response));
    }

    public function acceptAnywayChannelClaim(string $creator, string $claim): RedirectResponse
    {
        $response = $this->api()->post('vtual/channel-claims/'.$claim.'/accept-anyway');

        if (! $response->successful()) {
            return redirect()->route('vtual.profiles.show', $creator)->with('error', $this->apiMessage($response));
        }

        return redirect()->route('vtual.profiles.show', $creator)->with('success', $this->apiMessage($response));
    }

    public function refreshChannel(string $creator, string $channel): RedirectResponse
    {
        $response = $this->api()->post('vtual/channels/'.$channel.'/refresh');

        if (! $response->successful()) {
            return redirect()->route('vtual.profiles.show', $creator)->with('error', $this->apiMessage($response));
        }

        return redirect()->route('vtual.profiles.show', $creator)->with('success', $this->apiMessage($response));
    }

    public function archiveChannel(string $creator, string $channel): RedirectResponse
    {
        $response = $this->api()->post('vtual/channels/'.$channel.'/archive');

        if (! $response->successful()) {
            return redirect()->route('vtual.profiles.show', $creator)->with('error', $this->apiMessage($response));
        }

        return redirect()->route('vtual.profiles.show', $creator)->with('success', $this->apiMessage($response));
    }

    public function storeLink(Request $request, string $creator): RedirectResponse
    {
        $response = $this->api()->post('vtual/creators/'.$creator.'/links', $this->apiPayload($request, [
            'label',
            'url',
            'kind',
            'sort_order',
        ]));

        if (! $response->successful()) {
            return redirect()->route('vtual.profiles.show', $creator)->with('error', $this->apiMessage($response));
        }

        return redirect()->route('vtual.profiles.show', $creator)->with('success', $this->apiMessage($response));
    }

    public function updateLink(Request $request, string $creator, string $link): RedirectResponse
    {
        $response = $this->api()->patch('vtual/links/'.$link, $this->apiPayload($request, [
            'label',
            'url',
            'kind',
            'sort_order',
        ]));

        if (! $response->successful()) {
            return redirect()->route('vtual.profiles.show', $creator)->with('error', $this->apiMessage($response));
        }

        return redirect()->route('vtual.profiles.show', $creator)->with('success', $this->apiMessage($response));
    }

    public function destroyLink(string $creator, string $link): RedirectResponse
    {
        $response = $this->api()->delete('vtual/links/'.$link);

        if (! $response->successful()) {
            return redirect()->route('vtual.profiles.show', $creator)->with('error', $this->apiMessage($response));
        }

        return redirect()->route('vtual.profiles.show', $creator)->with('success', $this->apiMessage($response));
    }
}
