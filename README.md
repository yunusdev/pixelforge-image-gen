# Pixelforge Image Gen

A Laravel + Vue app where signed-in users generate images through the Pixelforge API and pay for each one with credits.

- **Create:** type a prompt, pick size, quality and up to 4 reference images, see the price **estimate** before you generate, then watch the generation finish in the feed (filters, paging, live status).
- **Charge:** a succeeded generation costs its **provider cost** plus a 25% **markup**, rounded up to the nanodollar, and never more than the top of its estimate. Failed generations are free. Each one is charged exactly once, even when webhooks arrive twice or are missed.
- **Credits:** every account starts with a $5.00 sign-up bonus and can add fake $5 / $10 / $25 packs. The page shows balance, available balance, a 30-day spending chart and the ledger.
- **Admin report:** totals, users and images for a date range, and a by-day check of our summed provider costs against Pixelforge's `GET /v1/usage`.

**Live app:** https://pixelforge-image-gen-production-4evp9v.laravel.cloud

Stack: Laravel 13 (Vue starter kit: Inertia, TypeScript, shadcn-vue, Tailwind, Fortify), PHP 8.4+, Postgres, database queue, Pest.

## How it was built

Built with an AI coding agent driven by [Matt Pocock's skills](https://github.com/mattpocock/skills), one step at a time:

1. **Set up the skills** (`setup-matt-pocock-skills`): issue tracker as local markdown under `.scratch/`, triage labels and domain doc layout ([CLAUDE.md](CLAUDE.md), [docs/agents](docs/agents)).
2. **Grilled the plan** (`grill-with-docs`) until every money and failure question had an answer. That produced the [glossary](GLOSSARY.md) and the [ADRs](docs/adr).
3. **Wrote the spec** (`to-spec`): [.scratch/image-generation/spec.md](.scratch/image-generation/spec.md).
4. **Explored the UI in a lab** (`prototype`): five designs on fake data in [lab/](lab). Version D (feed, minimal) was chosen, with the Sidebar layout for Credits and the Admin report. To browse it: `python3 -m http.server 4610 --directory lab`, then open http://localhost:4610.
5. **Broke the spec into tickets** (`to-tickets`): [.scratch/image-generation/issues](.scratch/image-generation/issues), each a thin end-to-end slice with its blockers.
6. **Implemented each ticket** (`implement`): test first with `tdd`, then `code-review`, which reviews the changes against the coding standards and against the spec in two parallel sub-agents. Each resolved ticket ends with handoff notes and what still needed a developer.

## Grader admin login

- Email: `grader@example.com` (the value of `GRADER_ADMIN_EMAIL`)
- Password: shared with the submission

The account is created by `GraderAdminSeeder` from `GRADER_ADMIN_EMAIL` and `GRADER_ADMIN_PASSWORD`. With no password set it is skipped. To make any existing user an admin:

```bash
php artisan users:make-admin someone@example.com
```

Run the seeder on its own with `php artisan db:seed --class=GraderAdminSeeder --force`.

## Money decisions

Terms (estimate, provider cost, charge, markup, available balance, capped, and so on) are defined in [GLOSSARY.md](GLOSSARY.md). The three decisions behind the money rules:

- [ADR 0001](docs/adr/0001-integer-nanodollars-round-up.md): every amount is an integer number of nanodollars; the charge rounds up.
- [ADR 0002](docs/adr/0002-available-balance-and-capped-charge.md): Generate is allowed only when the estimate's top fits in the available balance (balance minus the tops of generations still running). The charge is capped at the estimate's top; any difference is absorbed and shown in the admin report.
- [ADR 0003](docs/adr/0003-webhooks-processed-in-request.md): webhooks are processed inside the request, in one transaction, with the event id recorded so duplicates do nothing.

A scheduled sweep (`generations:check-missed`, every minute) asks Pixelforge about generations unfinished after 2 minutes, in case a webhook was missed.

## Local setup

Needs PHP 8.4+ (with `pdo_pgsql`, `bcmath`, `intl`), Composer, Node 22.18+ and Postgres 17+.

1. Create a Postgres database and user, e.g. `pixelforge` / `pixelforge`.
2. Install and configure:

   ```bash
   composer install
   cp .env.example .env
   php artisan key:generate
   ```

3. Fill in `.env`:

   | Key | Value |
   | --- | --- |
   | `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | your Postgres (`DB_CONNECTION=pgsql`) |
   | `QUEUE_CONNECTION` | `database` |
   | `PIXELFORGE_API_KEY` | your local Pixelforge API key |
   | `PIXELFORGE_WEBHOOK_SECRET` | the `whsec_…` signing secret for that key's webhook URL (while empty, every webhook is rejected) |
   | `PIXELFORGE_BASE_URL` | `https://pixelforge.promptedit.dev/v1` |
   | `GRADER_ADMIN_EMAIL`, `GRADER_ADMIN_PASSWORD` | optional, to seed an admin |

4. Migrate, seed and build:

   ```bash
   php artisan migrate --seed
   npm install
   npm run build
   ```

5. Run it. `composer run dev` starts the web server, the queue listener, logs and Vite. In a second terminal start the scheduler (for the missed-webhook sweep):

   ```bash
   composer run dev
   php artisan schedule:work
   ```

   Without `composer run dev`, run `php artisan serve`, `php artisan queue:work database --sleep=1` and `php artisan schedule:work` separately.

6. Webhooks need a public URL. Start a tunnel, e.g. `ngrok http 8000`, and set the local key's webhook URL in the Pixelforge dashboard to `https://<tunnel-host>/webhooks/pixelforge`. Put its signing secret in `PIXELFORGE_WEBHOOK_SECRET`. Without a tunnel, generations still finish through the sweep, about 2 minutes late.

Open http://localhost:8000, sign up, and generate.

## Tests

Tests use a separate Postgres database (see the `DB_*` values in `phpunit.xml`; default `pixelforge_test` on `127.0.0.1:5433`, change them to match your server). Pixelforge is always faked; no key is needed.

```bash
npm run build        # one test runs the built frontend
php artisan test
```

`composer ci:check` runs everything CI runs: lint and format checks, `vue-tsc`, Pint, PHPStan and the tests. Node must be on `PATH`: a test checks the browser's price estimate against the PHP one.

## Pixelforge test keywords

Put a keyword anywhere in the prompt (case-insensitive). It counts toward text tokens like any other text.

| Keyword | What happens | What the app shows |
| --- | --- | --- |
| `[succeed]` | Never fails at random | Succeeds and is charged |
| `[fail]` | Accepted, then `generation.failed` | Failed, free |
| `[blocked]` | Rejected at submit (content policy) | Failed at once as "Prompt blocked", free |
| `[overloaded]` | Every submit gets a 503 | Retries with backoff, then fails as "Pixelforge was too busy", free |
| `[slow]` | 60–90 s of processing | Stays processing, then finishes normally |
| `[duplicate]` | Every webhook is delivered twice | Finishes once and is charged once |

Without a keyword Pixelforge still fails a small share at random, returns occasional 503s, and sometimes sends duplicate webhooks. Pixelforge allows 3 generations at a time per account; a 4th shows "Waiting for a slot" until one finishes.

## Deploying (Laravel Cloud)

- PHP 8.4, Node 22, a Postgres database attached to the environment.
- Build: `composer install --no-dev --optimize-autoloader`, `npm ci`, `npm run build`. Deploy: `php artisan migrate --force`.
- Env: `APP_ENV=production`, `APP_DEBUG=false`, `QUEUE_CONNECTION=database`, `CACHE_STORE=database`, `SESSION_DRIVER=database`, the deployed key's `PIXELFORGE_API_KEY` and `PIXELFORGE_WEBHOOK_SECRET`, `PIXELFORGE_BASE_URL`, `GRADER_ADMIN_EMAIL`, `GRADER_ADMIN_PASSWORD`.
- App cluster: a background process `php artisan queue:work database --sleep=1`, the scheduler on, hibernation off.
- The deployed key's webhook URL in the Pixelforge dashboard: `https://<app-host>/webhooks/pixelforge`.
- After the first deploy: `php artisan db:seed --class=GraderAdminSeeder --force`.
