<?php

namespace App\Domain\ErrorLog\Tests\Unit\Support;

use App\Domain\ErrorLog\Support\ErrorMessageNormalizer;
use PHPUnit\Framework\TestCase;

class ErrorMessageNormalizerTest extends TestCase
{
    private ErrorMessageNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new ErrorMessageNormalizer;
    }

    public function test_strips_uuid_and_numeric_noise_for_stable_groups(): void
    {
        $a = $this->normalizer->normalize(
            'User 0193a1b2-c3d4-7e8f-9a0b-1c2d3e4f5a6b failed with code 42',
        );
        $b = $this->normalizer->normalize(
            'User 0193ffff-0000-7000-8000-000000000001 failed with code 99',
        );

        $this->assertSame($a, $b);
        $this->assertStringContainsString('{uuid}', $a);
        $this->assertStringContainsString('{n}', $a);
    }

    public function test_strips_email_addresses(): void
    {
        $a = $this->normalizer->normalize('Login failed for alice@example.com');
        $b = $this->normalizer->normalize('Login failed for bob@other.test');

        $this->assertSame($a, $b);
        $this->assertStringContainsString('{email}', $a);
    }
}
