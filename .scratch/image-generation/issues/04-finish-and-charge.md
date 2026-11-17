# 04: Finishing generations and recording the charge

**What to build:** When Pixelforge finishes a generation, the user's tile turns to Done with the image and the exact amount charged, or to Failed with "$0.00 free" and the reason. Pixelforge's messages may arrive twice, out of order, before our send has returned, or not at all, and the user still ends up with the right status and exactly one correct charge.

**Blocked by:** 03

**Status:** ready-for-agent

**Needs from the developer first:** an ngrok (or similar) tunnel, the local key's webhook URL set to it in the Pixelforge dashboard, and its signing secret in `.env`.

- [ ] The webhook endpoint sits outside CSRF and verifies the HMAC-SHA256 signature over the raw body in constant time, rejecting missing, invalid or stale (over 300 s) signatures with 400
- [ ] Each event is processed in the request inside one transaction: record the event id (unique), lock the generation, apply the status only if it moves forward, respond 2xx (ADR-0003)
- [ ] Events are matched by Pixelforge id, or by the metadata UUID when they arrive before the send has returned
- [ ] Unknown generations and `ping` events get 2xx and are logged
- [ ] On success the provider cost (usage × the generation's locked rates) and the actual charge (capped at the estimate's top) are stored, and the image URL and its expiry are saved
- [ ] A scheduled task every minute fetches generations unfinished for over 2 minutes from Pixelforge and passes them through the same lifecycle code, bounded to respect the rate limit
- [ ] **Money test, exact cost:** a succeeded event with known usage records exactly the expected provider cost and charge
- [ ] **Money test, never twice:** the same event delivered twice, and the webhook plus the backup check both reporting success, record one charge
- [ ] **Money test, failed is free:** `generation_failed` and `internal_error` record no charge and show their own messages
- [ ] A late `processing` event after `succeeded` doesn't change anything
- [ ] Done tiles show the charge (with a "capped" tag when capped). The side panel shows where the charge landed between the low estimate and the max
