<?php

namespace App\Domain\Vtual\Http\Controllers\Web;

use App\Http\Controllers\Web\Concerns\ConsumesApi;
use App\Http\Controllers\Web\Controller;
use Illuminate\Http\JsonResponse;
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
        $archivedChannels = $this->api()->get('vtual/creators/'.$creator.'/channels/archived');
        $channelClaims = $this->api()->get('vtual/creators/'.$creator.'/channel-claims');
        $links = $this->api()->get('vtual/creators/'.$creator.'/links');
        $platforms = $this->api()->get('vtual/platforms');
        $allProfiles = $this->api()->get('vtual/creators');

        $data = $response->json('data', []);
        $linkRows = $links->successful()
            ? collect($links->json('data', []))
            : collect($data['external_links'] ?? []);

        $canManageAny = auth()->user()?->can('manage any vtual resource') ?? false;
        $canMerge = ($data['my_role'] ?? null) === 'owner' || $canManageAny;

        $mergeTargets = collect();
        if ($canMerge && $allProfiles->successful()) {
            $mergeTargets = collect($allProfiles->json('data', []))
                ->filter(fn (array $p) => ($p['id'] ?? '') !== $creator
                    && ($p['status'] ?? '') === 'active'
                    && (($p['my_role'] ?? null) === 'owner' || $canManageAny))
                ->values();
        }

        return $this->webView('vtual.profiles.show', [
            'creator' => $data,
            'channels' => $channels->successful() ? collect($channels->json('data', [])) : collect(),
            'archivedChannels' => $archivedChannels->successful()
                ? collect($archivedChannels->json('data', []))
                : collect(),
            'channelClaims' => $channelClaims->successful() ? collect($channelClaims->json('data', [])) : collect(),
            'links' => $linkRows,
            'platforms' => $platforms->successful() ? collect($platforms->json('data', [])) : collect(),
            'canMutate' => in_array($data['my_role'] ?? null, ['owner', 'editor'], true) || $canManageAny,
            'canAcceptAnyway' => $canManageAny,
            'canMerge' => $canMerge,
            'mergeTargets' => $mergeTargets,
        ]);
    }

    public function merge(Request $request, string $creator): RedirectResponse|JsonResponse
    {
        $response = $this->api()->post('vtual/creators/'.$creator.'/merge', $this->apiPayload($request, [
            'destination_id',
        ]));

        return $this->respondFromApi(
            $request,
            $response,
            function ($resp) use ($creator) {
                $destinationId = (string) ($resp->json('data.id') ?? request()->input('destination_id'));

                return redirect()
                    ->route('vtual.profiles.show', $destinationId !== '' ? $destinationId : $creator)
                    ->with('success', $this->apiMessage($resp, __('web/vtual.ajax.merge_done')));
            },
            __('web/vtual.ajax.merge_done'),
        );
    }

    public function update(Request $request, string $creator): RedirectResponse|JsonResponse
    {
        $response = $this->api()->patch('vtual/creators/'.$creator, $this->apiPayload($request, [
            'display_name',
            'slug',
            'bio',
            'avatar_url',
        ]));

        return $this->respondFromApi(
            $request,
            $response,
            fn () => redirect()->route('vtual.profiles.show', $creator)
                ->with('success', $this->apiMessage($response, __('web/vtual.ajax.profile_saved'))),
            __('web/vtual.ajax.profile_saved'),
            stayOnPageOnSuccess: true,
        );
    }

    public function storeChannel(Request $request, string $creator): RedirectResponse|JsonResponse
    {
        // Staff (manage any): instant active channel. Others: pending bio claim.
        $response = $this->api()->post('vtual/creators/'.$creator.'/channels', $this->apiPayload($request, [
            'platform_key',
            'profile',
        ]));

        return $this->respondFromApi(
            $request,
            $response,
            fn () => redirect()->route('vtual.profiles.show', $creator)
                ->with('success', $this->apiMessage($response, __('web/vtual.ajax.channel_added'))),
            __('web/vtual.ajax.channel_added'),
            stayOnPageOnSuccess: true,
        );
    }

    public function verifyChannelClaim(Request $request, string $creator, string $claim): RedirectResponse|JsonResponse
    {
        $response = $this->api()->post('vtual/channel-claims/'.$claim.'/verify');

        return $this->respondFromApi(
            $request,
            $response,
            fn () => redirect()->route('vtual.profiles.show', $creator)
                ->with('success', $this->apiMessage($response, __('web/vtual.ajax.claim_verified'))),
            __('web/vtual.ajax.claim_verified'),
            stayOnPageOnSuccess: true,
        );
    }

    public function cancelChannelClaim(Request $request, string $creator, string $claim): RedirectResponse|JsonResponse
    {
        $response = $this->api()->post('vtual/channel-claims/'.$claim.'/cancel');

        return $this->respondFromApi(
            $request,
            $response,
            fn () => redirect()->route('vtual.profiles.show', $creator)
                ->with('success', $this->apiMessage($response, __('web/vtual.ajax.claim_cancelled'))),
            __('web/vtual.ajax.claim_cancelled'),
            stayOnPageOnSuccess: true,
        );
    }

    public function acceptAnywayChannelClaim(Request $request, string $creator, string $claim): RedirectResponse|JsonResponse
    {
        $response = $this->api()->post('vtual/channel-claims/'.$claim.'/accept-anyway');

        return $this->respondFromApi(
            $request,
            $response,
            fn () => redirect()->route('vtual.profiles.show', $creator)
                ->with('success', $this->apiMessage($response, __('web/vtual.ajax.claim_accepted'))),
            __('web/vtual.ajax.claim_accepted'),
            stayOnPageOnSuccess: true,
        );
    }

    public function refreshChannel(Request $request, string $creator, string $channel): RedirectResponse|JsonResponse
    {
        $response = $this->api()->post('vtual/channels/'.$channel.'/refresh');

        return $this->respondFromApi(
            $request,
            $response,
            fn () => redirect()->route('vtual.profiles.show', $creator)
                ->with('success', $this->apiMessage($response, __('web/vtual.ajax.channel_refreshed'))),
            __('web/vtual.ajax.channel_refreshed'),
            stayOnPageOnSuccess: true,
        );
    }

    public function archiveChannel(Request $request, string $creator, string $channel): RedirectResponse|JsonResponse
    {
        $response = $this->api()->post('vtual/channels/'.$channel.'/archive');

        return $this->respondFromApi(
            $request,
            $response,
            fn () => redirect()->route('vtual.profiles.show', $creator)
                ->with('success', $this->apiMessage($response, __('web/vtual.ajax.channel_archived'))),
            __('web/vtual.ajax.channel_archived'),
            stayOnPageOnSuccess: true,
        );
    }

    public function restoreChannel(Request $request, string $creator, string $channel): RedirectResponse|JsonResponse
    {
        $response = $this->api()->post('vtual/channels/'.$channel.'/restore');

        return $this->respondFromApi(
            $request,
            $response,
            fn () => redirect()->route('vtual.profiles.show', $creator)
                ->with('success', $this->apiMessage($response, __('web/vtual.ajax.channel_restored'))),
            __('web/vtual.ajax.channel_restored'),
            stayOnPageOnSuccess: true,
        );
    }

    public function storeLink(Request $request, string $creator): RedirectResponse|JsonResponse
    {
        $response = $this->api()->post('vtual/creators/'.$creator.'/links', $this->apiPayload($request, [
            'label',
            'url',
            'kind',
            'sort_order',
        ]));

        return $this->respondFromApi(
            $request,
            $response,
            fn () => redirect()->route('vtual.profiles.show', $creator)
                ->with('success', $this->apiMessage($response, __('web/vtual.ajax.link_added'))),
            __('web/vtual.ajax.link_added'),
            stayOnPageOnSuccess: true,
        );
    }

    public function updateLink(Request $request, string $creator, string $link): RedirectResponse|JsonResponse
    {
        $response = $this->api()->patch('vtual/links/'.$link, $this->apiPayload($request, [
            'label',
            'url',
            'kind',
            'sort_order',
        ]));

        return $this->respondFromApi(
            $request,
            $response,
            fn () => redirect()->route('vtual.profiles.show', $creator)
                ->with('success', $this->apiMessage($response, __('web/vtual.ajax.link_updated'))),
            __('web/vtual.ajax.link_updated'),
            stayOnPageOnSuccess: true,
        );
    }

    public function destroyLink(Request $request, string $creator, string $link): RedirectResponse|JsonResponse
    {
        $response = $this->api()->delete('vtual/links/'.$link);

        return $this->respondFromApi(
            $request,
            $response,
            fn () => redirect()->route('vtual.profiles.show', $creator)
                ->with('success', $this->apiMessage($response, __('web/vtual.ajax.link_deleted'))),
            __('web/vtual.ajax.link_deleted'),
            stayOnPageOnSuccess: true,
        );
    }
}
