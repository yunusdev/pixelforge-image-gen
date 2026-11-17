# 02: Price estimate in the prompt bar

**What to build:** On the Create page a user types a prompt, picks size, quality and up to 4 reference URLs in the floating prompt bar, and sees the estimate as a low–high range that updates as they type. Clicking the range opens the itemised receipt (prompt, reference images, image output, thinking, 25% service fee) and states "never more than" the top. Invalid input is explained in one line and Generate stays disabled. Generate does nothing yet beyond validation.

**Blocked by:** 01

**Status:** resolved

**Needs from the developer first:** nothing (Pixelforge's pricing endpoint needs no API key).

- [x] A pure pricing module turns settings plus pricing rules into an estimate (low and high charge in nanodollars) and turns usage plus rates into a provider cost and an actual charge, using integers only (ADR-0001)
- [x] Rates and token rules come from Pixelforge's public pricing endpoint, cached briefly, never hardcoded
- [x] **Money test, exact cost:** the docs' worked example (high, 1536x1024, 400 characters, 2 references) gives a maximum provider cost of $0.373875000 and a top charge of $0.467343750
- [x] **Money test, exact cost:** the charge is the provider cost × 1.25, rounded up to the nanodollar; the low end rounds down and the high end rounds up for display
- [x] **Money test, exact cost:** the actual charge is capped at the estimate's top, and the capped flag and absorbed amount are returned (ADR-0002)
- [x] Text tokens count Unicode characters, not bytes; 0–4 references and every size/quality pair are covered by tests
- [x] Validation: empty prompt, over 4,000 characters, more than 4 references, and non-http(s) URLs each show their own message, with the bad URL marked
- [x] Amounts show in cents with the exact value on hover

## Comments

**Done.** Nothing needs the developer for this ticket (the pricing endpoint is public).

- Pricing module: `app/Pricing/Pricing.php` (`estimate`, `providerCost`, `charge`, `actualCharge`) with value objects alongside. The text token variance is kept as parts per million so everything stays integer. A rate with more than 3 decimals per 1M tokens is refused rather than rounded, because it would not be a whole number of nanodollars per token.
- Pricing rules: `PricingRulesSource` fetches `GET /v1/pricing` through `App\Pixelforge\PixelforgeClient::pricing()` and caches it for 5 minutes. It also keeps a last-known copy, used if Pixelforge can't be reached. With neither, the prompt bar shows "Price unavailable" and Generate stays disabled. Ticket 03 should add its create/retrieve calls to the same `PixelforgeClient`.
- Estimate in the browser: the rules reach the Create page as the `pricing` Inertia prop. `resources/js/lib/pricing.ts` mirrors `Pricing::estimate()`, and `tests/Unit/Pricing/PricingMirrorTest.php` runs it under Node and checks it against PHP for all 45 size/quality/reference combinations plus the worked example. That test needs `node` (Node 22.18+ or 24) on PATH, which CI already has.
- Generate: `POST /generations` (`StoreGenerationRequest`) only validates for now and flashes an info toast. Ticket 03 replaces the body of `GenerationController::store`. The client-side messages in `resources/js/lib/generationSettings.ts` use the same wording as the server's.
- Tests now call `Http::preventStrayRequests()` in `tests/TestCase.php`, so a feature test that needs Pixelforge must `Http::fake()` it.
- The receipt lists Pixelforge's cost per line, then the 25% service fee as its own line, so the lines add up to the range.
