<?php

namespace App\Exceptions\Playground;

use App\Exceptions\BusinessRuleException;

/**
 * A direct-upload session is missing, expired, incomplete, or otherwise unusable.
 */
class AttachmentUploadException extends BusinessRuleException
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }

    public function statusCode(): int
    {
        return 422;
    }
}
