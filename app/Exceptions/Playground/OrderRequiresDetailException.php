<?php

namespace App\Exceptions\Playground;

use App\Exceptions\BusinessRuleException;

/**
 * An order must carry at least one detail line. Enforced in the service (a
 * business rule about the aggregate), not as request validation.
 */
class OrderRequiresDetailException extends BusinessRuleException
{
    public function __construct()
    {
        parent::__construct(__('api/playground-orders.requires_detail'));
    }

    public function statusCode(): int
    {
        return 422;
    }
}
