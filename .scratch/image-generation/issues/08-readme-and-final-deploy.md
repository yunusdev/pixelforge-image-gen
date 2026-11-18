# 08: README and final deploy

**What to build:** A grader can clone the repo, understand the decisions, run the app, open the design lab, log in as an admin on the live app, and find real generations there to compare with Pixelforge's records.

**Blocked by:** 05, 06, 07

**Status:** resolved

**Needs from the developer first:** the email of the Pixelforge account (for the submission form, filled in by the developer).

- [x] The README covers: what the app does, local setup (Postgres, queue worker, scheduler, tunnel, `.env` keys), running the tests, and the deployed URL
- [x] The README explains the money decisions and links the glossary and ADRs 0001–0003
- [x] The README gives the grader admin login and how to run make-admin
- [x] The README shows how to view the design lab (`python3 -m http.server 4610 --directory lab`) and that version D was chosen
- [x] The README lists the Pixelforge test keywords and what each one shows
- [ ] The live app has a handful of generations covering succeeded, failed and capped-if-possible cases, and the Admin report shows every day as matching Pixelforge
- [x] The full test suite passes on the final commit

## Comments

- `README.md` added: what the app does, live URL, grader admin (email from `GRADER_ADMIN_EMAIL`, password shared with the submission, never written in the repo), `users:make-admin`, money decisions linking `GLOSSARY.md` and ADRs 0001–0003, local setup for any Postgres 17+ (`.env` keys, `composer run dev` + `php artisan schedule:work`, ngrok tunnel), tests, test keywords, design lab (version D, Sidebar layout), Laravel Cloud deploy summary.
- Full suite on the final commit: 230 tests passed (1287 assertions), after `npm run build`.

**Needs developer**
- The live-app box: run a handful of generations on the deployed app (`[succeed]`, `[fail]`, `[blocked]`, `[duplicate]`; a capped case only if Pixelforge rates change between estimate and acceptance), then open the Admin report as the grader and check every day shows as matching Pixelforge.
- Set `GRADER_ADMIN_PASSWORD` on Laravel Cloud and run `php artisan db:seed --class=GraderAdminSeeder --force` after deploy; share the password with the submission.
- Fill in the Pixelforge account email on the submission form.
