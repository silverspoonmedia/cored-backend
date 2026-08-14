<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Rule;

trait InteractsWithListDisplayMode
{
    use InteractsWithListQueryPaging;

    /**
     * Whether the client wants a Yajra DataTables response.
     *
     * Explicit `display_mode=datatable`, or a DataTables AJAX payload (`draw`), selects datatable mode.
     * `display_mode=query` forces the legacy JSON envelope even when `draw` is present.
     */
    public function wantsDatatable(): bool
    {
        $mode = $this->input('display_mode');

        if ($mode === 'query') {
            return false;
        }

        if ($mode === 'datatable') {
            return true;
        }

        return $this->has('draw');
    }

    /**
     * @return array<string, mixed>
     */
    protected function listDisplayModeRules(): array
    {
        return array_merge([
            'display_mode' => ['sometimes', 'nullable', 'string', Rule::in(['query', 'datatable'])],
        ], $this->listQueryPagingRules());
    }
}
