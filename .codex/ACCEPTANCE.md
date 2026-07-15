# SaaS foundation acceptance contract

This contract evaluates behavior, not whether the application matches a historical template.
Every item is required unless the product owner explicitly records a deviation.

## Upstream integrity

- The repository began from the official Laravel React starter generated during initialization.
- Framework and authentication packages were not downgraded to match a reference application.
- Registration, login, logout, reset, verification, confirmation, 2FA, passkeys, profile, and
  account deletion have automated feature coverage.
- Inertia SSR is not configured.

## Tenant and authorization invariants

- Registration atomically provisions an organization and owner membership.
- Users can belong to and switch between multiple organizations.
- `owner`, `admin`, `member`, and `viewer` roles exist with explicit permissions.
- Server-side policies/gates enforce every protected mutation.
- Tenant-owned queries deny access when tenant context is absent.
- Cross-organization reads and writes fail in automated tests.
- Tenant identity is carried explicitly into queued jobs, commands, scheduled work, and private
  broadcast authorization.
- Documentation accurately calls this application-enforced tenant isolation unless native
  PostgreSQL RLS policies are actually installed and tested.

## Product experience

- Landing, dashboard, projects, members, settings, and authentication flows render successfully.
- The application has a responsive navigation shell and organization switcher.
- Light and dark modes use semantic tokens and remain readable.
- Empty, loading, success, validation, forbidden, and failure states are represented.
- Keyboard focus and mobile navigation are usable.
- Product name/mark can be changed without editing many unrelated components.

## Background and streaming behavior

- A queued notification or job succeeds through the configured worker.
- Retry exhaustion is observable through `failed_jobs`.
- The scheduler runs independently of web requests.
- A real Reverb socket can receive an authorized private-channel event.
- Unauthorized private-channel subscription fails.
- SSE begins streaming before completion, flushes incrementally, and terminates after disconnect.

## Image and deployment

- One production application image serves web, worker, scheduler, and Reverb roles.
- The final stage uses `ghcr.io/nikitph/laravel-runtime:1.0.0`.
- The disposable asset-builder has both PHP and Node available because the Wayfinder Vite plugin
  invokes `php artisan wayfinder:generate --with-form` during the frontend build.
- A clean Docker build generates Wayfinder types inside the build; it does not rely on generated
  files, `vendor`, or frontend assets copied from the host.
- The image runs as non-root and contains production dependencies and built assets.
- `.env`, credentials, the Node executable/package manager, `node_modules`, and development
  dependencies are absent from the final image.
- Health checking reaches an application route.
- Graceful shutdown does not abandon routine queue work.
- Docker Compose starts PostgreSQL, Redis, web, worker, scheduler, and Reverb locally.
- Kamal configuration documents a one-droplet deployment and secret inputs.
- Database migrations are an explicit deployment step before workers begin consuming new code.

## Agent readiness

- `AGENTS.md`, `docs/START-HERE.md`, and `docs/ARCHITECTURE.md` exist and agree with the code.
- One documented setup command creates a usable local demo environment.
- The replaceable example business resource is clearly identified.
- Generated Wayfinder code is regenerated, not maintained manually.
- CI and local development call the same verification entry point.

## Required final commands

Run from a clean checkout using the documented toolchain:

```bash
composer verify
docker build --no-cache -t company/product:conformance .
docker compose up -d
```

Then execute the repository's HTTP, queue, scheduler, WebSocket, SSE, image-content, and tenant
isolation checks. The exact scripts may evolve with Laravel; their asserted behaviors may not.
