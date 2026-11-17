Status: ready-for-agent

# Spec: Image generation with exact per-generation charging

## Problem Statement

People want to generate images from text prompts and know what each one costs. The image provider, Pixelforge, is asynchronous, is billed per token, never tells you a price, and only reveals part of the token usage after a generation finishes. It also misbehaves like a real provider: it rate-limits, allows only 3 generations in flight per account, overloads, fails generations after accepting them, and delivers webhooks more than once, out of order, or not at all.

As a user, I can't tell what a generation will cost before I submit it, what's happening to it after I submit, or what I was actually charged. As the operator, I can't tell what users were charged, what Pixelforge charged us, or whether the two reconcile.

## Solution

A Laravel + Vue + Inertia web app where signed-in users write a prompt, pick a size, a quality and up to 4 reference image URLs, and see the estimate (a low–high range for the charge) before they submit. Generating and history share one Create page: a feed of the user's images with a floating prompt bar at the bottom. Each generation shows live status: Waiting for a slot, queued, processing, succeeded or failed, with its estimate, its actual charge and the image.

Every succeeded generation is charged exactly once: its provider cost plus a 25% markup, rounded up to the nearest nanodollar, never above the top of its estimate. Failed generations are free. Users have a balance, backed by a ledger, that starts with a $5 sign-up bonus and can be topped up with fake credit packs. A generation is blocked when the user's available balance can't cover the top of its estimate.

Operators get an admin report of charges, provider costs, profit, capped charges and estimate accuracy, per UTC day (by completion) and per user. Each day is checked against Pixelforge's own usage numbers.

The app is deployed publicly on Laravel Cloud.

## User Stories

### Account

1. As a visitor, I want to sign up with email and password, so that I can start generating.
2. As a visitor, I want to use the app immediately after signing up, without verifying my email, so that I can try it right away.
3. As a new user, I want to receive a $5 sign-up bonus, so that I can generate images before buying credits.
4. As a user, I want to log in and out, so that my generations and balance are private to me.
5. As a user, I want to see only my own generations and ledger, so that other users' activity is never exposed to me.

### Generating

6. As a user, I want to write a prompt of up to 4,000 characters, so that I can describe the image I want.
7. As a user, I want to choose a size (1024x1024, 1536x1024 or 1024x1536), so that I get the shape I need.
8. As a user, I want to choose a quality (low, medium or high), so that I can trade cost against quality.
9. As a user, I want to add up to 4 reference image URLs (http or https), so that I can guide the style.
10. As a user, I want sensible defaults (1024x1024, medium, no reference images), so that I can generate with just a prompt.
11. As a user, I want to see the estimate as a low–high range that updates as I change the prompt, size, quality or reference images, so that I know what I'm committing to before I submit.
12. As a user, I want the estimate to state clearly that I'll never be charged more than its top, so that I can trust it.
13. As a user, I want invalid input (empty prompt, too long, more than 4 URLs, a non-http URL) explained in one plain line in the prompt bar, with the bad reference URL marked and Generate disabled until it's fixed, so that I don't waste a generation.
14. As a user, I want my generation to appear at the top of the feed immediately after I submit, with a short confirmation of how much was set aside, so that I know it was received.
15. As a user, I want the Generate action blocked with a clear "not enough credits" message when my available balance is below the top of the estimate, so that I can't overspend.

### Status and history

16. As a user, I want each generation to show its status (Waiting for a slot, queued, processing, succeeded or failed), so that I know what's happening.
17. As a user, I want statuses to update on their own while any generation is in progress, so that I don't have to refresh.
18. As a user, I want "Waiting for a slot" shown while Pixelforge is busy and the app is retrying, so that I understand why it hasn't started.
19. As a user, I want the image shown when a generation succeeds, so that I can see the result.
20. As a user, I want an "Image expired" placeholder once Pixelforge's image URL has expired (24 hours after completion), so that the history still makes sense.
21. As a user, I want each generation to show its estimate and, once succeeded, its actual charge, so that I can compare them.
22. As a user, I want amounts shown in cents with the exact amount on hover, so that the page is readable but nothing is hidden.
23. As a user, I want a failed generation to show $0 and explain why it failed in plain words, so that I know it was free and what went wrong.
24. As a user, I want different messages for a prompt Pixelforge couldn't render, a problem on Pixelforge's side, a blocked prompt, the daily limit being reached, and Pixelforge being too busy, so that I know whether to reword, wait or retry.
25. As a user, I want a "Try again" button on failed generations that copies their settings into the prompt bar, so that I see the fresh estimate and retry with one more click.
26. As a user, I want my images listed newest first, 20 at a time with a "Load 20 more" button, so that recent work is easy to find.
27. As a user, I want to see the prompt, size, quality and reference images of each past generation, so that I remember what I asked for.

### Balance and credits

28. As a user, I want to see my balance and my available balance, so that I know what I can spend right now.
29. As a user, I want to buy a $5, $10 or $25 credit pack with one click (fake, no payment), so that I can keep generating.
30. As a user, I want my ledger (sign-up bonus, credit purchases, generation charges) listed with amounts and dates, so that I can see how my balance got where it is.
31. As a user, I want my balance reduced only when a generation succeeds, by exactly its actual charge, so that I never pay for failures.
32. As a user, I want my available balance to drop by the top of the estimate while a generation is in progress, and recover when it finishes, so that I can't accidentally overspend with rapid clicks.
33. As a user, I want never to be charged more than the top of the estimate I was shown, so that the estimate is a promise.

### Robustness (seen by the user as "it just works")

34. As a user, I want my generation to be submitted automatically once Pixelforge has a free slot, so that I don't have to babysit it.
35. As a user, I want a generation that can't get a slot within 10 minutes, or that keeps hitting overload or rate limits after 5 tries, to fail for free with a clear message, so that it never hangs forever.
36. As a user, I want my generation to reach its final state even if Pixelforge's webhook never arrives, so that nothing gets stuck in processing.
37. As a user, I want to be charged exactly once even if Pixelforge sends the same event twice, so that duplicates never cost me.
38. As a user, I want a late "processing" event never to undo a finished generation, so that what I see is correct.

### Admin

39. As an operator, I want to make a user an admin only with a server command, so that no one can promote themselves through the app.
40. As an operator, I want admin pages to refuse non-admins on the server, so that user spending stays private.
41. As an operator, I want a report per UTC day (by completion) showing what users were charged, what Pixelforge charged us, and the profit, so that I can see the business.
42. As an operator, I want the same figures per user, so that I can see who spends what.
43. As an operator, I want each day's provider cost shown next to what Pixelforge's usage report says, with a ✓ when they match and a warning when they don't, so that I can trust our records.
44. As an operator, I want the report to default to the last 30 days, with a date picker up to 92 days, so that it lines up with Pixelforge's usage limits.
45. As an operator, I want amounts in the report shown with all 9 decimal places, so that I can compare them exactly with Pixelforge.
46. As an operator, I want capped charges counted and their absorbed amount shown per day and per user, so that lower-than-25% profit is explained.
47. As an operator, I want to see the summed estimate range next to the actual charge, and where actuals landed within the range on average, so that I know how good our estimates are.
48. As an operator, I want to drill into a day or a user and see each generation with its estimate range, actual charge, a capped marker and where it landed in the range, so that I can investigate.
49. As a grader, I want a ready-made admin login in the README, so that I can open the report without asking.

### Operations

50. As an operator, I want webhooks with a missing or invalid signature, or a timestamp older than 5 minutes, rejected, so that no one can forge events.
51. As an operator, I want validly signed webhooks for unknown generations (including test pings) acknowledged with "OK" and logged, so that Pixelforge stops retrying and I have a record.
52. As an operator, I want separate Pixelforge API keys, webhook URLs and signing secrets for local and deployed environments, so that each environment's numbers match Pixelforge's usage report for its key.

### Interface details (from the chosen design)

53. As a user, I want to filter my images by All, Running, Done or Failed, so that I can find failures quickly.
54. As a user, I want to open any image in a side panel showing its status, settings, what I paid and where that landed in the estimate, so that each cost is explained.
55. As a user, I want a "Use these settings" action on any image, so that I can make variations without retyping.
56. As a user, I want the price range in the prompt bar to open an itemised breakdown (prompt, reference images, image output, thinking, 25% service fee), so that I understand what drives the price.
57. As a new user, I want an empty state that tells me my starting credits and offers example prompts, so that I know what to do first.
58. As a user, I want images in progress to show a calm loading animation with how long they've been waiting, so that I can tell the app is working.
59. As a user, I want a short confirmation after buying a credit pack, and the new ledger line highlighted, so that I know it worked.
60. As a user, I want a spending chart for the last 30 days on the Credits page, so that I can see my usage at a glance.
61. As a user, I want an account menu with my name, email, available credits and Log out, so that account actions are in one place.
62. As a user on a phone, I want a menu button that shows my balance and the pages, so that I can use the app on a small screen.
63. As a non-admin, I want the Admin report link hidden, and an "Admins only" page if I open it directly, so that it's clear why I can't see it.
64. As an operator, I want the "By user" and per-image tables paginated 20 at a time, with totals at the top always covering the whole date range, so that paging never changes the totals.
65. As an operator, I want a clear "Couldn't check" state when Pixelforge's usage report doesn't respond, with our own numbers still shown and a Try again button, so that a provider outage isn't mistaken for wrong data.
66. As an operator, I want empty date ranges to say so plainly, so that a blank table isn't mistaken for an error.

## Implementation Decisions

### Stack

- Laravel's official Vue starter kit (Inertia, TypeScript, shadcn-vue, built-in auth), with email verification off.
- Postgres in every environment.
- The database as the queue driver, so there's no Redis.
- Deployed on Laravel Cloud with a web process, a queue worker and the scheduler.
- Pixelforge credentials (API key, webhook signing secret, base URL) come from config and environment variables, one key per environment.

### UI (design reference: lab version D)

- The design reference is `lab/d-feed-minimal/` (open it via `lab/index.html`, tab D), with the **Sidebar** layout for Credits and Admin report. It runs on fake data; the real pages rebuild it with shadcn-vue components themed to match.
- **Look:** warm monochrome. Off-white background, charcoal text, 1px light-grey borders, 8–12px corners, pastel status tags (grey waiting, blue queued, yellow processing, green done, red failed). Fonts are Newsreader for headings, Geist for interface text, and Geist Mono for every amount, count and timer.
- **Top bar on every page:** logo; Create · Credits · Admin report (the admin link only for admins); an "Available $x.xx" button linking to Credits; an initials account menu. Below 720px this becomes one Menu button.
- **Create page:**
  - Full-width masonry feed of image tiles, newest first. Each tile has a status tag, the prompt and its price ("≤ max" while running, charged amount when done, "$0.00 free" when failed). Failed tiles are compact and name the failure.
  - Status filter chips; "Load 20 more" with "Showing x of y".
  - A floating prompt bar holds the prompt, chips for size, quality and references (each opens a small popover), a character counter, a price-range chip that opens the itemised receipt, and Generate.
  - Validation and not-enough-credits messages appear as one line inside the bar.
- **Loading tiles:** waiting gets a slow light sweep, queued a faster sweep, processing soft drifting colour blobs. All carry a pill with a live elapsed timer. Motion is off when reduced motion is set.
- **Side panel per image:** the image or placeholder, status, settings, the charge with a marker showing where it landed between the low estimate and the max, the capped note, the failure explanation, and "Try again" / "Use these settings".
- **Credits (Sidebar layout):** a left section menu (Overview, Add credits, Ledger) and a centred column up to 1040px containing the credits card (available, balance, set aside, Top up), the 30-day spending chart, the $5/$10/$25 pack buttons, and the paginated ledger.
- **Admin report (Sidebar layout):**
  - A left section menu (Overview, By day, By user, Images) and the date range.
  - Four totals: charged, Pixelforge cost, profit, matches Pixelforge.
  - The by-day table is unpaginated (max 92 rows), with match, mismatch, capped and not-checked tags kept on one line.
  - The by-user table is paginated.
  - The images table is paginated and filters to a clicked day or user, with "Show all".
- **Toasts** (top right) confirm Generate, credit purchases, and copied settings.
- **Empty states:** no images yet (with example prompts); an empty filter result; a ledger with only the sign-up bonus; no spending yet; a report range with no data.

### Money (ADR-0001, ADR-0002)

- Every amount is an integer number of nanodollars: rates, provider cost, estimate low and high, charge, ledger entries and balances. No floats anywhere in the money path.
- Rates are dollars per 1M tokens. Per-token nanodollars are `rate × 1000`, exact because rates have at most 2 decimals. Provider cost is `Σ tokens × per-token nanodollars`.
- Charge is `ceil(provider cost × 125 / 100)`, using integer arithmetic.
- Estimate:
  - **Text tokens:** `ceil(characters / 4)` counting Unicode characters, then low = floor(85%) and high = ceil(115%).
  - **Image tokens:** 765 per reference image (exact).
  - **Output tokens:** exact, from the pricing table by quality and size.
  - **Reasoning tokens:** the quality's min and max.
  - Low and high each go through the markup. The low end rounds down for display and the high end rounds up.
  - Rates and token rules are read live from Pixelforge's public pricing endpoint (cached briefly), never hardcoded.
- Actual charge uses the generation's real usage and the rates Pixelforge returned on it (locked at acceptance), then is capped at the estimate's top. A capped flag and the absorbed amount are stored on the generation.
- Display: cents for users, with the exact value available on hover. The admin report shows all 9 decimals.

### Domain modules (deep modules with small interfaces)

- **Pricing:** pure functions. Settings plus pricing rules give an estimate (low and high charge, plus the underlying provider-cost range). Usage plus rates give the provider cost. Provider cost plus the estimate top give the actual charge and the capped flag. No I/O.
- **Pixelforge client:** a thin HTTP wrapper over create generation (with Idempotency-Key and metadata), retrieve generation, get pricing and get usage. It turns Pixelforge's error responses into typed outcomes: temporary (concurrency limit, overloaded, rate limit, with Retry-After), permanent (content policy, validation, daily limit), and auth errors. It's swappable for a fake in tests.
- **Generation lifecycle:** the single place that moves a generation between statuses. Webhooks, the submit job and the stuck-generation sweep all call it. It accepts a Pixelforge generation snapshot, only moves forward (Waiting for a slot → queued → processing → succeeded or failed, with succeeded and failed final), and on the move to succeeded records the actual charge and writes one ledger entry, all in one transaction with the generation row locked.
- **Ledger:** append-only entries (sign-up bonus, credit pack purchase, generation charge), each with an amount in signed nanodollars, a reason, a time, and an optional link to a generation. A cached balance on the user updates in the same transaction as each entry. Each generation has at most one charge entry, enforced by a unique constraint.
- **Affordability:** available balance = balance − Σ estimate high of the user's generations not yet final. It's checked under a row lock on the user at submit time.
- **Admin report:** a read model. It groups succeeded generations by UTC completion date and by user, sums charge, provider cost, profit, capped count and absorbed amount, and estimate low/high against actual. It fetches Pixelforge usage for the same range and compares per day.

### Submitting (save first, send in the background)

- On submit, the generation is created locally in the Waiting for a slot status, with its settings, estimate (low and high), and a UUID used as both the Idempotency-Key and `metadata` for matching webhooks. Then a queued job is dispatched.
- The job calls create generation and stores the Pixelforge id, status and rates from the response.
- Temporary errors:
  - Concurrency limit: retry after Retry-After (5s) until 10 minutes have passed since submission.
  - Overloaded and rate limit: up to 5 attempts, waiting at least Retry-After and backing off further each time.
  - Retries reuse the same Idempotency-Key, so nothing is ever created or billed twice.
  - When retries run out, the generation fails for free with a "Pixelforge was too busy" reason.
- Permanent errors (content policy, validation, daily limit) fail the generation immediately for free, with a specific reason.
- A webhook may arrive before the create call returns. It matches the local generation by the Pixelforge id or the metadata UUID.

### Webhooks (ADR-0003)

- A single endpoint outside CSRF/web middleware.
- It verifies the HMAC-SHA256 of `<timestamp>.<raw body>` with the signing secret, compares in constant time, and rejects timestamps more than 300 seconds from now with a 400.
- In one transaction, it inserts the event id into a processed-events table with a unique constraint (if it's already present, it returns 2xx and does nothing), then passes the event's generation snapshot to the lifecycle module.
- Unknown generations and `ping` events return 2xx and are logged.
- It responds within milliseconds, with no queued processing.

### Missed webhooks

- A scheduled command runs every minute. It finds generations that aren't final and have had no update for more than 2 minutes and have a Pixelforge id, fetches each one from Pixelforge, and passes the snapshot to the lifecycle module.
- It respects the per-minute rate limit by bounding how many it fetches each run.

### Live status

- The Create page uses Inertia polling every 2–3 seconds, only while any visible generation isn't final, and reloads only the generations prop.

### Images

- Not downloaded. The image URL and its expiry are stored, and the UI shows a placeholder once the expiry has passed.

### Failure handling

- `generation_failed` and `internal_error` mark the generation failed for free, with distinct user-facing messages. There's no automatic retry. "Try again" copies the failed generation's settings into the prompt bar; pressing Generate then creates a new generation with a fresh estimate and affordability check.

### Schema (shape, not final names)

- **Users:** adds an admin flag (only settable by a server command) and a cached balance in nanodollars.
- **Generations:**
  - Owner, local UUID, Pixelforge id (unique, nullable until accepted), status, prompt, size, quality, reference image URLs.
  - Estimate low and high, and estimate provider-cost low and high, all in nanodollars.
  - Rates snapshot (nanodollars per token, per type), usage token counts.
  - Provider cost, actual charge, capped flag, absorbed amount.
  - Error code and message, image URL and expiry.
  - Submit attempt bookkeeping, created/started/completed times (completed in UTC).
- **Ledger entries:** user, amount (signed nanodollars), reason, optional generation (unique when the reason is a generation charge), and created time.
- **Processed webhook events:** event id (unique), type, Pixelforge generation id, received time.

### Admin access

- A server command grants admin by email.
- A seeded grader admin account, documented in the README.
- Admin routes are guarded server-side.

### Admin report

- Default range is the last 30 days, max 92, in UTC days by completion date.
- Per day and per user: charged, provider cost, profit, capped count and absorbed amount, estimate sums against actual, and average position within the range.
- A per-day Pixelforge usage comparison with ✓ or a warning.
- A drill-down list of generations for a day or a user.
- The by-user and per-image lists are paginated 20 per page; totals always cover the whole range.
- If the Pixelforge usage call fails, the report still shows our numbers and marks each day "not checked" instead of hiding or guessing.

### Credits

- Fixed $5, $10 and $25 packs. Each purchase is one ledger entry.
- A $5 sign-up bonus ledger entry is written when the account is created.

## Testing Decisions

- **Good tests check external behaviour through a seam:** inputs in, observable results out (HTTP responses, database state, ledger totals). They don't touch internals. Tests are written first, using `/tdd`, at the agreed seams.
- **Pest** feature and unit tests run against Postgres. Pixelforge is replaced by a fake HTTP layer that can be scripted to return busy, overloaded, rate-limited, blocked, validation, daily-limit, accepted, failed or succeeded responses with given usage.
- **Agreed seams:**
  1. **Pricing calculator:** reproduces the docs' worked example exactly (maximum provider cost $0.373875 → charge top $0.46734375), rounds up, handles Unicode characters, the 0–4 reference image bounds and every size/quality combination, and caps the charge.
  2. **Pixelforge client (against the fake):** sends the Idempotency-Key and metadata, sends no unknown parameters, and maps each error code to the right temporary or permanent outcome along with its Retry-After.
  3. **Webhook endpoint:** checks the signature (valid, invalid, missing, stale timestamp). A duplicate event is recorded once and charged once. Events out of order never move a generation backward. A webhook arriving before the create call returns is handled. Unknown generations and pings get 2xx. Failed events cost nothing.
  4. **Submit flow:** blocked when the available balance is insufficient, including the rapid-clicks scenario. Each temporary error is retried, then gives up for free. Permanent errors fail immediately for free. The Idempotency-Key is reused across retries.
  5. **Ledger:** the balance always equals the sum of the entries. A failed generation adds no entry. A succeeded generation adds exactly one, even when the webhook and the sweep both process it.
  6. **Admin report:** grouped by UTC completion day (including the 23:59 → 00:00 case), compared against a faked usage response, with capped totals and estimate accuracy.
- **Every ticket carries tests for the three money rules where they apply:** exact cost, never twice, failed is free.
- Pages and forms get light feature tests only (authorization, validation, admin-only access, pagination bounds).
- **Prior art:** none, this is a greenfield repo. The starter kit's auth tests set the style.
- **Before deploying:** a manual end-to-end run against real Pixelforge using the prompt keywords `[fail]`, `[slow]`, `[duplicate]`, `[overloaded]`, `[blocked]` and `[succeed]`, then checking the admin report against `GET /v1/usage`.

## Out of Scope

- Real payments.
- Downloading or permanently storing images.
- Super-admin, or managing admins from the UI (parked for later).
- WebSockets and push updates.
- Automatic retry of failed generations.
- Email verification and transactional email.
- Multi-currency support.
- Treating Pixelforge price changes as anything beyond the cap.

## Further Notes

- **Glossary:** see the root `GLOSSARY.md`. ADRs: `docs/adr/0001` (integer nanodollars, round up), `0002` (available balance and capped charge), `0003` (webhooks processed in the request).
- **Build order:** core first (generate, pricing, history, webhooks, sweep, deploy), checked against `/v1/usage`. Then balances and the admin report.
- **UI:** `lab/` (committed) holds five design explorations on fake data. Version D with the Sidebar layout was chosen; see "UI (design reference: lab version D)" above. View it with `python3 -m http.server 4610 --directory lab`. The lab is reference only and is never served by the app.
- **Rate limits:** the 3-generation concurrency limit and the 60 requests/minute limit are per Pixelforge account, shared by all users and environments on that account.
- **Before implementing, the developer needs:**
  - a Pixelforge account with 2 API keys (local and deployed), each with its webhook URL and signing secret
  - a tunnel for local webhooks (ngrok)
  - a Laravel Cloud account connected to GitHub
  - a public GitHub repository
