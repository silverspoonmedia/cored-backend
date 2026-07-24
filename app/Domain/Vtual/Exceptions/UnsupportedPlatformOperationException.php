<?php

namespace App\Domain\Vtual\Exceptions;

use App\Domain\Vtual\Enums\PlatformKey;
use App\Domain\Vtual\Integrations\Enums\PlatformApiOperation;

class UnsupportedPlatformOperationException extends VtualException
{
    public function __construct(PlatformKey $platform, PlatformApiOperation $operation)
    {
        parent::__construct(
            __('api/vtual.integrations.unsupported_operation', [
                'platform' => $platform->value,
                'operation' => $operation->value,
            ]),
            422,
        );
    }
}
