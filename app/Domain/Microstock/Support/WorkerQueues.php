<?php

namespace App\Domain\Microstock\Support;

/**
 * Ordered Microstock queue names for queue workers.
 *
 * Paid lanes first, then daily-free lanes, then `default` for orchestration
 * jobs (ProcessCollection, AnalysisWatchdog).
 */
final class WorkerQueues
{
    /** @var list<string> */
    private const ORDER = [
        'collection_vision',
        'collection_thinking',
        'solo',
        'collection_vision_free',
        'collection_thinking_free',
        'solo_free',
    ];

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        $queues = config('microstock.queues', []);
        $names = [];

        foreach (self::ORDER as $key) {
            $name = trim((string) ($queues[$key] ?? ''));

            if ($name !== '') {
                $names[] = $name;
            }
        }

        $names = array_values(array_unique($names));
        $names[] = 'default';

        return $names;
    }

    public static function csv(): string
    {
        return implode(',', self::names());
    }
}
