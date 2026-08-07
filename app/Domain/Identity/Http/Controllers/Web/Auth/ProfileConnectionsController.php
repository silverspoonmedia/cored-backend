<?php

namespace App\Domain\Identity\Http\Controllers\Web\Auth;

use App\Domain\Identity\Services\DiscordOAuthService;
use App\Domain\Identity\Services\UserOAuthAccountService;
use App\Http\Controllers\Web\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileConnectionsController extends Controller
{
    public function __construct(
        private readonly UserOAuthAccountService $oauthAccounts,
        private readonly DiscordOAuthService $discordOAuth,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $discordAccount = $this->oauthAccounts->discordAccountFor($user);

        if ($discordAccount !== null && ! $this->oauthAccounts->isConnectionsSnapshotFresh($user)) {
            try {
                $this->oauthAccounts->syncDiscordConnections($user);
                $discordAccount = $this->oauthAccounts->discordAccountFor($user);
            } catch (\Throwable) {
                // Keep stale snapshot when Discord API is unavailable.
            }
        }

        $connections = $this->oauthAccounts->connectionsSnapshotFor($user);

        return $this->webView('profile.connections', [
            'discordConnected' => $discordAccount !== null,
            'discordOAuthConfigured' => $this->discordOAuth->isConfigured(),
            'discordConnections' => $connections,
            'discordSyncedAt' => $discordAccount?->connections_synced_at,
        ]);
    }

    public function disconnectDiscord(Request $request): RedirectResponse
    {
        $this->oauthAccounts->disconnectDiscord($request->user());

        return back()->with('success', __('web/identity-connections.discord_disconnected'));
    }
}
