<?php

namespace App\Domain\Approval\Services;

use Illuminate\Database\Eloquent\Model;

class ConditionEvaluator
{
    /**
     * Evaluate a condition against a document model.
     *
     * @param  array|null  $condition  JSON condition: {"field": "total", "op": ">=", "value": 10000}
     * @param  Model  $document  The document to evaluate against
     * @return bool True if condition passes or is null (always active)
     */
    public function evaluate(?array $condition, Model $document): bool
    {
        if ($condition === null) {
            return true;
        }

        $field = data_get($document, $condition['field']);
        $operator = $condition['op'];
        $expected = $condition['value'];

        return match ($operator) {
            '=' => $field == $expected,
            '!=' => $field != $expected,
            '>' => $field > $expected,
            '>=' => $field >= $expected,
            '<' => $field < $expected,
            '<=' => $field <= $expected,
            'in' => in_array($field, (array) $expected),
            'not_in' => ! in_array($field, (array) $expected),
            'contains' => str_contains((string) $field, (string) $expected),
            'starts_with' => str_starts_with((string) $field, (string) $expected),
            'ends_with' => str_ends_with((string) $field, (string) $expected),
            default => throw new \InvalidArgumentException("Unknown operator: {$operator}"),
        };
    }
}
