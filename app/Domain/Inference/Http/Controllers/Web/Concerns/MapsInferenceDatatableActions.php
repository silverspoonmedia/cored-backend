<?php

namespace App\Domain\Inference\Http\Controllers\Web\Concerns;

trait MapsInferenceDatatableActions
{
    /**
     * @param  array<string, mixed>  $row
     * @param  array{show?: string, edit?: string, delete?: string}  $routes
     * @return array<string, mixed>
     */
    protected function mapInferenceRowActions(array $row, array $routes): array
    {
        $allowed = array_column($row['actions'] ?? [], 'key');
        $id = $row['id'] ?? null;
        $actions = [];

        if ($id !== null && isset($routes['show']) && in_array('show', $allowed, true)) {
            $actions[] = [
                'key' => 'show',
                'label' => __('ui.datatable.view'),
                'method' => 'GET',
                'url' => route($routes['show'], $id),
                'style' => 'info',
                'icon' => 'bi bi-eye',
            ];
        }

        if ($id !== null && isset($routes['edit']) && in_array('edit', $allowed, true)) {
            $actions[] = [
                'key' => 'edit',
                'label' => __('ui.datatable.edit'),
                'method' => 'GET',
                'url' => route($routes['edit'], $id),
                'style' => 'primary',
                'icon' => 'bi bi-pencil',
            ];
        }

        if ($id !== null && isset($routes['delete']) && in_array('delete', $allowed, true)) {
            $actions[] = [
                'key' => 'delete',
                'label' => __('ui.datatable.delete'),
                'method' => 'DELETE',
                'url' => route($routes['delete'], $id),
                'style' => 'danger',
                'icon' => 'bi bi-trash',
                'confirm' => __('web/inference.confirm.delete'),
            ];
        }

        $row['actions'] = $actions;

        return $row;
    }
}
