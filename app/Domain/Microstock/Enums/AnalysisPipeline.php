<?php

namespace App\Domain\Microstock\Enums;

/**
 * Which queue lane runs a vision/thinking job: per-collection pipeline or solo re-analyze.
 */
enum AnalysisPipeline: string
{
    case Collection = 'collection';
    case Solo = 'solo';

    public function visionQueue(bool $freeTier = false): string
    {
        return match ($this) {
            self::Collection => $freeTier
                ? (string) config('microstock.queues.collection_vision_free', 'microstock-collection-vision-free')
                : (string) config('microstock.queues.collection_vision', 'microstock-collection-vision'),
            self::Solo => $freeTier
                ? (string) config('microstock.queues.solo_free', 'microstock-solo-free')
                : (string) config('microstock.queues.solo', 'microstock-solo'),
        };
    }

    public function thinkingQueue(bool $freeTier = false): string
    {
        return match ($this) {
            self::Collection => $freeTier
                ? (string) config('microstock.queues.collection_thinking_free', 'microstock-collection-thinking-free')
                : (string) config('microstock.queues.collection_thinking', 'microstock-collection-thinking'),
            self::Solo => $freeTier
                ? (string) config('microstock.queues.solo_free', 'microstock-solo-free')
                : (string) config('microstock.queues.solo', 'microstock-solo'),
        };
    }

    public function concurrencyScopeKey(string $stage, ?string $folderId): string
    {
        return match ($this) {
            self::Collection => 'microstock:conc:'.$stage.':collection:'.($folderId ?? 'unknown'),
            self::Solo => 'microstock:conc:'.$stage.':solo',
        };
    }

    public function thinkingMetadataOverlapKey(string $metadataId): string
    {
        return $this->value.'-thinking-metadata:'.$metadataId;
    }
}
