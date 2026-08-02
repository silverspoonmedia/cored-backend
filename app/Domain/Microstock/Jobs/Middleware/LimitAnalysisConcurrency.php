<?php

namespace App\Domain\Microstock\Jobs\Middleware;

use App\Domain\Microstock\Enums\AnalysisPipeline;
use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Cache-backed global + per-scope concurrency caps for vision/thinking jobs.
 */
class LimitAnalysisConcurrency
{
    public function __construct(
        private readonly string $stage,
        private readonly AnalysisPipeline $pipeline,
        private readonly ?string $folderId = null,
    ) {}

    public function handle(object $job, Closure $next): void
    {
        /** @var array<string, mixed> $upstream */
        $upstream = setting_config('microstock.ai.upstream', []);
        $releaseAfter = max(1, (int) ($upstream['release_after_seconds'] ?? 15));

        $globalMax = $this->globalMax($upstream);
        $scopeMax = $this->scopeMax($upstream);

        if ($globalMax <= 0 || $scopeMax <= 0) {
            $next($job);

            return;
        }

        $globalKey = 'microstock:conc:global:'.$this->stage;
        $scopeKey = $this->pipeline->concurrencyScopeKey($this->stage, $this->folderId);

        $globalCount = (int) Cache::increment($globalKey);
        if ($globalCount > $globalMax) {
            Cache::decrement($globalKey);
            $job->release($releaseAfter);

            return;
        }

        $scopeCount = (int) Cache::increment($scopeKey);
        if ($scopeCount > $scopeMax) {
            Cache::decrement($globalKey);
            Cache::decrement($scopeKey);
            $job->release($releaseAfter);

            return;
        }

        try {
            $next($job);
        } finally {
            Cache::decrement($globalKey);
            Cache::decrement($scopeKey);
        }
    }

    /**
     * @param  array<string, mixed>  $upstream
     */
    private function globalMax(array $upstream): int
    {
        return match ($this->stage) {
            'vision' => max(0, (int) ($upstream['max_concurrent_vision'] ?? 6)),
            'thinking' => max(0, (int) ($upstream['max_concurrent_thinking'] ?? 10)),
            default => 0,
        };
    }

    /**
     * @param  array<string, mixed>  $upstream
     */
    private function scopeMax(array $upstream): int
    {
        if ($this->pipeline === AnalysisPipeline::Collection) {
            return match ($this->stage) {
                'vision' => max(0, (int) ($upstream['collection_vision_concurrency'] ?? 3)),
                'thinking' => max(0, (int) ($upstream['collection_thinking_concurrency'] ?? 3)),
                default => 0,
            };
        }

        return match ($this->stage) {
            'vision' => max(0, (int) ($upstream['solo_vision_concurrency'] ?? 2)),
            'thinking' => max(0, (int) ($upstream['solo_thinking_concurrency'] ?? 2)),
            default => 0,
        };
    }
}
