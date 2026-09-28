<?php

namespace App\Domain\Vtual\Enums;

/**
 * Capability a delegated per-creator grant carries
 * (decision: docs/domains/vtual/reference-capability-grants.md).
 *
 * Ordered: `Edit` implies `View`. A future `manage` tier is a case here, not a
 * schema change — the column stores the string, and `atLeast()` compares rank.
 */
enum VtualGrantCapability: string
{
    case View = 'view';
    case Edit = 'edit';

    public function rank(): int
    {
        return match ($this) {
            self::View => 1,
            self::Edit => 2,
        };
    }

    /**
     * Does this capability satisfy the required one?
     */
    public function atLeast(self $required): bool
    {
        return $this->rank() >= $required->rank();
    }

    public function canMutate(): bool
    {
        return $this === self::Edit;
    }

    /**
     * Highest capability present in a mixed bag of raw values (one member may
     * hold several grants for the same creator through different rows/orgs).
     *
     * @param  iterable<mixed>  $values
     */
    public static function best(iterable $values): ?self
    {
        $best = null;

        foreach ($values as $value) {
            $capability = $value instanceof self ? $value : self::tryFrom((string) $value);

            if ($capability === null) {
                continue;
            }

            if ($best === null || $capability->rank() > $best->rank()) {
                $best = $capability;
            }
        }

        return $best;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
