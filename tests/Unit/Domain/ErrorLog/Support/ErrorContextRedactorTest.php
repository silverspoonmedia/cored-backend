<?php

namespace Tests\Unit\Domain\ErrorLog\Support;

use App\Domain\ErrorLog\Support\ErrorContextRedactor;
use Tests\TestCase;

class ErrorContextRedactorTest extends TestCase
{
    public function test_redacts_sensitive_keys_case_insensitively(): void
    {
        $redactor = new ErrorContextRedactor;

        $result = $redactor->redact([
            'password' => 'secret',
            'Authorization' => 'Bearer xyz',
            'nested' => [
                'token' => 'abc',
                'safe' => 'ok',
            ],
            'user_id' => 'u-1',
        ]);

        $this->assertSame('[redacted]', $result['password']);
        $this->assertSame('[redacted]', $result['Authorization']);
        $this->assertSame('[redacted]', $result['nested']['token']);
        $this->assertSame('ok', $result['nested']['safe']);
        $this->assertSame('u-1', $result['user_id']);
    }
}
