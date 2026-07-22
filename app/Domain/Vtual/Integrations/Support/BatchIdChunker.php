<?php

namespace App\Domain\Vtual\Integrations\Support;

use App\Domain\Vtual\Exceptions\VtualException;

/**
 * Normalize external id lists for multi-id platform API calls.
 *
 * Default: hard-fail when count exceeds max (no silent multi-request).
 */
final class BatchIdChunker
{
    /**
     * @param  list<string|int>  $ids
     * @return list<string>
     */
    public static function normalize(array $ids, int $max, string $label = 'ids'): array
    {
        if ($max < 1) {
            throw new VtualException(__('api/vtual.integrations.batch_max_invalid'), 500);
        }

        $clean = [];
        $seen = [];
        foreach ($ids as $id) {
            if (! is_string($id) && ! is_int($id)) {
                continue;
            }
            $value = trim((string) $id);
            if ($value === '' || isset($seen[$value])) {
                continue;
            }
            $seen[$value] = true;
            $clean[] = $value;
        }

        if ($clean === []) {
            throw new VtualException(__('api/vtual.integrations.ids_required', [
                'label' => $label,
            ]), 422);
        }

        if (count($clean) > $max) {
            throw new VtualException(__('api/vtual.integrations.batch_too_large', [
                'label' => $label,
                'max' => $max,
                'count' => count($clean),
            ]), 422);
        }

        return $clean;
    }

    /**
     * Split into chunks of $max (for fetch*Chunked helpers).
     *
     * @param  list<string|int>  $ids
     * @return list<list<string>>
     */
    public static function chunk(array $ids, int $max, string $label = 'ids'): array
    {
        if ($max < 1) {
            throw new VtualException(__('api/vtual.integrations.batch_max_invalid'), 500);
        }

        $clean = [];
        $seen = [];
        foreach ($ids as $id) {
            if (! is_string($id) && ! is_int($id)) {
                continue;
            }
            $value = trim((string) $id);
            if ($value === '' || isset($seen[$value])) {
                continue;
            }
            $seen[$value] = true;
            $clean[] = $value;
        }

        if ($clean === []) {
            throw new VtualException(__('api/vtual.integrations.ids_required', [
                'label' => $label,
            ]), 422);
        }

        /** @var list<list<string>> $chunks */
        $chunks = array_chunk($clean, $max);

        return $chunks;
    }
}
