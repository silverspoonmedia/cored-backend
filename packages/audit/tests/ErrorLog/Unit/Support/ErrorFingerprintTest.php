<?php

namespace App\Domain\ErrorLog\Tests\Unit\Support;

use App\Domain\ErrorLog\Support\ErrorFingerprint;
use App\Domain\ErrorLog\Support\ErrorMessageNormalizer;
use PHPUnit\Framework\TestCase;

class ErrorFingerprintTest extends TestCase
{
    public function test_same_normalized_message_and_location_share_fingerprint(): void
    {
        $fp = new ErrorFingerprint(new ErrorMessageNormalizer);

        $a = $fp->make(
            'exception',
            'error',
            \RuntimeException::class,
            'User 11111111-1111-7111-8111-111111111111 not found',
            '/app/Foo.php',
            10,
        );

        $b = $fp->make(
            'exception',
            'error',
            \RuntimeException::class,
            'User 22222222-2222-7222-8222-222222222222 not found',
            '/app/Foo.php',
            10,
        );

        $this->assertSame($a, $b);
        $this->assertSame(40, strlen($a));
    }

    public function test_different_file_line_produces_different_fingerprint(): void
    {
        $fp = new ErrorFingerprint(new ErrorMessageNormalizer);

        $a = $fp->make('exception', 'error', \RuntimeException::class, 'boom', '/a.php', 1);
        $b = $fp->make('exception', 'error', \RuntimeException::class, 'boom', '/a.php', 2);

        $this->assertNotSame($a, $b);
    }
}
