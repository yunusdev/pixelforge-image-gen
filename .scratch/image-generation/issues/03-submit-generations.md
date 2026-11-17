# 03: Submit generations, with retries

**What to build:** A user presses Generate and the generation appears at the top of the feed straight away as "Waiting for a slot", with a toast saying how much is set aside. In the background it's sent to Pixelforge and moves to queued. While Pixelforge is busy, it keeps retrying on its own. If Pixelforge rejects it outright, or it can't get in, it fails for free with a plain explanation. The feed refreshes itself while anything is running, and clicking a tile opens the side panel, which has "Try again" and "Use these settings".

**Blocked by:** 02

**Status:** resolved

**Needs from the developer first:** a Pixelforge account and a local API key in `.env`.

- [x] Submitting saves the generation locally first (settings, estimate low/high, a UUID) and dispatches a background job; the web request returns at once
- [x] The Pixelforge client sends exactly the documented fields, using the UUID as the Idempotency-Key and in metadata, and stores the Pixelforge id, status and rates from the reply
- [x] Concurrency-limit replies retry after Retry-After until 10 minutes from submission; overloaded and rate-limited replies retry up to 5 times with growing waits; each retry reuses the same Idempotency-Key
- [x] Content-policy, validation and daily-limit replies fail the generation immediately with their own message
- [x] **Money test, failed is free:** every give-up and permanent rejection leaves the generation at $0.00 with no charge recorded
- [x] **Money test, never twice:** a retried create returns the same Pixelforge generation (idempotent replay), so no second generation is recorded
- [x] The client is tested against a scriptable fake Pixelforge (busy, overloaded, rate-limited, blocked, validation, daily limit, accepted)
- [x] The feed shows tiles (status tag, prompt, "≤ max" price) and polls every 2–3 seconds only while a visible generation isn't final
- [x] The side panel shows status, settings and estimate. "Try again" and "Use these settings" copy the settings into the prompt bar

## Comments

**Done**, tested only against a faked Pixelforge (no real key yet).

- Submit: `GenerationController::store` → `App\Generations\SubmitGenerations::submit()` saves the generation (status `waiting`, settings, estimate low/high and provider-cost low/high, UUID) and dispatches `App\Jobs\SubmitGeneration`. The toast says "Started. Up to $x set aside until it finishes." Ticket 06 adds the affordability check inside `submit()`, under a lock on the user.
- Client: `PixelforgeClient::createGeneration()` and `generation()` (retrieve, ready for the sweep). Replies become `PixelforgeGeneration` snapshots; errors become `TemporaryError` (concurrency, overloaded, rate limit, any 5xx or connection failure, with Retry-After), `PermanentError` (content policy, validation, daily limit, 409 idempotency reuse) or `PixelforgeAuthError`. The UUID travels as metadata key `generation_uuid`.
- Retries are delayed `release()`s, never sleeps. Concurrency limit: release after Retry-After until 10 minutes from `created_at`, not counted as a try. Overloaded / rate limit / 5xx: `submit_tries` counts them; waits are Retry-After × 2^(try−1); the 5th fails. Any busy reply after 10 minutes fails too. Both end as `error_code = too_busy`. The job's `retryUntil()` (15 min) and `failed()` are a safety net that also fails it for free. Tests drive the clock with `travel()`/`freezeSecond()` and `withFakeQueueInteractions()`.
- Lifecycle (for ticket 04): `App\Generations\GenerationLifecycle::apply(Generation, PixelforgeGeneration)` is the forward-only entry point (row lock, one transaction). It keeps the Pixelforge id and locked rates the first time it sees them, and on `succeeded` already stores usage, image URL/expiry, provider cost and the capped actual charge via `Pricing::actualCharge()`. Ticket 04 should route webhooks and the sweep through it, match by `pixelforge_id` or `uuid`, and add the ledger entry in the same transaction (ticket 06). `failBeforeAcceptance()` is for submit-time failures. Statuses: `App\Generations\GenerationStatus`; user-facing failure words: `App\Generations\FailureReason`.
- Schema: `generations` already has every column the spec lists (rates, usage, provider cost, actual charge, capped, absorbed, error, image URL/expiry, submit bookkeeping, started/completed), so ticket 04 needs no table changes beyond its processed-events table.
- Feed: the Create page gets the newest 20 as `generations` (a lazy prop, so polling with `only: ['generations']` skips the pricing fetch). `usePoll` runs every 2.5 s only while any tile isn't final. Tiles and panel render all five statuses, including processing and succeeded/capped; filters, "Load 20 more" and the polished empty state stay for ticket 05.
- **Needs developer:** with a real local key, run `php artisan queue:work database` and generate with `[overloaded]` (should retry, then fail as "Pixelforge was too busy" after 5 tries) and `[blocked]` (fails at once as "Prompt blocked"), and check the 4th concurrent generation waits as "Waiting for a slot".
