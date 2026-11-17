# Affordability uses available balance; charges are capped at the estimate's top

The actual charge isn't known until a generation finishes, yet we must block generating when the user can't afford it. A user's **available balance** is their balance minus the top of the estimate for each of their generations still in progress, worked out on the spot rather than stored as a hold. A new generation is allowed only if its estimate's top fits within it. This stops a burst of quick clicks from spending more than the balance. The balance itself changes only when a generation succeeds (by its actual charge) or credits are added.

Rates are locked by Pixelforge when it *accepts* a generation, which can be later than when we showed the estimate (e.g. after waiting for a slot). If rates rose in between, the actual charge would exceed the top of the estimate. We cap the actual charge at the estimate's top and absorb the difference: the user is never charged more than they were shown and the balance never goes negative. Capped generations are marked and surfaced in the admin report so lower-than-25% profit is explained.

## Considered Options

- Check the balance only, charge at the end: concurrent submissions can drive the balance negative.
- Charge the true amount even above the shown top: keeps "cost + exactly 25%" but breaks the promise shown to the user and allows negative balances.
