<?php

namespace Tests\Domain\Microstock\Unit\Config;

use Tests\TestCase;

class MicrostockAiConfigTest extends TestCase
{
    public function test_locked_production_ai_defaults(): void
    {
        $this->assertSame(
            'https://ai-chat.spnd.uk/api/chat/completions',
            config('microstock.ai.vision.endpoint'),
        );
        $this->assertSame('internal.vision', config('microstock.ai.vision.model'));
        $this->assertSame('', config('microstock.ai.vision.free_model'));

        $this->assertSame(
            'https://ai-chat.spnd.uk/api/chat/completions',
            config('microstock.ai.thinking.endpoint'),
        );
        $this->assertSame('internal.thinking', config('microstock.ai.thinking.model'));
        $this->assertSame('', config('microstock.ai.thinking.free_model'));

        $this->assertSame('url', config('microstock.ai.vision_image.delivery'));
        $this->assertSame('ai_1024', config('microstock.ai.vision_image.variant_kind'));
        $this->assertSame(2, config('microstock.ai.thinking_visual_attempts'));
        $this->assertSame(4, config('microstock.ai.batch_min_files'));
    }

    public function test_config_file_default_timeout_is_grace_aligned(): void
    {
        $contents = file_get_contents(base_path('config/microstock.php'));

        $this->assertIsString($contents);
        $this->assertStringContainsString("'timeout' => 1500", $contents);
        $this->assertStringNotContainsString("env('MICROSTOCK_AI_VISION_TIMEOUT'", $contents);
        $this->assertStringNotContainsString("env('MICROSTOCK_AI_THINKING_TIMEOUT'", $contents);
        $this->assertStringNotContainsString("env('MICROSTOCK_AI_VISION_MODEL_FREE'", $contents);
        $this->assertStringNotContainsString("env('MICROSTOCK_AI_THINKING_MODEL_FREE'", $contents);
        $this->assertStringContainsString("'internal.vision'", $contents);
        $this->assertStringContainsString("'internal.thinking'", $contents);
    }
}
