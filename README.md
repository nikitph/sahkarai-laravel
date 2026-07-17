# SahkarAI

SahkarAI turns RBI, Income Tax, and GST publications into a searchable regulatory archive, localized plain-language interpretations, notifications, and document-grounded AI chats.

This repository is the product application generated from the company Laravel initializer. It runs on the frozen multi-arch `ghcr.io/nikitph/laravel-runtime:1.0.0` image with PostgreSQL, Redis queues, Reverb, and separate web/worker/scheduler roles.

## Start locally

Docker Desktop is the only prerequisite:

```bash
./bin/setup
```

Open `http://localhost:8002` and sign in with one of the seeded accounts:

| Account | Password | Access |
|---|---|---|
| `demo@example.com` | `password` | Tier 2, 200 chat credits |
| `admin@example.com` | `password` | SaaS operations |

The seed also provides sample regulatory documents and interpretations in English, Hindi, Gujarati, and Marathi. AI and payment credentials are not required to browse the seeded product.

## Product capabilities

- Free, Insight, Intelligence, and Personalized capability boundaries with user-owned subscriptions and credit ledgers
- RBI / Income Tax / GST polling, immutable original storage, SHA-256 deduplication, revisions, PDF extraction, and a 12-month backfill command
- Laravel AI SDK agents for structured four-locale interpretations and document-version-scoped streaming chat
- searchable latest-version archive with filters, provenance, raw downloads, issue reporting, and Markdown/PDF exports
- real-time in-app notifications, source-specific email cadence, localized mail, and immutable delivery logs
- Razorpay checkout seams, signed/idempotent webhooks, queued downgrades, failed-renewal handling, reconciliation, and dormant top-up support
- private immutable chats, atomic one-credit message debits, context-window closure, and JSON/Markdown/PDF exports
- operations dashboard for poll health, failures, issue triage, billing drift, and privacy-safe user/chat metadata
- 30-day account deletion grace period with signed restoration and scheduled hard deletion/anonymization

## External configuration

Copy `.env.example` to `.env`. The two integrations needed for live end-to-end testing are:

```dotenv
DEEPSEEK_API_KEY=
RAZORPAY_KEY_ID=
RAZORPAY_KEY_SECRET=
RAZORPAY_WEBHOOK_SECRET=
RAZORPAY_TIER_1_PLAN_ID=
RAZORPAY_TIER_2_PLAN_ID=
RAZORPAY_TIER_3_PLAN_ID=
```

Income Tax ingestion defaults to the department's official Circular RSS feed.
Set `RBI_FEED_URL` and `GST_FEED_URL` before enabling those sources, or override
`INCOME_TAX_FEED_URL` if the department supplies an allow-listed endpoint.
Missing or unreachable sources fail visibly in `poll_runs` and the ops dashboard.

## Common commands

```bash
./bin/setup                         # build, migrate, seed, and boot the stack
composer verify                     # format, static analysis, tests, and production build
php artisan test                    # backend suite
php artisan regulatory:backfill     # queue the 12-month source backfill
php artisan regulatory:backfill --sync
npm run types:check
```

Workers must be running for acquisition, extraction, interpretation, notifications, billing reconciliation, and account cleanup. The scheduler dispatches source polls, notification digests, billing transitions, reconciliation, and expired-account purges.

## Architecture and specifications

Read [docs/START-HERE.md](docs/START-HERE.md), [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md), [docs/SPEC-COVERAGE.md](docs/SPEC-COVERAGE.md), and the scenario ledger in [docs/SPEC-EVIDENCE.md](docs/SPEC-EVIDENCE.md) before changing a domain boundary. The source requirements live in `specs/**/*.feature`; TypeScript helper files under `specs` are deliberately ignored.

The specs mention Supabase sessions, dormant TOTP, and database RLS. This application deliberately retains the stronger initializer authentication surface (Laravel sessions, optional passkeys and 2FA) and enforces product ownership through policies, owner-scoped relationships, authorization tests, and transactional write actions. There is no admin impersonation or chat-body access.

## Production

The Dockerfile produces one immutable image without Node, development dependencies, or `.env`. Kamal runs the same image as web, worker, scheduler, and Reverb roles. The existing DigitalOcean configuration is under `config/deploy.yml` and `config/deploy.reverb.yml`.

```bash
kamal accessory boot redis
kamal deploy --version=<version> --skip-push
kamal deploy -c config/deploy.reverb.yml --version=<version> --skip-push
```

The Redis accessory supplies queues, cache, and isolated migration locks; PostgreSQL remains the durable application database. Classic FrankenPHP mode remains deliberate. Secrets belong in the gitignored `.kamal/secrets` file or the deployment secret store, never in an image layer.

After placing DeepSeek and Razorpay test credentials in the environment, run the read-only live provider gate before enabling traffic:

```bash
php artisan sahkarai:providers:verify
```
