<?php

namespace App\Services\Currency;

use App\Models\ExchangeRate;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CurrencyRateService
{
    private const USD = 'USD';

    public const SOURCE_DEFAULT = 'default';
    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_API = 'api';

    public function effectiveCurrency(?User $user): string
    {
        return optional($user)->default_currency ?: config('hisabi.currency');
    }

    public function supportedCurrencies(): array
    {
        return array_map(
            static fn(string $currency): array => [
                'value' => $currency,
                'label' => $currency,
            ],
            $this->supportedCurrencyValues(),
        );
    }

    public function ensureUserRates(User $user): Collection
    {
        $existingCurrencies = $user->exchangeRates()
            ->pluck('currency')
            ->all();

        $missingCurrencies = array_values(array_diff($this->supportedCurrencyValues(), $existingCurrencies));

        if ($missingCurrencies !== []) {
            $timestamp = now();

            $user->exchangeRates()->insert(array_map(
                fn(string $currency): array => [
                    'user_id' => $user->id,
                    'currency' => $currency,
                    'rate' => 1,
                    'source' => self::SOURCE_DEFAULT,
                    'last_synced_at' => $currency === self::USD ? $timestamp : null,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ],
                $missingCurrencies,
            ));
        }

        return $user->exchangeRates()->orderBy('currency')->get();
    }

    public function getRateMap(User $user): array
    {
        return $this->ensureUserRates($user)
            ->mapWithKeys(fn(ExchangeRate $rate) => [$rate->currency => (float) $rate->rate])
            ->all();
    }

    public function convert(User $user, float $amount, ?string $fromCurrency, ?string $toCurrency = null): float
    {
        $fromCurrency = strtoupper($fromCurrency ?: self::USD);
        $toCurrency = strtoupper($toCurrency ?: $this->effectiveCurrency($user));

        if ($fromCurrency === $toCurrency) {
            return round($amount, 2);
        }

        $rateMap = $this->getRateMap($user);

        $fromRate = $rateMap[$fromCurrency] ?? ($fromCurrency === self::USD ? 1.0 : null);
        $toRate = $rateMap[$toCurrency] ?? ($toCurrency === self::USD ? 1.0 : null);

        if (! $fromRate || ! $toRate) {
            throw new RuntimeException("Missing exchange rate for {$fromCurrency} or {$toCurrency}.");
        }

        $amountInUsd = $fromCurrency === self::USD ? $amount : $amount / $fromRate;
        $convertedAmount = $toCurrency === self::USD ? $amountInUsd : $amountInUsd * $toRate;

        return round($convertedAmount, 2);
    }

    public function updateManualRates(User $user, array $rates): Collection
    {
        $normalizedRates = collect($rates)
            ->filter(fn($rate) => isset($rate['currency'], $rate['rate']))
            ->map(fn(array $rate): array => [
                'currency' => strtoupper((string) $rate['currency']),
                'rate' => (float) $rate['rate'],
            ])
            ->keyBy('currency');

        $normalizedRates->put(self::USD, [
            'currency' => self::USD,
            'rate' => 1.0,
        ]);

        $this->ensureUserRates($user);

        foreach ($normalizedRates as $currency => $payload) {
            if (! in_array($currency, $this->supportedCurrencyValues(), true)) {
                continue;
            }

            $user->exchangeRates()->updateOrCreate(
                ['currency' => $currency],
                [
                    'rate' => $currency === self::USD ? 1.0 : $payload['rate'],
                    'source' => self::SOURCE_MANUAL,
                    'last_synced_at' => now(),
                ],
            );
        }

        return $user->exchangeRates()->orderBy('currency')->get();
    }

    public function refreshRates(User $user): Collection
    {
        $this->ensureUserRates($user);

        $symbols = array_values(array_filter(
            $this->supportedCurrencyValues(),
            fn(string $currency): bool => $currency !== self::USD,
        ));

        $response = Http::baseUrl(config('services.frankfurter.base_url', 'https://api.frankfurter.app'))
            ->timeout(15)
            ->acceptJson()
            ->get('/latest', [
                'from' => self::USD,
                'symbols' => implode(',', $symbols),
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Unable to refresh exchange rates from the provider.');
        }

        $rates = Arr::wrap($response->json('rates'));
        $timestamp = now();

        $user->exchangeRates()->updateOrCreate(
            ['currency' => self::USD],
            [
                'rate' => 1.0,
                'source' => self::SOURCE_API,
                'last_synced_at' => $timestamp,
            ],
        );

        foreach ($rates as $currency => $rate) {
            if (! in_array($currency, $this->supportedCurrencyValues(), true)) {
                continue;
            }

            $user->exchangeRates()->updateOrCreate(
                ['currency' => $currency],
                [
                    'rate' => (float) $rate,
                    'source' => self::SOURCE_API,
                    'last_synced_at' => $timestamp,
                ],
            );
        }

        return $user->exchangeRates()->orderBy('currency')->get();
    }

    private function supportedCurrencyValues(): array
    {
        return array_values((array) config('hisabi.supported_currencies', [self::USD]));
    }

    public function currenciesPayload(User $user): array
    {
        $rates = $this->ensureUserRates($user);
        $syncedRates = $rates->filter(fn(ExchangeRate $rate): bool => $rate->last_synced_at !== null);
        $latestSyncedAt = $syncedRates->isNotEmpty()
            ? $syncedRates->max(fn(ExchangeRate $rate) => $rate->last_synced_at->timestamp)
            : null;

        return [
            'settings' => [
                'default_currency' => $user->default_currency,
                'effective_currency' => $this->effectiveCurrency($user),
            ],
            'defaults' => [
                'currency' => config('hisabi.currency'),
            ],
            'options' => [
                'currencies' => $this->supportedCurrencies(),
            ],
            'rates' => $rates->map(fn(ExchangeRate $rate): array => [
                'currency' => $rate->currency,
                'label' => $rate->currency,
                'rate' => (float) $rate->rate,
                'source' => $rate->source,
                'last_synced_at' => $rate->last_synced_at ? $rate->last_synced_at->toIso8601String() : null,
            ])->values()->all(),
            'last_refreshed_at' => $latestSyncedAt ? Carbon::createFromTimestamp($latestSyncedAt)->toIso8601String() : null,
        ];
    }
}
