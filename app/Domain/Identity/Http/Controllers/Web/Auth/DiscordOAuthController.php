<?php

namespace App\Domain\Identity\Http\Controllers\Web\Auth;

use App\Domain\Identity\Exceptions\IdentityOAuthException;
use App\Domain\Identity\Services\DiscordOAuthService;
use App\Domain\Identity\Services\UserOAuthAccountService;
use App\Domain\Identity\Support\DiscordOAuthState;
use App\Domain\Vtual\Enums\CreatorClaimStatus;
use App\Domain\Vtual\Models\Creator;
use App\Domain\Vtual\Services\ChannelClaimService;
use App\Domain\Vtual\Services\CreatorClaimService;
use App\Http\Controllers\Web\Controller;
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

        $state = DiscordOAuthState::encode(
            (string) $user->id,
            $intent,
            is_string($slug) ? $slug : null,
            is_string($channelId) ? $channelId : null,
            is_string($claimId) ? $claimId : null,
        );

        return redirect()->away($this->discordOAuth->authorizeUrl($state));
    }

    public function callback(Request $request): RedirectResponse
    {
        $user = $request->user();
        $code = (string) $request->query('code', '');
        $state = (string) $request->query('state', '');
        $oauthError = $request->query('error');

        if ($oauthError !== null) {
            return redirect()->route('profile.connections.edit')
                ->with('error', __('web/identity-connections.discord_denied'));
        }

        if ($code === '' || $state === '') {
            return redirect()->route('profile.connections.edit')
                ->with('error', __('web/identity-connections.discord_callback_invalid'));
        }

        try {
            $payload = DiscordOAuthState::decode($state);
        } catch (\InvalidArgumentException) {
            return redirect()->route('profile.connections.edit')
                ->with('error', __('web/identity-connections.discord_callback_invalid'));
        }

        if ($payload['user_id'] !== (string) $user->id) {
            abort(403);
        }

        try {
            $tokenPayload = $this->discordOAuth->exchangeCode($code);
            $this->oauthAccounts->connectDiscord($user, $tokenPayload);
        } catch (IdentityOAuthException $e) {
            return $this->redirectAfterCallback($payload)
                ->with('error', $e->getMessage());
        }

        if ($payload['intent'] === 'claim_channel' && $payload['slug'] && $payload['channel_id']) {
            return $this->attemptClaimChannelVerify($user, $payload['slug'], $payload['channel_id']);
        }

        if ($payload['intent'] === 'claim_verify' && $payload['claim_id'] && $payload['slug']) {
            return $this->attemptClaimVerify($user, $payload['slug'], $payload['claim_id']);
        }

        if ($payload['intent'] === 'channel_claim_verify' && $payload['claim_id'] && $payload['slug']) {
            return $this->attemptChannelClaimVerify($user, $payload['slug'], $payload['claim_id']);
        }

        return redirect()->route('profile.connections.edit')
            ->with('success', __('web/identity-connections.discord_connected'));
    }

    private function attemptClaimChannelVerify($user, string $slug, string $channelId): RedirectResponse
    {
        try {
            /** @var Creator|null $creator */
            $creator = Creator::query()->where('slug', $slug)->first();
            if ($creator === null) {
                throw new \RuntimeException('missing creator');
            }

            $claim = $this->claims->start($user, (string) $creator->id, $channelId);
            $verified = $this->claims->verifyViaDiscord($user, (string) $claim->id);

            if ($verified->status === CreatorClaimStatus::Verified) {
                return redirect()->route('vtual.vtuber.show', $slug)
                    ->with('success', __('web/vtual-vtuber.claim_progress_verified'));
            }

            return redirect()->route('vtual.vtuber.claim', $slug)
                ->with('warning', __('web/vtual-vtuber.claim_progress_failed'));
        } catch (\Throwable) {
            return redirect()->route('vtual.vtuber.claim', $slug)
                ->with('error', __('web/vtual-vtuber.claim_discord_verify_failed'));
        }
    }

    private function attemptClaimVerify($user, string $slug, string $claimId): RedirectResponse
    {
        try {
            $verified = $this->claims->verifyViaDiscord($user, $claimId);

            if ($verified->status === CreatorClaimStatus::Verified) {
                return redirect()->route('vtual.vtuber.show', $slug)
                    ->with('success', __('web/vtual-vtuber.claim_progress_verified'));
            }

            return redirect()->route('vtual.vtuber.claim', $slug)
                ->with('warning', __('web/vtual-vtuber.claim_progress_failed'));
        } catch (\Throwable) {
            return redirect()->route('vtual.vtuber.claim', $slug)
                ->with('error', __('web/vtual-vtuber.claim_discord_verify_failed'));
        }
    }

    private function attemptChannelClaimVerify($user, string $slug, string $claimId): RedirectResponse
    {
        try {
            $verified = $this->channelClaims->verifyViaDiscord($user, $claimId);

            if ($verified->status === CreatorClaimStatus::Verified) {
                return redirect()->route('vtual.profiles.show', $slug)
                    ->with('success', __('web/vtual-vtuber.claim_progress_verified'));
            }

            return redirect()->route('vtual.profiles.show', $slug)
                ->with('warning', __('web/vtual-vtuber.claim_progress_failed'));
        } catch (\Throwable) {
            return redirect()->route('vtual.profiles.show', $slug)
                ->with('error', __('web/vtual-vtuber.claim_discord_verify_failed'));
        }
    }

    /**
     * @param  array{user_id: string, intent: string, slug?: ?string, channel_id?: ?string, claim_id?: ?string}  $payload
     */
    private function redirectAfterCallback(array $payload): RedirectResponse
    {
        if ($payload['intent'] === 'channel_claim_verify' && $payload['slug']) {
            return redirect()->route('vtual.profiles.show', $payload['slug']);
        }

        if ($payload['slug']) {
            return redirect()->route('vtual.vtuber.claim', $payload['slug']);
        }

        return redirect()->route('profile.connections.edit');
    }
}
