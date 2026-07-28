<?php

namespace App\Domain\Microstock\Enums;

/**
 * Which queue lane runs a vision/thinking job: per-collection pipeline or solo re-analyze.
 */
enum AnalysisPipeline: string
{
    case Collection = 'collection';
    case Solo = 'solo';

    public function visionQueue(): string
    {
        return match ($this) {
            self::Collection => (string) config('microstock.queues.collection_vision', 'microstock-collection-vision'),
            self::Solo => (string) config('microstock.queues.solo', 'microstock-solo'),
        };
    }

    public function thinkingQueue(): string
    {
        return match ($this) {
            self::Collection => (string) config('microstock.queues.collection_thinking', 'microstock-collection-thinking'),
            self::Solo => (string) config('microstock.queues.solo', 'microstock-solo'),
        };
    }

    public function visionOverlapKey(?string $folderId): string
    {
        return match ($this) {
            self::Collection => 'collection-vision:'.($folderId ?? 'unknown'),
            self::Solo => 'solo-vision',
        };
    }

    public function thinkingOverlapKey(?string $folderId): string
    {
        return match ($this) {
            self::Collection => 'collection-thinking:'.($folderId ?? 'unknown'),
            self::Solo => 'solo-thinking',
        };
    }

    public function thinkingMetadataOverlapKey(string $metadataId): string
    {
        return $this->value.'-thinking-metadata:'.$metadataId;
    }
}
