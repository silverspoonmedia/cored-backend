<?php

namespace Tests\Unit\Support\Encryption;

use App\Support\Encryption\BlindIndex;
use RuntimeException;
use Tests\TestCase;

class BlindIndexTest extends TestCase
{
    public function test_make_is_deterministic_for_normalized_input(): void
    {
        config(['encryption.blind_index_pepper' => 'pepper']);

        $first = BlindIndex::make('User@Example.COM');
        $second = BlindIndex::make('  user@example.com ');

        $this->assertSame($first, $second);
        $this->assertSame(64, strlen($first));
    }

    public function test_make_requires_pepper(): void
    {
        config(['encryption.blind_index_pepper' => '']);

        $this->expectException(RuntimeException::class);

        BlindIndex::make('token');
    }
}
