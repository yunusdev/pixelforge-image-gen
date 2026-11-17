# 01: App skeleton, themed and deployed

**What to build:** A visitor can open the live app, sign up, log in and out, and land on an empty Create page. The page already has the chosen look (lab version D) and the top bar: Create · Credits, an "Available" button, an account menu with Log out, and a Menu button on phones. Every later ticket ships straight to this live URL.

**Blocked by:** None (can start immediately)

**Status:** ready-for-agent

**Needs from the developer first:** a public GitHub repository and a Laravel Cloud account connected to it.

- [ ] Built on the official Laravel Vue starter kit (Inertia, TypeScript, shadcn-vue) with Postgres; the database is the queue driver
- [ ] Email verification is off: a new account can use the app immediately
- [ ] The theme matches lab version D: warm monochrome palette, Newsreader headings, Geist for interface text, Geist Mono for amounts, pastel status tags, 1px borders, 8–12px corners
- [ ] The top bar, account menu (name, email, Log out) and mobile Menu button match the design reference; the Admin report link is not shown yet
- [ ] Pest runs against Postgres in CI or locally with one command
- [ ] Deployed on Laravel Cloud with a web process, a queue worker and the scheduler, and the deployed URL loads the sign-up page
- [ ] Pixelforge settings (API key, webhook signing secret, base URL) are read from config/environment, with no secrets committed
