<?php

namespace App\Domain\Product\Exceptions;

use App\Exceptions\BusinessRuleException;

class DuplicateSkuException extends BusinessRuleException
{
    public function __construct()
    {
        parent::__construct(__('api/product-items.duplicate_sku'));
    }

    public function statusCode(): int
    {
        return 422;
    }
}
