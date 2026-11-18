<?php

namespace Tests\Domain\Microstock\Unit\Support;

use App\Domain\Microstock\Support\Prompts;
use Tests\TestCase;

class PromptsTest extends TestCase
{
    public function test_vision_system_stays_under_gateway_char_limit(): void
    {
        $this->assertLessThan(
            1200,
            strlen(Prompts::visionSystem()),
            'visionSystem() must stay compact — long prompts + images can 524 on the gateway',
        );
    }

    public function test_thinking_system_stays_under_gateway_char_limit(): void
    {
        $this->assertLessThan(
            1200,
            strlen(Prompts::thinkingSystem()),
            'thinkingSystem() must stay compact — long prompts + images can 524 on the gateway',
        );
    }

    public function test_marketplace_rules_include_ban_list(): void
    {
        $reflection = new \ReflectionClass(Prompts::class);
        $method = $reflection->getMethod('marketplaceRules');
        $method->setAccessible(true);
        $rules = $method->invoke(null);

        $this->assertStringContainsString('BAN:', $rules);
        $this->assertStringContainsString('white background', $rules);
        $this->assertStringContainsString('50-60', $rules);
    }
}
