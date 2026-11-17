# 06: Balances: ledger, Credits page and affordability

**What to build:** Every user starts with $5. They can see their available credits, their balance, and what's set aside for running images, and buy $5, $10 or $25 packs with one click (fake). A ledger explains every change, and a 30-day spending chart is shown. Generate is blocked with "Not enough credits" when the available balance can't cover the top of the estimate, even with rapid clicks.

**Blocked by:** 04

**Status:** resolved

**Needs from the developer first:** nothing.

- [x] Signing up writes a $5.00 sign-up bonus ledger entry
- [x] A succeeded generation writes exactly one ledger entry for its actual charge, in the same transaction as recording the charge, enforced by a unique constraint per generation
- [x] Buying a pack writes one ledger entry, shows a confirmation toast, and highlights the new line
- [x] The cached balance on the user always equals the sum of their ledger entries
- [x] Available balance = balance − the estimate top of each of the user's unfinished generations. It's checked under a lock on the user at submit time (ADR-0002)
- [x] **Money test, never twice:** five rapid submits with $0.50 available and a $0.47 top allow one and block the rest
- [x] **Money test, failed is free:** a failed generation adds no ledger entry and frees its set-aside amount
- [x] **Money test, exact cost:** the balance drops by exactly the actual charge (capped where applicable)
- [x] The Credits page uses the Sidebar layout: section menu, credits card with Top up, spending chart, pack buttons, paginated ledger, and empty states for a first-time user
- [x] The "Available" button in the top bar and the account menu show the available balance

## Comments

- Ledger: `App\Credits\Ledger` (append-only, cached `users.balance` updated in the same transaction; `ledger_entries.generation_id` unique). Charge entry written inside `GenerationLifecycle::recordSuccess()`. Factory users also get the sign-up bonus.
- Affordability: `App\Credits\Affordability::startIfAffordable()` locks the user row (`SELECT … FOR UPDATE`) while checking and saving. The rapid-clicks test runs the five submits one after another; true parallel requests rely on the Postgres row lock (not exercised in tests, since RefreshDatabase wraps each test in one transaction).
- The spending chart groups by UTC day; ledger dates show in the browser's local time.
- Needs developer: check the prompt bar's "Not enough credits" line and the Credits page on the deployed app once real Pixelforge pricing is reachable (locally the Create page has no pricing without a key; server-side blocking is covered by tests).
