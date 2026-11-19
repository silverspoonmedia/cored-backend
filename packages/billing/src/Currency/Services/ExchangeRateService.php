<?php

namespace App\Domain\Currency\Services;

use App\Domain\Currency\Exceptions\ExchangeRateException;
use App\Domain\Currency\Models\Currency;
use App\Domain\Currency\Models\ExchangeRateSnapshot;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Frankfurter (ECB) FX client — display currency → settlement IDR.
 * Rates are persisted to exchange_rate_snapshots; Laravel cache is forever L1
 * with logical freshness via fetched_at (platform.frankfurter.cache_ttl).
 *
 * @see https://www.frankfurter.app/docs/
 * @see docs/platform/cache-policy.md
 */
class ExchangeRateService
{
    public const PROVIDER = 'frankfurter';

    public const SETTLEMENT_CURRENCY = 'IDR';

    /**
     * @return array{
     *     rate: float,
     *     provider: string,
     *     fetched_at: CarbonInterface,
     *     amount: float,
     *     from: string,
     *     to: string,
     *     snapshot_id: string|null
     * }
     */
    public function convert(float $amount, string $from, ?string $to = null): array
    {
        $from = strtoupper(trim($from));
        $to = strtoupper(trim($to ?? self::SETTLEMENT_CURRENCY));

        if ($from === $to) {
            return [
                'rate' => 1.0,
                'provider' => self::PROVIDER,
                'fetched_at' => now(),
                'amount' => $this->roundSettlementAmount($amount, $to),
                'from' => $from,
                'to' => $to,
                'snapshot_id' => null,
            ];
        }

        $resolved = $this->resolveRate($from, $to);

        return [
            'rate' => $resolved['rate'],
            'provider' => $resolved['provider'],
            'fetched_at' => $resolved['fetched_at'],
            'amount' => $this->roundSettlementAmount($amount * $resolved['rate'], $to),
            'from' => $from,
            'to' => $to,
            'snapshot_id' => $resolved['snapshot_id'],
        ];
    }

    public function rate(string $from, string $to): float
    {
        return $this->resolveRate($from, $to)['rate'];
    }

    /**
     * A DB or cache row carries `Carbon\Carbon` (the Eloquent cast), a fresh
     * fetch carries `Illuminate\Support\Carbon`; both are the same object graph,
     * so the shape declares the framework interface.
     *
     * @return array{rate: float, provider: string, fetched_at: CarbonInterface, snapshot_id: string|null}
     */
    private function resolveRate(string $from, string $to): array
    {
        $from = strtoupper(trim($from));
        $to = strtoupper(trim($to));

        if ($from === $to) {
            return [
                'rate' => 1.0,
                'provider' => self::PROVIDER,
                'fetched_at' => now(),
                'snapshot_id' => null,
            ];
        }

        $freshnessSeconds = (int) setting_config('services.frankfurter.cache_ttl', 3600);
        $cacheKey = "fx.frankfurter.{$from}.{$to}";

        /** @var array{rate: float, provider: string, fetched_at: string, snapshot_id: string|null}|null $cached */
        $cached = Cache::get($cacheKey);

        if (is_array($cached) && isset($cached['rate'])) {
            $fetchedAt = isset($cached['fetched_at'])
                ? Carbon::parse($cached['fetched_at'])
                : null;

            if ($fetchedAt !== null && $fetchedAt->gte(now()->subSeconds($freshnessSeconds))) {
                return [
                    'rate' => (float) $cached['rate'],
                    'provider' => (string) ($cached['provider'] ?? self::PROVIDER),
                    'fetched_at' => $fetchedAt,
                    'snapshot_id' => $cached['snapshot_id'] ?? null,
                ];
            }

            Cache::forget($cacheKey);
        }

        $freshDb = ExchangeRateSnapshot::query()
            ->where('base_currency', $from)
            ->where('quote_currency', $to)
            ->where('provider', self::PROVIDER)
            ->where('fetched_at', '>=', now()->subSeconds($freshnessSeconds))
            ->orderByDesc('fetched_at')
            ->first();

        if ($freshDb !== null) {
            $payload = [
                'rate' => (float) $freshDb->rate,
                'provider' => (string) $freshDb->provider,
                'fetched_at' => $freshDb->fetched_at?->toIso8601String() ?? now()->toIso8601String(),
                'snapshot_id' => $freshDb->id,
            ];
            Cache::forever($cacheKey, $payload);

            return [
                'rate' => (float) $freshDb->rate,
                'provider' => (string) $freshDb->provider,
                'fetched_at' => $freshDb->fetched_at ?? now(),
                'snapshot_id' => $freshDb->id,
            ];
        }

        $snapshot = $this->fetchAndPersist($from, $to);
        $payload = [
            'rate' => (float) $snapshot->rate,
            'provider' => (string) $snapshot->provider,
            'fetched_at' => $snapshot->fetched_at?->toIso8601String() ?? now()->toIso8601String(),
            'snapshot_id' => $snapshot->id,
        ];
        Cache::forever($cacheKey, $payload);

        return [
            'rate' => (float) $snapshot->rate,
            'provider' => (string) $snapshot->provider,
            'fetched_at' => $snapshot->fetched_at ?? now(),
            'snapshot_id' => $snapshot->id,
        ];
    }

    private function fetchAndPersist(string $from, string $to): ExchangeRateSnapshot
    {
        $baseUrl = rtrim((string) config('services.frankfurter.base_url', 'https://api.frankfurter.app'), '/');
        $timeout = (int) setting_config('services.frankfurter.timeout', 10);

        $response = Http::timeout($timeout)
            ->acceptJson()
            ->get("{$baseUrl}/latest", [
                'from' => $from,
                'to' => $to,
            ]);

        if (! $response->successful()) {
            throw new ExchangeRateException(__('api/currencies.fx_unavailable'));
        }

        $body = $response->json();
        $rates = is_array($body) ? ($body['rates'] ?? null) : null;

        if (! is_array($rates) || ! isset($rates[$to]) || ! is_numeric($rates[$to])) {
            throw new ExchangeRateException(__('api/currencies.fx_unavailable'));
        }

        $rate = (float) $rates[$to];

        if ($rate <= 0) {
            throw new ExchangeRateException(__('api/currencies.fx_unavailable'));
        }

        $asOf = null;
        if (is_array($body) && isset($body['date']) && is_string($body['date'])) {
            try {
                $asOf = Carbon::parse($body['date'])->toDateString();
            } catch (\Throwable) {
                $asOf = null;
            }
        }

        return ExchangeRateSnapshot::query()->create([
            'base_currency' => $from,
            'quote_currency' => $to,
            'rate' => $rate,
            'provider' => self::PROVIDER,
            'as_of_date' => $asOf,
            'fetched_at' => now(),
            'source_payload' => is_array($body) ? $body : null,
        ]);
    }

    private function roundSettlementAmount(float $amount, string $currency): float
    {
        $decimals = $this->decimalPlacesFor($currency);

        return (float) round($amount, $decimals);
    }

    private function decimalPlacesFor(string $currency): int
    {
        $code = strtoupper(trim($currency));

        $row = Currency::query()->where('code', $code)->first();

        if ($row !== null) {
            return max(0, (int) $row->decimal_places);
        }

        return $code === self::SETTLEMENT_CURRENCY || $code === 'IDR' ? 0 : 2;
    }
}
