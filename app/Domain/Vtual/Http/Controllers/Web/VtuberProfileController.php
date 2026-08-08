<?php

namespace App\Domain\Vtual\Http\Controllers\Web;

use App\Domain\Identity\Services\DiscordOAuthService;
use App\Domain\Identity\Services\UserOAuthAccountService;
use App\Domain\Vtual\Support\VtualClaimRealtime;
use App\Http\Controllers\Web\Concerns\ConsumesApi;
use App\Http\Controllers\Web\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Read-only vTuber profile — consumer of `/api/v1/vtual/vtubers/{creator}`.
 */
class VtuberProfileController extends Controller
{
    use ConsumesApi;

    public function index(): View
    {
        return $this->webView('vtual.vtuber.index');
    }

    public function show(string $creator): View|RedirectResponse
    {
        $result = $this->resolveProfile($creator);

        if ($result instanceof RedirectResponse) {
            return $result;
        }

        return $this->webView('vtual.vtuber.show', [
            'profile' => $result,
        ]);
    }

    public function claim(string $creator): View|RedirectResponse
    {
        $result = $this->resolveProfile($creator, canonicalRoute: 'vtual.vtuber.claim');

        if ($result instanceof RedirectResponse) {
            return $result;
        }

        if (empty($result['is_claimable'])) {
            return redirect()->route('vtual.vtuber.show', $result['slug'] ?? $creator);
        }

        $activeClaim = $this->activeClaimForCreator($result['id'] ?? null);
        $claimRealtime = app(VtualClaimRealtime::class);
        $broadcast = $claimRealtime->clientConfig();
        $oauthAccounts = app(UserOAuthAccountService::class);
        $discordOAuth = app(DiscordOAuthService::class);
        $user = Auth::guard('api')->user();
        $discordVerifySetting = (bool) setting_config('vtual.claim.discord_verify_enabled', false);
        $canAcceptAnyway = $user !== null
            && $user->can('manage any vtual resource')
            && (bool) setting_config('vtual.claim.accept_anyway_enabled', false);

        return $this->webView('vtual.vtuber.claim', [
            'profile' => $result,
            'activeClaim' => $activeClaim,
            'claimRealtime' => [
                'enabled' => $claimRealtime->enabled(),
                'broadcast' => $broadcast,
                'claimId' => $activeClaim['id'] ?? null,
                'userId' => Auth::guard('api')->id(),
            ],
            'profileUrl' => $result['profile_url'] ?? route('vtual.vtuber.show', $result['slug'] ?? $creator),
            'discordConnected' => $user !== null && $oauthAccounts->hasDiscordConnection($user),
            'discordVerifyEnabled' => $discordVerifySetting && $discordOAuth->isConfigured(),
            'canAcceptAnyway' => $canAcceptAnyway,
        ]);
    }

    public function startClaim(Request $request, string $creator): RedirectResponse|JsonResponse
    {
        $profile = $this->resolveProfileOrAbort($creator);

        $response = $this->api()->post('vtual/claims', $this->apiPayload($request, [
            'creator_id',
            'channel_id',
        ], [
            'creator_id' => $profile['id'] ?? '',
        ]));

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => redirect()
                ->route('vtual.vtuber.claim', $profile['slug'] ?? $creator)
                ->with('success', $this->apiMessage($response, __('web/vtual.ajax.claim_started'))),
            __('web/vtual.ajax.claim_started'),
            stayOnPageOnSuccess: true,
        );
    }

    public function verifyClaim(Request $request, string $creator): RedirectResponse|JsonResponse
    {
        $profile = $this->resolveProfileOrAbort($creator);
        $claimId = (string) $request->input('claim_id', '');

        if ($claimId === '') {
            abort(422, __('web/vtual-vtuber.claim_missing_active'));
        }

        $response = $this->api()->post('vtual/claims/'.$claimId.'/verify');

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => redirect()
                ->route('vtual.vtuber.claim', $profile['slug'] ?? $creator)
                ->with('success', $this->apiMessage($response, __('web/vtual-vtuber.claim_verify_queued'))),
            __('web/vtual-vtuber.claim_verify_queued'),
            stayOnPageOnSuccess: true,
        );
    }

    public function discordClaimChannel(Request $request, string $creator): RedirectResponse|JsonResponse
    {
        $profile = $this->resolveProfileOrAbort($creator);
        $channelId = (string) $request->input('channel_id', '');
        $slug = (string) ($profile['slug'] ?? $creator);

        if ($channelId === '') {
            abort(422, __('web/vtual-vtuber.claim_missing_channel'));
        }

        $oauthAccounts = app(UserOAuthAccountService::class);
        if (! $oauthAccounts->hasDiscordConnection($request->user())) {
            return redirect()->route('auth.discord.redirect', [
                'intent' => 'claim_channel',
                'slug' => $slug,
                'channel_id' => $channelId,
            ]);
        }

        $startResponse = $this->api()->post('vtual/claims', [
            'creator_id' => $profile['id'] ?? '',
            'channel_id' => $channelId,
        ]);

        if (! $startResponse->successful()) {
            return $this->respondFromApi(
                $request,
                $startResponse,
                fn (): RedirectResponse => back(),
                __('web/vtual.ajax.claim_started'),
            );
        }

        $claimId = (string) ($startResponse->json('data.id') ?? '');
        $verifyResponse = $this->api()->post('vtual/claims/'.$claimId.'/verify-discord');

        return $this->respondFromApi(
            $request,
            $verifyResponse,
            function () use ($verifyResponse, $slug): RedirectResponse {
                if (($verifyResponse->json('data.status') ?? '') === 'verified') {
                    return redirect()->route('vtual.vtuber.show', $slug)
                        ->with('success', __('web/vtual-vtuber.claim_progress_verified'));
                }

                return redirect()->route('vtual.vtuber.claim', $slug)
                    ->with('warning', __('web/vtual-vtuber.claim_progress_failed'));
            },
            __('web/vtual-vtuber.claim_discord_verified'),
            stayOnPageOnSuccess: true,
        );
    }

    public function discordVerifyClaim(Request $request, string $creator): RedirectResponse|JsonResponse
    {
        $profile = $this->resolveProfileOrAbort($creator);
        $claimId = (string) $request->input('claim_id', '');
        $slug = (string) ($profile['slug'] ?? $creator);

        if ($claimId === '') {
            abort(422, __('web/vtual-vtuber.claim_missing_active'));
        }

        $oauthAccounts = app(UserOAuthAccountService::class);
        if (! $oauthAccounts->hasDiscordConnection($request->user())) {
            return redirect()->route('auth.discord.redirect', [
                'intent' => 'claim_verify',
                'slug' => $slug,
                'claim_id' => $claimId,
            ]);
        }

        $response = $this->api()->post('vtual/claims/'.$claimId.'/verify-discord');

        return $this->respondFromApi(
            $request,
            $response,
            function () use ($response, $slug): RedirectResponse {
                if (($response->json('data.status') ?? '') === 'verified') {
                    return redirect()->route('vtual.vtuber.show', $slug)
                        ->with('success', __('web/vtual-vtuber.claim_progress_verified'));
                }

                return redirect()->route('vtual.vtuber.claim', $slug)
                    ->with('warning', __('web/vtual-vtuber.claim_progress_failed'));
            },
            __('web/vtual-vtuber.claim_discord_verified'),
            stayOnPageOnSuccess: true,
        );
    }

    public function cancelClaim(Request $request, string $creator): RedirectResponse|JsonResponse
    {
        $profile = $this->resolveProfileOrAbort($creator);
        $claimId = (string) $request->input('claim_id', '');

        if ($claimId === '') {
            abort(422, __('web/vtual-vtuber.claim_missing_active'));
        }

        $response = $this->api()->post('vtual/claims/'.$claimId.'/cancel');

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => redirect()
                ->route('vtual.vtuber.claim', $profile['slug'] ?? $creator)
                ->with('success', $this->apiMessage($response, __('web/vtual.ajax.claim_cancelled'))),
            __('web/vtual.ajax.claim_cancelled'),
            stayOnPageOnSuccess: true,
        );
    }

    public function acceptAnywayClaim(Request $request, string $creator): RedirectResponse|JsonResponse
    {
        $profile = $this->resolveProfileOrAbort($creator);
        $claimId = (string) $request->input('claim_id', '');

        if ($claimId === '') {
            abort(422, __('web/vtual-vtuber.claim_missing_active'));
        }

        $response = $this->api()->post('vtual/claims/'.$claimId.'/accept-anyway');

        if (! $response->successful()) {
            return $this->respondFromApi(
                $request,
                $response,
                fn (): RedirectResponse => redirect()
                    ->route('vtual.vtuber.claim', $profile['slug'] ?? $creator)
                    ->with('warning', $this->apiMessage($response, __('web/vtual.claims.accept_anyway_help'))),
                __('web/vtual.claims.accept_anyway_help'),
                stayOnPageOnSuccess: true,
            );
        }

        return $this->respondFromApi(
            $request,
            $response,
            fn (): RedirectResponse => redirect()
                ->route('vtual.vtuber.show', $profile['slug'] ?? $creator)
                ->with('success', __('web/vtual-vtuber.claim_progress_verified')),
            __('web/vtual-vtuber.claim_progress_verified'),
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function activeClaimForCreator(?string $creatorId): ?array
    {
        if ($creatorId === null || $creatorId === '') {
            return null;
        }

        $mine = $this->api()->get('vtual/claims/mine');
        if (! $mine->successful()) {
            return null;
        }

        return collect($mine->json('data', []))
            ->first(fn (array $row): bool => ($row['creator_id'] ?? '') === $creatorId);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveProfileOrAbort(string $creator): array
    {
        $result = $this->resolveProfile($creator, canonicalRoute: 'vtual.vtuber.claim');

        if ($result instanceof RedirectResponse) {
            abort(404);
        }

        return $result;
    }

    /**
     * @return array<string, mixed>|RedirectResponse
     */
    private function resolveProfile(string $creator, string $canonicalRoute = 'vtual.vtuber.show'): array|RedirectResponse
    {
        $response = $this->api()->get('vtual/vtubers/'.$creator);

        if (! $response->successful()) {
            abort($response->status() >= 400 ? $response->status() : 404, $this->apiMessage($response));
        }

        $profile = $response->json('data', []);

        if (Str::isUuid($creator) && ! empty($profile['slug']) && $profile['slug'] !== $creator) {
            return redirect()->route($canonicalRoute, $profile['slug'], 301);
        }

        return $profile;
    }
}
