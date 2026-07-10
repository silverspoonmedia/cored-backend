<?php

namespace App\Domain\Playground\Exceptions;

use App\Exceptions\BusinessRuleException;

/**
 * A completed order is immutable: editing or deleting it would desync already
 * fulfilled business records. Domain rule, kept out of the HTTP validation layer.
 */
class OrderNotEditableException extends BusinessRuleException
{
    public function __construct()
    {
        parent::__construct(__('api/playground-orders.not_editable'));
    }

    public function statusCode(): int
    {
        return 409;
    }
}
