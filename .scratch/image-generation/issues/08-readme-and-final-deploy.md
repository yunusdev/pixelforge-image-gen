# 08: README and final deploy

**What to build:** A grader can clone the repo, understand the decisions, run the app, open the design lab, log in as an admin on the live app, and find real generations there to compare with Pixelforge's records.

**Blocked by:** 05, 06, 07

**Status:** ready-for-agent

**Needs from the developer first:** the email of the Pixelforge account (for the submission form, filled in by the developer).

- [ ] The README covers: what the app does, local setup (Postgres, queue worker, scheduler, tunnel, `.env` keys), running the tests, and the deployed URL
- [ ] The README explains the money decisions and links the glossary and ADRs 0001–0003
- [ ] The README gives the grader admin login and how to run make-admin
- [ ] The README shows how to view the design lab (`python3 -m http.server 4610 --directory lab`) and that version D was chosen
- [ ] The README lists the Pixelforge test keywords and what each one shows
- [ ] The live app has a handful of generations covering succeeded, failed and capped-if-possible cases, and the Admin report shows every day as matching Pixelforge
- [ ] The full test suite passes on the final commit
