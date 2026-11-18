# 07: Admin report

**What to build:** An operator logs in with an admin account and opens the Admin report. It shows what users were charged, what Pixelforge charged us, and the profit, per UTC day (by completion) and per user, with capped charges and estimate accuracy. They can click a day or user to see each image, and each day is checked against Pixelforge's own usage numbers. Non-admins never see it.

**Blocked by:** 04

**Status:** resolved

**Needs from the developer first:** nothing.

- [x] Admins are marked by a flag set only by a server command (make-admin by email); a grader admin is seeded
- [x] Admin routes are guarded on the server. Non-admins don't see the link and get the "Admins only" page if they open it directly
- [x] The date range defaults to the last 30 days, with a maximum of 92, in UTC days counted by completion time
- [x] Totals (charged, Pixelforge cost, profit, matches Pixelforge) always cover the whole range
- [x] The by-day table is unpaginated. The by-user and per-image tables are paginated 20 per page, and the images table filters to a clicked day or user with "Show all"
- [x] Amounts show all 9 decimals; capped counts and the absorbed amount appear per day and per user; estimate ranges and where actuals landed are shown
- [x] Each day's provider cost is compared with `GET /v1/usage` for the same range: a match, a mismatch tag (with the difference), or "not checked" with a "Couldn't check" card and Try again when Pixelforge doesn't respond
- [x] Empty ranges say so plainly
- [x] **Money test, exact cost:** a generation created at 23:59 UTC and completed at 00:00 UTC counts on the completion day, and day totals equal the sum of their generations
- [x] **Money test, failed is free:** failed generations add nothing to charged or provider cost
- [x] **Money test, never twice:** each generation contributes once to every total, and the comparison against a faked usage reply matches
- [x] The page uses the Sidebar layout from the design reference

## Comments

Resolved on `main`. Route `GET /admin/report` (name `admin.report`) sits behind the `admin` middleware. Non-admins get the `AdminsOnly` page with a 403, and the header shows "Admin report" only to admins.

- **Flag:** migration `add_is_admin_to_users_table`. Grant it with `php artisan users:make-admin {email}`.
- **Grader admin:** `GraderAdminSeeder` reads `GRADER_ADMIN_EMAIL` (default `grader@example.com`) and `GRADER_ADMIN_PASSWORD`, and skips with a warning when no password is set. It's idempotent and called from `DatabaseSeeder`. Run it alone with `php artisan db:seed --class=GraderAdminSeeder --force`.
- **Range:** `?from=&to=` (YYYY-MM-DD, max 92 days). The images table takes `?day=` / `?user=`; pages are `users_page` / `images_page`.
- **Usage check:** `/v1/usage` is asked once per full page load. Paging a table is an Inertia partial reload, so it doesn't call Pixelforge again (tested).
- **Position:** "where actuals landed" is display-only: a whole percent between estimate low and high. It isn't money.
- Logic lives in `app/Admin/AdminReport.php` and `ReportRange.php`. Tests are in `tests/Feature/Admin/*`.

**Needs developer**
- Set `GRADER_ADMIN_PASSWORD` (and optionally `GRADER_ADMIN_EMAIL`) on Laravel Cloud. After the deploy runs the migration, run the seeder or `users:make-admin`.
- Add the grader login to the README (ticket 08).
- Once live generations exist, check that the report matches the real `/v1/usage` (the local check showed matches).
