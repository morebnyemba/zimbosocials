import { usePage } from '@inertiajs/react';

type CurrencyRates = Record<string, { symbol: string; rate: number }>;

/**
 * Display-currency formatting only. All amounts everywhere in the app are
 * USD internally (balances, transactions, orders) — this never converts a
 * value that gets sent back to the server, only what's shown on screen.
 */
export function useCurrency() {
    const rates = ((usePage().props as any).currencyRates ?? {}) as CurrencyRates;
    const code = ((usePage().props as any).auth?.user?.currency ?? 'USD') as string;

    function formatUSD(usdAmount: number): string {
        const amount = Number(usdAmount) || 0;

        if (code === 'USD') {
            return `$${amount.toFixed(2)}`;
        }

        const entry = rates[code];
        if (!entry) {
            return `$${amount.toFixed(2)}`;
        }

        return `${entry.symbol} ${(amount * entry.rate).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    }

    return { formatUSD, currencyCode: code };
}
