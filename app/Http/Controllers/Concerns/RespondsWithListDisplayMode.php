<?php

namespace App\Http\Controllers\Concerns;

use App\Support\ListQueryPaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

trait RespondsWithListDisplayMode
{
    /**
     * Return either the standard query JSON envelope or a DataTables payload.
     */
    protected function respondToListDisplayMode(
        object $request,
        callable $queryResponse,
        callable $datatableResponse,
    ): JsonResponse {
        if (method_exists($request, 'wantsDatatable') && $request->wantsDatatable()) {
            return $datatableResponse();
        }

        return $queryResponse();
    }

    /**
     * Query-mode list body: legacy flat `T[]` unless page/per_page/search are present,
     * then `{ items, pagination }` (same shape as inference/microstock usage events).
     *
     * @param  callable(mixed): array<int, array<string, mixed>>  $resolveRows
     */
    protected function respondToQueryList(
        object $request,
        mixed $list,
        callable $resolveRows,
        string $message,
    ): JsonResponse {
        $rows = Collection::make($resolveRows($list))->values();

        if (! $this->requestWantsQueryPaging($request)) {
            return $this->successResponse($rows->all(), $message);
        }

        /** @var Request $pagingRequest */
        $pagingRequest = $request instanceof Request ? $request : request();
        $page = ListQueryPaginator::paginate($rows, $pagingRequest);

        return $this->successResponse([
            'items' => array_values($page->items()),
            'pagination' => ListQueryPaginator::meta($page),
        ], $message);
    }

    private function requestWantsQueryPaging(object $request): bool
    {
        if (method_exists($request, 'wantsQueryPaging')) {
            return (bool) $request->wantsQueryPaging();
        }

        if ($request instanceof Request) {
            return $request->exists('page') || $request->exists('per_page') || $request->filled('search');
        }

        return false;
    }
}
