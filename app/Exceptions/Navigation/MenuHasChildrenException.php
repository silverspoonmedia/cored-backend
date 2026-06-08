<?php

namespace App\Exceptions\Navigation;

use App\Exceptions\BusinessRuleException;

class MenuHasChildrenException extends BusinessRuleException
{
    public function __construct()
    {
        parent::__construct('This menu has child items. Pass cascade=true to delete it together with its descendants.');
    }

    public function statusCode(): int
    {
        return 409;
    }
}
