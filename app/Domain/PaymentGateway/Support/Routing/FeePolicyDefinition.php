<?php

namespace App\Domain\PaymentGateway\Support\Routing;

readonly class FeePolicyDefinition
{
    public function __construct(
        public ?int $flatIdr,
        public ?float $percent,
        public bool $vatInclusive = false,
    ) {}

    public static function fromMixed(mixed $fee, bool $vatInclusive = false): self
    {
        if (is_int($fee) || (is_float($fee) && floor($fee) === $fee)) {
            return new self(flatIdr: (int) $fee, percent: null, vatInclusive: $vatInclusive);
        }

        if (is_string($fee) && preg_match('/^(\d+(?:\.\d+)?)\s*%$/', trim($fee), $matches)) {
            return new self(flatIdr: null, percent: (float) $matches[1], vatInclusive: $vatInclusive);
        }

        throw new \InvalidArgumentException('Fee must be a flat IDR integer or a percentage string like "0.7%".');
    }

    public function isFlat(): bool
    {
        return $this->flatIdr !== null;
    }

    public function isPercent(): bool
    {
        return $this->percent !== null;
    }
}
