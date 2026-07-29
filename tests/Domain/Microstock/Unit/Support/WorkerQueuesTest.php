<?php

namespace Tests\Domain\Microstock\Unit\Support;

use App\Domain\Microstock\Support\WorkerQueues;
use Tests\TestCase;

class WorkerQueuesTest extends TestCase
{
    public function test_names_returns_configured_queues_in_priority_order_with_default_last(): void
    {
        config([
            'microstock.queues' => [
                'collection_vision' => 'q-vision',
                'collection_thinking' => 'q-thinking',
                'solo' => 'q-solo',
                'collection_vision_free' => 'q-vision-free',
                'collection_thinking_free' => 'q-thinking-free',
                'solo_free' => 'q-solo-free',
            ],
        ]);

        $this->assertSame([
            'q-vision',
            'q-thinking',
            'q-solo',
            'q-vision-free',
            'q-thinking-free',
            'q-solo-free',
            'default',
        ], WorkerQueues::names());
    }

    public function test_names_deduplicates_duplicate_config_values(): void
    {
        config([
            'microstock.queues' => [
                'collection_vision' => 'same-queue',
                'collection_thinking' => 'same-queue',
                'solo' => 'q-solo',
                'collection_vision_free' => '',
                'collection_thinking_free' => '',
                'solo_free' => '',
            ],
        ]);

        $this->assertSame([
            'same-queue',
            'q-solo',
            'default',
        ], WorkerQueues::names());
    }

    public function test_csv_joins_names_with_commas(): void
    {
        config([
            'microstock.queues' => [
                'collection_vision' => 'a',
                'collection_thinking' => 'b',
                'solo' => 'c',
                'collection_vision_free' => 'd',
                'collection_thinking_free' => 'e',
                'solo_free' => 'f',
            ],
        ]);

        $this->assertSame('a,b,c,d,e,f,default', WorkerQueues::csv());
    }
}
