# 05: Feed polish and core check against real Pixelforge

**What to build:** The Create page feels finished. It has filters (All, Running, Done, Failed), "Load 20 more" with "Showing x of y", a friendly empty state with example prompts for a new user, calm loading animations with elapsed timers, and an "Image expired" placeholder after 24 hours. Then the whole core flow is proven on the deployed app against real Pixelforge.

**Blocked by:** 04

**Status:** resolved

**Needs from the developer first:** a second, production Pixelforge key with its webhook URL pointing at the live app, and its signing secret set in Laravel Cloud.

- [x] The filter chips and an empty-filter message ("No failed images" with "Show all") match the design reference
- [x] The feed loads 20 at a time, newest first
- [x] The new-user empty state shows the starting credits and example prompts that fill the prompt bar
- [x] Loading tiles: a slow sweep for waiting, a faster sweep for queued, drifting colour blobs for processing, each with a live elapsed timer; motion is off under reduced-motion
- [x] Succeeded generations past `image_url_expires_at` show the expired placeholder; prices and status stay
- [ ] On the deployed app, prompts with `[succeed]`, `[fail]`, `[slow]`, `[duplicate]`, `[overloaded]` and `[blocked]` each end in the right state with the right charge
- [ ] **Money check, exact cost:** the deployed app's summed provider costs for the test day equal `GET /v1/usage` for the production key, to the nanodollar

## Comments

- Filters and paging are server-side: `GET /create?filter=running|succeeded|failed&shown=40`. `shown` is whole steps of 20, capped at 1000; unknown values fall back to All / 20. Polling keeps the query and reloads `generations` + `feed` (counts) only. Generating sends you back to All.
- Expired images: the resource sends `image_expired` and drops the dead `image_url`; tiles and the panel also flip to the placeholder if the expiry passes while the page is open.
- Starting credits in the empty state come from `config/credits.php` (`sign_up_bonus`, $5.00 in nanodollars). Ticket 06 should write the sign-up bonus ledger entry from the same value, and may switch the empty state to the user's available balance.
- Loading tiles (slow sweep / sweep / drifting blobs, live timer, off under reduced motion) were already built in 03/04 and match the lab; checked in the browser.
- Needs developer: the two deployed-app boxes. They need the production Pixelforge key with its webhook URL pointed at the live app (`https://<host>/webhooks/pixelforge`), its signing secret in Laravel Cloud, APP_KEY re-added and a deploy. Then run prompts with `[succeed]`, `[fail]`, `[slow]`, `[duplicate]`, `[overloaded]`, `[blocked]` and check each ends in the right state and charge; then compare the day's summed provider costs with `GET /v1/usage` for that key, to the nanodollar (the admin report in 07 does this comparison; until then sum `provider_cost` of succeeded generations for the UTC day).
