<?php

namespace App\Domain\Identity\Http\Controllers\API\V1\Auth;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\DiscordOAuthService;
use App\Domain\Identity\Services\UserOAuthAccountService;
use App\Http\Controllers\Controller;
use App\Support\Presentation\DatePresenter;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ProfileConnectionsController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly UserOAuthAccountService $oauthAccounts,
        private readonly DiscordOAuthService $discordOAuth,
    ) {}

    public function show(): JsonResponse
    {
        $user = $this->actor();
        $account = $this->oauthAccounts->discordAccountFor($user);

        if ($account !== null && ! $this->oauthAccounts->isConnectionsSnapshotFresh($user)) {
            try {
                $this->oauthAccounts->syncDiscordConnections($user);
                $account = $this->oauthAccounts->discordAccountFor($user);
            } catch (\Throwable) {
                // Keep stale snapshot when Discord API is unavailable.
            }
        }

        [$syncedAt, $syncedAtFormatted, $syncedAtHuman] = DatePresenter::pack($account?->connections_synced_at);

        return $this->successResponse([
            'discord_connected' => $account !== null,
            'discord_oauth_configured' => $this->discordOAuth->isConfigured(),
            'discord_synced_at' => $syncedAt,
            'discord_synced_at_formatted' => $syncedAtFormatted,
            'discord_synced_at_human' => $syncedAtHuman,
            'connections' => $this->presentConnections($this->oauthAccounts->connectionsSnapshotFor($user)),
        ], __('api/identity.connections.show'));
    }

    public function disconnectDiscord(): JsonResponse
    {
        $this->oauthAccounts->disconnectDiscord($this->actor());

        return $this->successResponse(null, __('api/identity.connections.discord_disconnected'));
    }

    /**
     * @param  list<array<string, mixed>>  $snapshot
     * @return list<array{type: string, id: string|null, name: string|null, verified: bool, revoked: bool}>
     */
    private function presentConnections(array $snapshot): array
    {
        $rows = [];

        foreach ($snapshot as $row) {
            if (! is_array($row)) {
                continue;
            }

            $rows[] = [
                'type' => (string) ($row['type'] ?? ''),
                'id' => isset($row['id']) ? (string) $row['id'] : null,
                'name' => isset($row['name']) ? (string) $row['name'] : null,
                'verified' => (bool) ($row['verified'] ?? false),
                'revoked' => (bool) ($row['revoked'] ?? false),
            ];
        }

        return $rows;
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = Auth::guard('api')->user();

        return $user;
    }
}
