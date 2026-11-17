# 03: Submit generations, with retries

**What to build:** A user presses Generate and the generation appears at the top of the feed straight away as "Waiting for a slot", with a toast saying how much is set aside. In the background it's sent to Pixelforge and moves to queued. While Pixelforge is busy, it keeps retrying on its own. If Pixelforge rejects it outright, or it can't get in, it fails for free with a plain explanation. The feed refreshes itself while anything is running, and clicking a tile opens the side panel, which has "Try again" and "Use these settings".

**Blocked by:** 02

**Status:** ready-for-agent

**Needs from the developer first:** a Pixelforge account and a local API key in `.env`.

- [ ] Submitting saves the generation locally first (settings, estimate low/high, a UUID) and dispatches a background job; the web request returns at once
- [ ] The Pixelforge client sends exactly the documented fields, using the UUID as the Idempotency-Key and in metadata, and stores the Pixelforge id, status and rates from the reply
- [ ] Concurrency-limit replies retry after Retry-After until 10 minutes from submission; overloaded and rate-limited replies retry up to 5 times with growing waits; each retry reuses the same Idempotency-Key
- [ ] Content-policy, validation and daily-limit replies fail the generation immediately with their own message
- [ ] **Money test, failed is free:** every give-up and permanent rejection leaves the generation at $0.00 with no charge recorded
- [ ] **Money test, never twice:** a retried create returns the same Pixelforge generation (idempotent replay), so no second generation is recorded
- [ ] The client is tested against a scriptable fake Pixelforge (busy, overloaded, rate-limited, blocked, validation, daily limit, accepted)
- [ ] The feed shows tiles (status tag, prompt, "≤ max" price) and polls every 2–3 seconds only while a visible generation isn't final
- [ ] The side panel shows status, settings and estimate. "Try again" and "Use these settings" copy the settings into the prompt bar
