<?php

namespace Tests\Domain\Vtual\Unit\Support;

use App\Domain\Vtual\Support\SlugGenerator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SlugGeneratorTest extends TestCase
{
    #[Test]
    public function it_slugifies_display_names(): void
    {
        $this->assertSame('hello-world', SlugGenerator::from('Hello World'));
    }

    #[Test]
    public function it_falls_back_when_empty(): void
    {
        $this->assertSame('item', SlugGenerator::from('!!!'));
    }

    #[Test]
    public function it_appends_suffix_until_unique(): void
    {
        $existing = ['foo' => true, 'foo-2' => true];

        $slug = SlugGenerator::unique('Foo', fn (string $s): bool => isset($existing[$s]));

        $this->assertSame('foo-3', $slug);
    }
}
