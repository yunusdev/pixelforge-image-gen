# 02: Price estimate in the prompt bar

**What to build:** On the Create page a user types a prompt, picks size, quality and up to 4 reference URLs in the floating prompt bar, and sees the estimate as a low–high range that updates as they type. Clicking the range opens the itemised receipt (prompt, reference images, image output, thinking, 25% service fee) and states "never more than" the top. Invalid input is explained in one line and Generate stays disabled. Generate does nothing yet beyond validation.

**Blocked by:** 01

**Status:** ready-for-agent

**Needs from the developer first:** nothing (Pixelforge's pricing endpoint needs no API key).

- [ ] A pure pricing module turns settings plus pricing rules into an estimate (low and high charge in nanodollars) and turns usage plus rates into a provider cost and an actual charge, using integers only (ADR-0001)
- [ ] Rates and token rules come from Pixelforge's public pricing endpoint, cached briefly, never hardcoded
- [ ] **Money test, exact cost:** the docs' worked example (high, 1536x1024, 400 characters, 2 references) gives a maximum provider cost of $0.373875000 and a top charge of $0.467343750
- [ ] **Money test, exact cost:** the charge is the provider cost × 1.25, rounded up to the nanodollar; the low end rounds down and the high end rounds up for display
- [ ] **Money test, exact cost:** the actual charge is capped at the estimate's top, and the capped flag and absorbed amount are returned (ADR-0002)
- [ ] Text tokens count Unicode characters, not bytes; 0–4 references and every size/quality pair are covered by tests
- [ ] Validation: empty prompt, over 4,000 characters, more than 4 references, and non-http(s) URLs each show their own message, with the bad URL marked
- [ ] Amounts show in cents with the exact value on hover
