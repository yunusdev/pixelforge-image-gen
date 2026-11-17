# Money is integer nanodollars; the charge rounds up

Pixelforge's costs are exact fractions of a cent (e.g. `0.000575000`) and never rounded, so we store every amount (provider cost, charge, estimate, ledger entries) as an integer count of nanodollars. Floats would drift, and decimal strings would add friction to every comparison and sum. Provider cost is always a whole number of nanodollars because rates have at most two decimals per 1M tokens. The 25% markup can leave a fraction of a nanodollar, so the charge rounds **up**. We're never undercharged, and the user overpays by at most $0.000000001 per generation.

## Considered Options

- Round half-up: sometimes undercharges by a fraction of a nanodollar.
- Arbitrary-precision decimals (12+ places) so nothing is ever rounded: exact, but every sum and comparison in the codebase pays for it.
