<?php

namespace App\Support\DataTable;

use App\Models\User;
use App\Support\DataTable\Contracts\RowActionResolverInterface;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

final class DataTableResponseFactory
{
    /**
     * Build a Yajra DataTables JSON response with an `actions` column per row.
     *
     * @param  iterable<mixed>  $items
     * @param  callable(mixed): array<string, mixed>  $presentRow
     */
    public function respond(
        iterable $items,
        callable $presentRow,
        RowActionResolverInterface $actionResolver,
        ?User $actor = null,
    ): JsonResponse {
        $rows = collect($items)->map(function (mixed $item) use ($presentRow, $actionResolver, $actor): array {
            return array_merge($presentRow($item), [
                'actions' => array_map(
                    fn (ActionButton $button) => $button->toArray(),
                    $actionResolver->resolve($item, $actor),
                ),
            ]);
        });

        return DataTables::of($rows)->make(true);
    }
}
