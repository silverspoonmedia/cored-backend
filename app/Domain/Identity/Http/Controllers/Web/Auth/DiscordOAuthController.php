<?php

namespace App\Domain\Identity\Http\Controllers\Web\Auth;

use App\Domain\Identity\Exceptions\IdentityOAuthException;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\DiscordOAuthService;
use App\Domain\Identity\Services\UserOAuthAccountService;
use App\Domain\Identity\Support\DiscordOAuthState;
use App\Domain\Identity\Support\OAuthReturnUrl;
use App\Domain\Vtual\Enums\CreatorClaimStatus;
use App\Domain\Vtual\Models\Creator;
use App\Domain\Vtual\Services\ChannelClaimService;
use App\Domain\Vtual\Services\CreatorClaimService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DiscordOAuthController extends Controller
{
    public function __construct(
        private readonly DiscordOAuthService $discordOAuth,
        private readonly UserOAuthAccountService $oauthAccounts,
        private readonly CreatorClaimService $claims,
        private readonly ChannelClaimService $channelClaims,
    ) {}

    public function redirect(Request $request): RedirectResponse
    {
        $user = $request->user();
        $intent = (string) $request->query('intent', 'profile');
        $slug = $request->query('slug');
        $channelId = $request->query('channel_id');
        $claimId = $request->query('claim_id');
        $returnTo = OAuthReturnUrl::sanitize(
            is_string($request->query('return_to')) ? $request->query('return_to') : null,
        );

        $state = DiscordOAuthState::encode(
            (string) $user->id,
            $intent,
            is_string($slug) ? $slug : null,
            is_string($channelId) ? $channelId : null,
            is_string($claimId) ? $claimId : null,
            $returnTo,
        );

        return redirect()->away($this->discordOAuth->authorizeUrl($state));
    }

    public function callback(Request $request): RedirectResponse
    {
        $code = (string) $request->query('code', '');
        $state = (string) $request->query('state', '');
        $oauthError = $request->query('error');

        $payload = null;
        if ($state !== '') {
            try {
                $payload = DiscordOAuthState::decode($state);
            } catch (\InvalidArgumentException) {
                $payload = null;
            }
        }

        if ($oauthError !== null) {
            return $this->redirectAfterCallback($payload, 'denied', 'error', __('web/identity-connections.discord_denied'));
        }

        if ($code === '' || $payload === null) {
            return $this->redirectAfterCallback($payload, 'error', 'error', __('web/identity-connections.discord_callback_invalid'));
        }

        $user = $this->userFromCallback($request, $payload);

        try {
            $tokenPayload = $this->discordOAuth->exchangeCode($code);
            $this->oauthAccounts->connectDiscord($user, $tokenPayload);
        } catch (IdentityOAuthException $e) {
            return $this->redirectAfterCallback($payload, 'error', 'error', $e->getMessage());
        }

        if ($payload['intent'] === 'claim_channel' && $payload['slug'] && $payload['channel_id']) {
            return $this->attemptClaimChannelVerify($user, $payload);
        }

        if ($payload['intent'] === 'claim_verify' && $payload['claim_id'] && $payload['slug']) {
            return $this->attemptClaimVerify($user, $payload);
        }

        if ($payload['intent'] === 'channel_claim_verify' && $payload['claim_id'] && $payload['slug']) {
            return $this->attemptChannelClaimVerify($user, $payload);
        }

        return $this->redirectAfterCallback(
            $payload,
            'connected',
            'success',
            __('web/identity-connections.discord_connected'),
        );
    }

    /**
     * @param  array{user_id: string, intent: string, slug?: ?string, channel_id?: ?string, claim_id?: ?string, return_to?: ?string}  $payload
     */
    private function userFromCallback(Request $request, array $payload): User
    {
        $sessionUser = $request->user();
        if ($sessionUser instanceof User && (string) $sessionUser->id !== $payload['user_id']) {
            abort(403);
        }

        /** @var User|null $user */
        $user = User::query()->find($payload['user_id']);
        if ($user === null) {
            abort(403);
        }

        return $user;
    }

    /**
     * @param  array{user_id: string, intent: string, slug?: ?string, channel_id?: ?string, claim_id?: ?string, return_to?: ?string}  $payload
     */
    private function attemptClaimChannelVerify(User $user, array $payload): RedirectResponse
    {
        $slug = (string) $payload['slug'];
        $channelId = (string) $payload['channel_id'];

        try {
            /** @var Creator|null $creator */
            $creator = Creator::query()->where('slug', $slug)->first();
            if ($creator === null) {
                throw new \RuntimeException('missing creator');
            }

            $claim = $this->claims->start($user, (string) $creator->id, $channelId);
            $verified = $this->claims->verifyViaDiscord($user, (string) $claim->id);

            if ($verified->status === CreatorClaimStatus::Verified) {
                return $this->redirectAfterCallback(
                    $payload,
                    'connected',
                    'success',
                    __('web/vtual-vtuber.claim_progress_verified'),
                    '/apps/vtual/vtubers/'.$slug,
                );
            }

            return $this->redirectAfterCallback(
                $payload,
                'error',
                'warning',
                __('web/vtual-vtuber.claim_progress_failed'),
                '/apps/vtual/vtubers/'.$slug.'/claim',
            );
        } catch (\Throwable) {
            return $this->redirectAfterCallback(
                $payload,
                'error',
                'error',
                __('web/vtual-vtuber.claim_discord_verify_failed'),
                '/apps/vtual/vtubers/'.$slug.'/claim',
            );
        }
    }

    /**
     * @param  array{user_id: string, intent: string, slug?: ?string, channel_id?: ?string, claim_id?: ?string, return_to?: ?string}  $payload
     */
    private function attemptClaimVerify(User $user, array $payload): RedirectResponse
    {
        $slug = (string) $payload['slug'];
        $claimId = (string) $payload['claim_id'];

        try {
            $verified = $this->claims->verifyViaDiscord($user, $claimId);

            if ($verified->status === CreatorClaimStatus::Verified) {
                return $this->redirectAfterCallback(
                    $payload,
                    'connected',
                    'success',
                    __('web/vtual-vtuber.claim_progress_verified'),
                    '/apps/vtual/vtubers/'.$slug,
                );
            }

            return $this->redirectAfterCallback(
                $payload,
                'error',
                'warning',
                __('web/vtual-vtuber.claim_progress_failed'),
                '/apps/vtual/vtubers/'.$slug.'/claim',
            );
        } catch (\Throwable) {
            return $this->redirectAfterCallback(
                $payload,
                'error',
                'error',
                __('web/vtual-vtuber.claim_discord_verify_failed'),
                '/apps/vtual/vtubers/'.$slug.'/claim',
            );
        }
    }

    /**
     * @param  array{user_id: string, intent: string, slug?: ?string, channel_id?: ?string, claim_id?: ?string, return_to?: ?string}  $payload
     */
    private function attemptChannelClaimVerify(User $user, array $payload): RedirectResponse
    {
        $slug = (string) $payload['slug'];
        $claimId = (string) $payload['claim_id'];

        try {
            $verified = $this->channelClaims->verifyViaDiscord($user, $claimId);

            if ($verified->status === CreatorClaimStatus::Verified) {
                return $this->redirectAfterCallback(
                    $payload,
                    'connected',
                    'success',
                    __('web/vtual-vtuber.claim_progress_verified'),
                    '/apps/vtual/vtubers/'.$slug,
                );
            }

            return $this->redirectAfterCallback(
                $payload,
                'error',
                'warning',
                __('web/vtual-vtuber.claim_progress_failed'),
                '/apps/vtual/vtubers/'.$slug,
            );
        } catch (\Throwable) {
            return $this->redirectAfterCallback(
                $payload,
                'error',
                'error',
                __('web/vtual-vtuber.claim_discord_verify_failed'),
                '/apps/vtual/vtubers/'.$slug,
            );
        }
    }

    /**
     * @param  array{user_id: string, intent: string, slug?: ?string, channel_id?: ?string, claim_id?: ?string, return_to?: ?string}|null  $payload
     */
    private function redirectAfterCallback(
        ?array $payload,
        string $spaStatus,
        string $flashType,
        string $flashMessage,
        ?string $spaPath = null,
    ): RedirectResponse {
        $returnTo = is_string($payload['return_to'] ?? null)
            ? OAuthReturnUrl::sanitize((string) $payload['return_to'])
            : null;

        if ($returnTo !== null) {
            return redirect()->away(OAuthReturnUrl::withDiscordStatus($returnTo, $spaStatus));
        }

        // Blade is gone: every fallback lands on an SPA path, still carrying the status.
        if ($spaPath !== null) {
            return redirect()->away(OAuthReturnUrl::withDiscordStatus(OAuthReturnUrl::fromSpaPath($spaPath), $spaStatus));
        }

        if (is_array($payload) && ($payload['intent'] ?? '') === 'channel_claim_verify' && ($payload['slug'] ?? null)) {
            return redirect()->away(OAuthReturnUrl::withDiscordStatus(OAuthReturnUrl::fromSpaPath('/apps/vtual/vtubers/'.$payload['slug']), $spaStatus));
        }

        if (is_array($payload) && ($payload['slug'] ?? null)) {
            return redirect()->away(OAuthReturnUrl::withDiscordStatus(OAuthReturnUrl::fromSpaPath('/apps/vtual/vtubers/'.$payload['slug'].'/claim'), $spaStatus));
        }

        return redirect()->away(OAuthReturnUrl::withDiscordStatus(OAuthReturnUrl::fromSpaPath('/apps/profile/connections'), $spaStatus));
    }
}
