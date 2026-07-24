<?php

namespace App\Domain\Vtual\Integrations\Contracts;

use App\Domain\Vtual\Integrations\Dto\PlatformVideoDto;
use Illuminate\Support\Collection;

interface FetchesVideos
{
    /**
     * @param  list<string|int>  $externalIds  Max batch from config vtual.http.batch.videos
     * @param  list<string>|null  $parts  YouTube parts only; ignored by Twitch
     * @return Collection<int, PlatformVideoDto>
     */
    public function fetchVideos(array $externalIds, ?array $parts = null): Collection;
}
