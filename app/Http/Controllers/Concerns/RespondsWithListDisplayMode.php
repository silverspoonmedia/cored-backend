<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;

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
}
