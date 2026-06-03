<?php

namespace App\Exceptions\User;

use App\Exceptions\BusinessRuleException;

class CannotDeleteSelfException extends BusinessRuleException
{
    public function __construct()
    {
        parent::__construct('Anda tidak dapat menghapus akun Anda sendiri.');
    }

    public function statusCode(): int
    {
        return 422;
    }
}
