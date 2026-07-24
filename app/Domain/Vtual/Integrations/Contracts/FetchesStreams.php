<?php

namespace App\Domain\Vtual\Integrations\Contracts;

use App\Domain\Vtual\Integrations\Dto\PlatformStreamDto;
use Illuminate\Support\Collection;

/**
 * Live stream snapshots by broadcaster/user external ids.
 * Implemented by Twitch; not forced on YouTube (different discovery model).
 */
interface FetchesStreams
{
    /**
     * @param  list<string|int>  $userExternalIds
     * @return Collection<int, PlatformStreamDto>
     */
    public function fetchStreamsByUserIds(array $userExternalIds): Collection;
}
