<?php

namespace App\Domain\Microstock\Support;

/**
 * @phpstan-type BatchTarget array{metadata_id: string, file_id: string}
 */
final class AnalysisBillingContext
{
    private static ?self $current = null;

    /** @param list<BatchTarget> $batchTargets */
    public function __construct(
        public readonly string $userId,
        public readonly ?string $metadataId = null,
        public readonly ?string $fileId = null,
        public readonly ?string $folderId = null,
        public readonly array $batchTargets = [],
    ) {}

    public static function current(): ?self
    {
        return self::$current;
    }

    /** @template TReturn @param callable(): TReturn $callback @return TReturn */
    public static function run(self $context, callable $callback): mixed
    {
        $previous = self::$current;
        self::$current = $context;
        try {
            return $callback();
        } finally {
            self::$current = $previous;
        }
    }
}