/**
 * Display helpers for amounts in integer nanodollars (ADR-0001).
 * Users see cents; the exact value goes in a hover title.
 */

const NANODOLLARS_PER_CENT = 10_000_000;

export type CentRounding = 'down' | 'up' | 'nearest';

function toCents(nanodollars: number, rounding: CentRounding): number {
    const whole = Math.trunc(nanodollars / NANODOLLARS_PER_CENT);
    const rest = nanodollars - whole * NANODOLLARS_PER_CENT;

    if (rest === 0) {
        return whole;
    }

    if (rounding === 'down') {
        return rest > 0 ? whole : whole - 1;
    }

    if (rounding === 'up') {
        return rest > 0 ? whole + 1 : whole;
    }

    return Math.abs(rest) * 2 >= NANODOLLARS_PER_CENT
        ? whole + Math.sign(rest)
        : whole;
}

/** "$0.47": the amount in cents, rounded the given way. */
export function formatCents(
    nanodollars: number,
    rounding: CentRounding = 'nearest',
): string {
    const cents = toCents(nanodollars, rounding);
    const sign = cents < 0 ? '-' : '';
    const abs = Math.abs(cents);

    return `${sign}$${Math.trunc(abs / 100).toLocaleString('en-US')}.${String(abs % 100).padStart(2, '0')}`;
}

/** "$0.467343750": the exact amount with all 9 decimals. */
export function formatExact(nanodollars: number): string {
    const sign = nanodollars < 0 ? '-' : '';
    const digits = String(Math.abs(nanodollars)).padStart(10, '0');

    return `${sign}$${digits.slice(0, -9)}.${digits.slice(-9)}`;
}
