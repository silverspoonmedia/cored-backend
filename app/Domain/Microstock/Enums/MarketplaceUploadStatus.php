<?php

namespace App\Domain\Microstock\Enums;

/**
 * Lifecycle of one marketplace upload attempt.
 *
 * The upload is a copy-out: no local file, derivative, metadata row or CSV is
 * moved or deleted, so this enum tracks the transfer only.
 */
enum MarketplaceUploadStatus: string
{
    case Queued = 'queued';
    case Uploading = 'uploading';
    case Uploaded = 'uploaded';
    case Failed = 'failed';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Uploaded, self::Failed => true,
            self::Queued, self::Uploading => false,
        };
    }
}
