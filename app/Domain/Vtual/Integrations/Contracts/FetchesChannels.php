<?php

namespace App\Domain\Vtual\Integrations\Contracts;

use App\Domain\Vtual\Integrations\Dto\PlatformChannelDto;
use Illuminate\Support\Collection;

interface FetchesChannels
{
    /**
     * @param  list<string|int>  $externalIds  Max batch from config vtual.http.batch.channels
     * @param  list<string>|null  $parts  YouTube parts only; ignored by Twitch
     * @return Collection<int, PlatformChannelDto>
     */
    public function fetchChannels(array $externalIds, ?array $parts = null): Collection;
}
