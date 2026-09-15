<?php

namespace Tests\Domain\Archivd\Unit\Support;

use App\Domain\Archivd\Support\Sha256Checksum;
use Tests\TestCase;

class Sha256ChecksumTest extends TestCase
{
    public function test_normalize_lowercases_and_trims(): void
    {
        $hash = hash('sha256', 'payload');

        $this->assertSame($hash, Sha256Checksum::normalize('  '.strtoupper($hash).'  '));
    }

    public function test_is_valid_accepts_uppercase_hex(): void
    {
        $hash = hash('sha256', 'payload');

        $this->assertTrue(Sha256Checksum::isValid(strtoupper($hash)));
        $this->assertFalse(Sha256Checksum::isValid('not-a-hash'));
        $this->assertFalse(Sha256Checksum::isValid(str_repeat('g', 64)));
    }
}
