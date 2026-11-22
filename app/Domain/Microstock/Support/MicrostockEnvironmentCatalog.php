<?php

namespace App\Domain\Microstock\Support;

use App\Support\EnvironmentVariableCatalog;

/**
 * Microstock's environment keys, in the kernel's typed catalog.
 *
 * `.env.example` declares the per-queue worker names Microstock runs, and the
 * kernel's template validation needs their types. Those keys are product
 * knowledge, so Microstock publishes them from its provider instead of the kernel
 * listing them — removing the product removes the keys with it.
 */
final class MicrostockEnvironmentCatalog
{
    /**
     * @return array<string, string>
     */
    public static function types(): array
    {
        return [
            'MICROSTOCK_AI_COLLECTION_VISION_QUEUE' => EnvironmentVariableCatalog::TYPE_STRING,
            'MICROSTOCK_AI_COLLECTION_THINKING_QUEUE' => EnvironmentVariableCatalog::TYPE_STRING,
            'MICROSTOCK_AI_SOLO_QUEUE' => EnvironmentVariableCatalog::TYPE_STRING,
            'MICROSTOCK_AI_COLLECTION_VISION_QUEUE_FREE' => EnvironmentVariableCatalog::TYPE_STRING,
            'MICROSTOCK_AI_COLLECTION_THINKING_QUEUE_FREE' => EnvironmentVariableCatalog::TYPE_STRING,
            'MICROSTOCK_AI_SOLO_QUEUE_FREE' => EnvironmentVariableCatalog::TYPE_STRING,
        ];
    }
}
