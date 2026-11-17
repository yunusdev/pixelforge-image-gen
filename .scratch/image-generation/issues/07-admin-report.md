# 07: Admin report

**What to build:** An operator logs in with an admin account and opens the Admin report. It shows what users were charged, what Pixelforge charged us, and the profit, per UTC day (by completion) and per user, with capped charges and estimate accuracy. They can click a day or user to see each image, and each day is checked against Pixelforge's own usage numbers. Non-admins never see it.

**Blocked by:** 04

**Status:** ready-for-agent

**Needs from the developer first:** nothing.

- [ ] Admins are marked by a flag set only by a server command (make-admin by email); a grader admin is seeded
- [ ] Admin routes are guarded on the server. Non-admins don't see the link and get the "Admins only" page if they open it directly
- [ ] The date range defaults to the last 30 days, with a maximum of 92, in UTC days counted by completion time
- [ ] Totals (charged, Pixelforge cost, profit, matches Pixelforge) always cover the whole range
- [ ] The by-day table is unpaginated. The by-user and per-image tables are paginated 20 per page, and the images table filters to a clicked day or user with "Show all"
- [ ] Amounts show all 9 decimals; capped counts and the absorbed amount appear per day and per user; estimate ranges and where actuals landed are shown
- [ ] Each day's provider cost is compared with `GET /v1/usage` for the same range: a match, a mismatch tag (with the difference), or "not checked" with a "Couldn't check" card and Try again when Pixelforge doesn't respond
- [ ] Empty ranges say so plainly
- [ ] **Money test, exact cost:** a generation created at 23:59 UTC and completed at 00:00 UTC counts on the completion day, and day totals equal the sum of their generations
- [ ] **Money test, failed is free:** failed generations add nothing to charged or provider cost
- [ ] **Money test, never twice:** each generation contributes once to every total, and the comparison against a faked usage reply matches
- [ ] The page uses the Sidebar layout from the design reference
