<?php

namespace App\Domain\Vtual\Integrations\Contracts;

use App\Domain\Vtual\Enums\PlatformKey;
use App\Domain\Vtual\Integrations\Enums\PlatformApiOperation;
use App\Domain\Vtual\Models\PlatformCredential;

interface PlatformClient
{
    public function key(): PlatformKey;

    public function supports(PlatformApiOperation $operation): bool;

    /**
     * Bind active credential for subsequent calls (set by gateway).
     */
    public function withCredential(PlatformCredential $credential): static;
}
