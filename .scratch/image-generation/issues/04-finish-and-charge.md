# 04: Finishing generations and recording the charge

**What to build:** When Pixelforge finishes a generation, the user's tile turns to Done with the image and the exact amount charged, or to Failed with "$0.00 free" and the reason. Pixelforge's messages may arrive twice, out of order, before our send has returned, or not at all, and the user still ends up with the right status and exactly one correct charge.

**Blocked by:** 03

**Status:** resolved

**Needs from the developer first:** an ngrok (or similar) tunnel, the local key's webhook URL set to it in the Pixelforge dashboard, and its signing secret in `.env`.

- [x] The webhook endpoint sits outside CSRF and verifies the HMAC-SHA256 signature over the raw body in constant time, rejecting missing, invalid or stale (over 300 s) signatures with 400
- [x] Each event is processed in the request inside one transaction: record the event id (unique), lock the generation, apply the status only if it moves forward, respond 2xx (ADR-0003)
- [x] Events are matched by Pixelforge id, or by the metadata UUID when they arrive before the send has returned
- [x] Unknown generations and `ping` events get 2xx and are logged
- [x] On success the provider cost (usage × the generation's locked rates) and the actual charge (capped at the estimate's top) are stored, and the image URL and its expiry are saved
- [x] A scheduled task every minute fetches generations unfinished for over 2 minutes from Pixelforge and passes them through the same lifecycle code, bounded to respect the rate limit
- [x] **Money test, exact cost:** a succeeded event with known usage records exactly the expected provider cost and charge
- [x] **Money test, never twice:** the same event delivered twice, and the webhook plus the backup check both reporting success, record one charge
- [x] **Money test, failed is free:** `generation_failed` and `internal_error` record no charge and show their own messages
- [x] A late `processing` event after `succeeded` doesn't change anything
- [x] Done tiles show the charge (with a "capped" tag when capped). The side panel shows where the charge landed between the low estimate and the max

## Comments

- Webhook URL to set in the Pixelforge dashboard: `https://<host>/webhooks/pixelforge` (POST, no CSRF, signature-checked). Its `whsec_…` secret goes in `PIXELFORGE_WEBHOOK_SECRET`; while it's empty every webhook is rejected with 400, by design.
- Missed-webhook check: `php artisan generations:check-missed`, scheduled every minute in `routes/console.php`. Needs the scheduler running (Laravel Cloud: scheduler on; locally `php artisan schedule:work`). At most 10 fetches per run, oldest first; a busy reply ends the run early.
- New migration `processed_webhook_events` runs with the normal deploy migrate.
- The ledger entry on success is ticket 06; this ticket records provider cost, actual charge, capped flag and absorbed amount on the generation.
- Needs developer: ngrok tunnel, the local key's webhook URL pointed at it, and its signing secret in `.env`; then a real run with `[succeed]`, `[fail]`, `[duplicate]` and `[slow]` to see tiles finish via webhook. So far covered only by faked tests.
