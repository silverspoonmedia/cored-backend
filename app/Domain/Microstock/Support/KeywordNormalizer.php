<?php

namespace App\Domain\Microstock\Support;

final class KeywordNormalizer
{
    public const MAX_KEYWORDS = 60;

    /**
     * Trim, dedupe (case-insensitive), cap at {@see MAX_KEYWORDS} preserving order.
     *
     * @param  list<string>  $keywords
     * @return list<string>
     */
    public static function normalize(array $keywords): array
    {
        $seen = [];
        $out = [];

        foreach ($keywords as $keyword) {
            if (! is_string($keyword)) {
                continue;
            }
            $trimmed = trim($keyword);
            if ($trimmed === '') {
                continue;
            }
            $key = mb_strtolower($trimmed);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $trimmed;

            if (count($out) >= self::MAX_KEYWORDS) {
                break;
            }
        }

        return $out;
    }
}
