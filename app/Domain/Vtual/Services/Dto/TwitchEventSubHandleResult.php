<?php

namespace App\Domain\Vtual\Services\Dto;

use App\Domain\Vtual\Enums\TwitchEventSubSkipReason;

readonly class TwitchEventSubHandleResult
{
    public function __construct(
        public ?string $challenge = null,
        public int $enqueued = 0,
        public ?TwitchEventSubSkipReason $skipReason = null,
        public ?string $liveAction = null,
    ) {}

    public function isVerification(): bool
    {
        return $this->challenge !== null;
    }

    public function skipped(): bool
    {
        return $this->skipReason !== null;
    }
}
