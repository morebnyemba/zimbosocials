<?php

namespace App\Services;

use App\Models\Setting;

/**
 * Display-currency conversion, USD-only internally. Balances, transactions,
 * and orders are always stored and computed in USD — this service only
 * converts a USD amount for on-screen display in a user's chosen currency.
 * Never use it to derive an amount that gets persisted or charged.
 */
class CurrencyService
{
    /**
     * Admin-configured non-USD currencies: code => ['symbol' => ..., 'rate' => units per 1 USD].
     *
     * @return array<string, array{symbol: string, rate: float}>
     */
    public function rates(): array
    {
        $raw = Setting::get('currency_rates', '{}');
        $decoded = json_decode((string) $raw, true);

        if (! is_array($decoded)) {
            return [];
        }

        $rates = [];
        foreach ($decoded as $code => $entry) {
            $code = strtoupper((string) $code);
            $rate = (float) ($entry['rate'] ?? 0);

            if ($code === '' || $code === 'USD' || $rate <= 0) {
                continue;
            }

            $rates[$code] = [
                'symbol' => (string) ($entry['symbol'] ?? $code),
                'rate' => $rate,
            ];
        }

        return $rates;
    }

    /** All selectable currency codes, USD always first. */
    public function supportedCodes(): array
    {
        return ['USD', ...array_keys($this->rates())];
    }

    /** Convert a USD amount into the given currency for display only. */
    public function convert(float $usdAmount, string $code): float
    {
        $code = strtoupper($code);
        if ($code === 'USD') {
            return $usdAmount;
        }

        $rate = $this->rates()[$code]['rate'] ?? null;

        return $rate ? round($usdAmount * $rate, 2) : $usdAmount;
    }

    /** Format a USD amount as a display string in the given currency. */
    public function format(float $usdAmount, string $code): string
    {
        $code = strtoupper($code);
        if ($code === 'USD') {
            return '$'.number_format($usdAmount, 2);
        }

        $entry = $this->rates()[$code] ?? null;
        if (! $entry) {
            return '$'.number_format($usdAmount, 2);
        }

        return $entry['symbol'].' '.number_format($usdAmount * $entry['rate'], 2);
    }
}
