<?php

namespace App\Domain\Microstock\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Microstock\Exceptions\MicrostockException;
use App\Domain\Microstock\Models\MarketplaceCredential;
use Illuminate\Database\Eloquent\Collection;

/**
 * Marketplace credentials (host/username/password/…) stored envelope-encrypted.
 * Used by the future FTP/API auto-upload; MVP only stores + lists them.
 */
class CredentialService
{
    /**
     * @return Collection<int, MarketplaceCredential>
     */
    public function list(User $owner): Collection
    {
        return MarketplaceCredential::query()
            ->where('user_id', $owner->id)
            ->orderBy('marketplace')
            ->orderBy('label')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $secret  host/username/password/port/…
     */
    public function store(User $owner, string $marketplace, string $label, array $secret): MarketplaceCredential
    {
        $marketplace = trim($marketplace);
        $label = trim($label);

        if ($marketplace === '' || $label === '') {
            throw new MicrostockException(__('api/microstock.credential_invalid'), 422);
        }

        if ($secret === []) {
            throw new MicrostockException(__('api/microstock.credential_secret_required'), 422);
        }

        $credential = new MarketplaceCredential([
            'user_id' => $owner->id,
            'marketplace' => $marketplace,
            'label' => $label,
        ]);
        // Assign via cast so the payload is envelope-encrypted on write.
        $credential->secret_payload = $secret;
        $credential->save();

        return $credential;
    }

    public function delete(User $owner, string $credentialId): void
    {
        $credential = MarketplaceCredential::query()
            ->where('user_id', $owner->id)
            ->whereKey($credentialId)
            ->first();

        if ($credential === null) {
            throw new MicrostockException(__('api/microstock.credential_not_found'), 404);
        }

        $credential->delete();
    }
}
