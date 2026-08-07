<?php

namespace App\Domain\Vtual\Http\Controllers\Web;

use App\Domain\Identity\Services\DiscordOAuthService;
use App\Domain\Identity\Services\UserOAuthAccountService;
use App\Http\Controllers\Web\Concerns\ConsumesApi;
use App\Http\Controllers\Web\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Owned vTuber profiles — list + manage (ConsumesApi → /api/v1/vtual/*).
 */
class CreatorProfilesController extends Controller
{
    use ConsumesApi;

    public function index(): View
    {
        $response = $this->api()->get('vtual/creators/owned');

        if (! $response->successful()) {
            abort($response->status(), $this->apiMessage($response));
        }

        return $this->webView('vtual.profiles.index', [
            'profiles' => $response->json('data') ?? [],
        ]);
    }

    public function show(string $creator): View|RedirectResponse
    {
        $creatorId = $this->resolveCreatorId($creator);
        $response = $this->api()->get('vtual/creators/'.$creatorId);

        if (! $response->successful()) {
            return redirect()->route('vtual.profiles.index')->with('error', $this->apiMessage($response));
        }

        $data = $response->json('data', []);
        $slug = (string) ($data['slug'] ?? $creator);

        if (Str::isUuid($creator) && $slug !== '' && $slug !== $creator) {
            return redirect()->route('vtual.profiles.show', $slug, 301);
        }

        $channels = $this->api()->get('vtual/creators/'.$creatorId.'/channels');
        $archivedChannels = $this->api()->get('vtual/creators/'.$creatorId.'/channels/archived');
        $channelClaims = $this->api()->get('vtual/creators/'.$creatorId.'/channel-claims');
        $links = $this->api()->get('vtual/creators/'.$creatorId.'/links');
        $platforms = $this->api()->get('vtual/platforms');
        $allProfiles = $this->api()->get('vtual/creators');

        $linkRows = $links->successful()
            ? collect($links->json('data', []))
            : collect($data['external_links'] ?? []);

        $user = Auth::guard('api')->user();
        $canManageAny = $user?->can('manage any vtual resource') ?? false;
        $canMerge = ($data['my_role'] ?? null) === 'owner' || $canManageAny;

        $mergeTargets = collect();
        if ($canMerge && $allProfiles->successful()) {
            $mergeTargets = collect($allProfiles->json('data', []))
                ->filter(fn (array $p): bool => ($p['id'] ?? '') !== $creatorId
                    && ($p['status'] ?? '') === 'active'
                    && (($p['my_role'] ?? null) === 'owner' || $canManageAny))
                ->values();
        }

        $oauthAccounts = app(UserOAuthAccountService::class);
        $discordOAuth = app(DiscordOAuthService::class);
        $discordVerifySetting = (bool) setting_config(
            'vtual.channel_claim.discord_verify_enabled',
            setting_config('vtual.claim.discord_verify_enabled', false),
        );

        return $this->webView('vtual.profiles.show', [
            'creator' => $data,
            'creatorRouteKey' => $slug,
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
            'discordConnected' => $user !== null && $oauthAccounts->hasDiscordConnection($user),
            'discordVerifyEnabled' => $discordVerifySetting && $discordOAuth->isConfigured(),
        ]);
    }

    public function update(Request $request, string $creator): RedirectResponse|JsonResponse
    {
        $creatorId = $this->resolveCreatorId($creator);
        $response = $this->api()->patch('vtual/creators/'.$creatorId, $this->apiPayload($request, [
            'display_name',
            'slug',
            'bio',
            'avatar_url',
        ]));

        return $this->respondFromApi(
            $request,
            $response,
            fn () => redirect()->route('vtual.profiles.show', $this->routeKeyFromResponse($response, $creator))
                ->with('success', $this->apiMessage($response, __('web/vtual.ajax.profile_saved'))),
            __('web/vtual.ajax.profile_saved'),
            stayOnPageOnSuccess: true,
        );
    }

    public function merge(Request $request, string $creator): RedirectResponse|JsonResponse
    {
        $creatorId = $this->resolveCreatorId($creator);
        $response = $this->api()->post('vtual/creators/'.$creatorId.'/merge', $this->apiPayload($request, [
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

    public function storeChannel(Request $request, string $creator): RedirectResponse|JsonResponse
    {
        $creatorId = $this->resolveCreatorId($creator);
        $response = $this->api()->post('vtual/creators/'.$creatorId.'/channels', $this->apiPayload($request, [
            'platform_key',
            'profile',
        ]));

        return $this->respondFromApi(
            $request,
            $response,
            fn () => redirect()->route('vtual.profiles.show', $this->routeKeyFromResponse($response, $creator))
                ->with('success', $this->apiMessage($response, __('web/vtual.ajax.channel_added'))),
            __('web/vtual.ajax.channel_added'),
            stayOnPageOnSuccess: true,
        );
    }

    public function verifyChannelClaim(Request $request, string $creator, string $claim): RedirectResponse|JsonResponse
    {
        $this->resolveCreatorId($creator);
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

    public function verifyChannelClaimDiscord(Request $request, string $creator, string $claim): RedirectResponse|JsonResponse
    {
        $this->resolveCreatorId($creator);
        $response = $this->api()->post('vtual/channel-claims/'.$claim.'/verify-discord');

        return $this->respondFromApi(
            $request,
            $response,
            fn () => redirect()->route('vtual.profiles.show', $creator)
                ->with('success', $this->apiMessage($response, __('web/vtual.ajax.claim_verified'))),
            __('web/vtual-vtuber.claim_discord_verified'),
            stayOnPageOnSuccess: true,
        );
    }

    public function cancelChannelClaim(Request $request, string $creator, string $claim): RedirectResponse|JsonResponse
    {
        $this->resolveCreatorId($creator);
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
        $this->resolveCreatorId($creator);
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
        $this->resolveCreatorId($creator);
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
        $this->resolveCreatorId($creator);
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
        $this->resolveCreatorId($creator);
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

    public function promoteChannel(Request $request, string $creator, string $channel): RedirectResponse|JsonResponse
    {
        $this->resolveCreatorId($creator);
        $response = $this->api()->post('vtual/channels/'.$channel.'/promote');

        return $this->respondFromApi(
            $request,
            $response,
            fn () => redirect()->route('vtual.profiles.show', $creator)
                ->with('success', $this->apiMessage($response, __('web/vtual.ajax.channel_promoted'))),
            __('web/vtual.ajax.channel_promoted'),
            stayOnPageOnSuccess: true,
        );
    }

    public function storeLink(Request $request, string $creator): RedirectResponse|JsonResponse
    {
        $creatorId = $this->resolveCreatorId($creator);
        $response = $this->api()->post('vtual/creators/'.$creatorId.'/links', $this->apiPayload($request, [
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
        $this->resolveCreatorId($creator);
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
        $this->resolveCreatorId($creator);
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

    private function resolveCreatorId(string $creator): string
    {
        if (Str::isUuid($creator)) {
            return $creator;
        }

        $response = $this->api()->get('vtual/vtubers/'.$creator);

        if (! $response->successful()) {
            abort($response->status() >= 400 ? $response->status() : 404, $this->apiMessage($response));
        }

        $id = (string) ($response->json('data.id') ?? '');

        if ($id === '') {
            abort(404);
        }

        return $id;
    }

    /**
     * @param  \Illuminate\Http\Client\Response  $response
     */
    private function routeKeyFromResponse($response, string $fallback): string
    {
        $slug = (string) ($response->json('data.creator.slug') ?? $response->json('data.slug') ?? '');

        if ($slug !== '') {
            return $slug;
        }

        return $fallback;
    }
}
