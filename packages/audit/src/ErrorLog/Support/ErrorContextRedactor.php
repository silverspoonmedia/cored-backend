<?php

namespace App\Domain\ErrorLog\Support;

/**
 * Recursively redacts sensitive keys from error context payloads.
 */
class ErrorContextRedactor
{
    private const REDACTED = '[redacted]';

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function redact(array $context): array
    {
        $keys = array_map(
            static fn (string $key): string => strtolower($key),
            config('errorlog.redact_keys', []),
        );

        return $this->walk($context, $keys);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $redactKeys
     * @return array<string, mixed>
     */
    private function walk(array $data, array $redactKeys): array
    {
        $out = [];

        foreach ($data as $key => $value) {
            $stringKey = is_string($key) ? $key : (string) $key;

            if (in_array(strtolower($stringKey), $redactKeys, true)) {
                $out[$stringKey] = self::REDACTED;

                continue;
            }

            if (is_array($value)) {
                /** @var array<string, mixed> $value */
                $out[$stringKey] = $this->walk($value, $redactKeys);

                continue;
            }

            $out[$stringKey] = $value;
        }

        return $out;
    }
}
