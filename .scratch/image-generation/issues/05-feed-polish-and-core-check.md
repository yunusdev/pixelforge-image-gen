# 05: Feed polish and core check against real Pixelforge

**What to build:** The Create page feels finished. It has filters (All, Running, Done, Failed), "Load 20 more" with "Showing x of y", a friendly empty state with example prompts for a new user, calm loading animations with elapsed timers, and an "Image expired" placeholder after 24 hours. Then the whole core flow is proven on the deployed app against real Pixelforge.

**Blocked by:** 04

**Status:** ready-for-agent

**Needs from the developer first:** a second, production Pixelforge key with its webhook URL pointing at the live app, and its signing secret set in Laravel Cloud.

- [ ] The filter chips and an empty-filter message ("No failed images" with "Show all") match the design reference
- [ ] The feed loads 20 at a time, newest first
- [ ] The new-user empty state shows the starting credits and example prompts that fill the prompt bar
- [ ] Loading tiles: a slow sweep for waiting, a faster sweep for queued, drifting colour blobs for processing, each with a live elapsed timer; motion is off under reduced-motion
- [ ] Succeeded generations past `image_url_expires_at` show the expired placeholder; prices and status stay
- [ ] On the deployed app, prompts with `[succeed]`, `[fail]`, `[slow]`, `[duplicate]`, `[overloaded]` and `[blocked]` each end in the right state with the right charge
- [ ] **Money check, exact cost:** the deployed app's summed provider costs for the test day equal `GET /v1/usage` for the production key, to the nanodollar
