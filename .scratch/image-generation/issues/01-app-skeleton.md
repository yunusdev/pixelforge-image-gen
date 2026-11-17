# 01: App skeleton, themed and deployed

**What to build:** A visitor can open the live app, sign up, log in and out, and land on an empty Create page. The page already has the chosen look (lab version D) and the top bar: Create · Credits, an "Available" button, an account menu with Log out, and a Menu button on phones. Every later ticket ships straight to this live URL.

**Blocked by:** None (can start immediately)

**Status:** resolved

**Needs from the developer first:** a public GitHub repository and a Laravel Cloud account connected to it.

- [x] Built on the official Laravel Vue starter kit (Inertia, TypeScript, shadcn-vue) with Postgres; the database is the queue driver
- [x] Email verification is off: a new account can use the app immediately
- [x] The theme matches lab version D: warm monochrome palette, Newsreader headings, Geist for interface text, Geist Mono for amounts, pastel status tags, 1px borders, 8–12px corners
- [x] The top bar, account menu (name, email, Log out) and mobile Menu button match the design reference; the Admin report link is not shown yet
- [x] Pest runs against Postgres in CI or locally with one command
- [ ] Deployed on Laravel Cloud with a web process, a queue worker and the scheduler, and the deployed URL loads the sign-up page
- [x] Pixelforge settings (API key, webhook signing secret, base URL) are read from config/environment, with no secrets committed

## Comments

**Done.** Laravel 13 Vue starter kit (Inertia 3, TypeScript, shadcn-vue, Fortify, Wayfinder) with Pest 5 on Postgres. Run everything locally with `php artisan test` (needs the `pixelforge-pg` container: 127.0.0.1:5433, database `pixelforge_test`). `composer ci:check` runs the full CI set (vp check, vue-tsc, Pint, PHPStan, tests), and the GitHub workflow starts a Postgres 17 service for it. Pest 5 needs PHP 8.4, so `composer.json` and CI now require `^8.4`.

- `/` sends visitors to sign up and signed-in users to `/create`. `/credits` is a placeholder page until ticket 06.
- The app is light only: the kit's appearance (dark mode) settings were removed. The kit's profile and security settings stay reachable from the account menu ("Settings"), which is the one addition to the lab's menu.
- The "Available" button shows no amount yet. Ticket 06 adds the available balance to it and to the account menu.
- Pastel tags: `StatusTag.vue` maps the generation statuses (waiting, queued, processing, succeeded, failed) to the theme's `tag-*` colours.
- Pixelforge settings: `config('services.pixelforge.api_key' | 'webhook_secret' | 'base_url')`, from `PIXELFORGE_API_KEY`, `PIXELFORGE_WEBHOOK_SECRET` and `PIXELFORGE_BASE_URL` (default `https://pixelforge.promptedit.dev/v1`). `.env.example` leaves the secrets empty.
- The kit ships no CLAUDE.md or AGENTS.md, so `CLAUDE.md` is unchanged.

**Needs developer: deploy on Laravel Cloud** (left unticked: needs your GitHub repo and Cloud account)

1. Push this branch to the public GitHub repo, then create a Cloud application from it. Use PHP 8.4 and Node 22.
2. Database: create a Laravel Postgres (Serverless Postgres) database and attach it to the environment. Cloud injects the `DB_*` variables.
3. Build commands (the Cloud defaults are fine):
   - `composer install --no-dev --optimize-autoloader`
   - `npm ci`
   - `npm run build` (the Wayfinder Vite plugin calls `php artisan`, so PHP must be on the build image, which it is on Cloud)
4. Deploy commands: `php artisan migrate --force`
5. Environment variables:
   - `APP_NAME=Pixelforge`, `APP_ENV=production`, `APP_DEBUG=false`. Cloud sets `APP_KEY` and `APP_URL`.
   - `QUEUE_CONNECTION=database`, `CACHE_STORE=database`, `SESSION_DRIVER=database`
   - `PIXELFORGE_API_KEY` and `PIXELFORGE_WEBHOOK_SECRET`: the deployed key's values, kept separate from the local key
   - `PIXELFORGE_BASE_URL=https://pixelforge.promptedit.dev/v1`
6. Queue worker: on the app compute cluster, add a background process / queue worker running `php artisan queue:work database --sleep=1`.
7. Scheduler: turn on "Scheduler" on the app compute cluster. It runs `schedule:run` every minute, which the missed-webhook sweep in ticket 04 needs.
8. Keep the app awake: turn off hibernation on the app compute cluster. Webhooks, the worker and the scheduler can't wait for a cold start. Also turn off database hibernation, or accept a slow first request.
9. Check: the deployed URL redirects to `/register` and shows the sign-up page. Then tick the deploy box above.
10. Later (ticket 04): set the deployed key's webhook URL in the Pixelforge dashboard to the app's webhook route on the Cloud domain.
