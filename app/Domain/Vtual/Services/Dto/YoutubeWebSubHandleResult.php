<?php

namespace App\Domain\Vtual\Services\Dto;

use App\Domain\Vtual\Enums\YoutubeWebSubSkipReason;

readonly class YoutubeWebSubHandleResult
{
    /**
     * @param  list<string>  $videoIds
     */
    public function __construct(
        public int $enqueued = 0,
        public ?YoutubeWebSubSkipReason $skipReason = null,
        public array $videoIds = [],
    ) {}

    public function skipped(): bool
    {
        return $this->skipReason !== null;
    }
}
