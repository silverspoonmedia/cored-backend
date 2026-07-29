<?php

namespace Tests\Domain\Microstock\Unit\Support;

use App\Domain\Microstock\Support\KeywordNormalizer;
use Tests\TestCase;

class KeywordNormalizerTest extends TestCase
{
    public function test_dedupes_case_insensitive_and_caps_at_sixty(): void
    {
        $keywords = [];
        for ($i = 1; $i <= 80; $i++) {
            $keywords[] = 'keyword'.$i;
        }
        $keywords[] = 'Keyword1';

        $normalized = KeywordNormalizer::normalize($keywords);

        $this->assertCount(60, $normalized);
        $this->assertSame('keyword1', $normalized[0]);
        $this->assertSame('keyword60', $normalized[59]);
    }

    public function test_trims_and_drops_empty(): void
    {
        $this->assertSame(['cat', 'dog'], KeywordNormalizer::normalize([' cat ', '', 'dog', 'CAT']));
    }
}
