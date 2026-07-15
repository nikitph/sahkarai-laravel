# SahkarAI

The Laravel application for SahkarAI. It starts at the business-use-case line: product identity, organizations, permissions, UI, queues, realtime, AI primitives and deployment conventions are already established.

## Start locally

```bash
./bin/setup
```

Open `http://localhost:8002` and use `demo@example.com` / `password`. Then read `docs/START-HERE.md` and give an agent the first domain capability. Docker Desktop is the only setup prerequisite.

## Included

- Laravel 13 / PHP 8.4 on frozen multi-arch `ghcr.io/nikitph/laravel-runtime:1.0.0`
- Inertia 3, React 19, TypeScript, Tailwind 4, shadcn-style components and Motion
- Fortify registration, verification, password reset, 2FA, passkeys and account settings
- PostgreSQL organizations, memberships, invitations and typed RBAC policies
- explicit request-scoped tenancy, tenant model scope and isolation tests
- queued mail, database queues/cache/sessions, scheduler and audit events
- Laravel AI SDK, Reverb WebSockets and SSE-capable FrankenPHP runtime
- one image for web, worker, scheduler and Reverb roles
- Kamal/DigitalOcean deployment templates and one-command verification
- an end-to-end Projects reference module for agents to copy

## Commands

```bash
./bin/setup                 # build and boot the complete local stack
composer run dev            # host-native app, queue, logs and Vite
composer verify             # format, static analysis, tests and production build
php artisan test            # backend suite
npm run types:check         # frontend types
```

## Architecture rules

`Authentication → TenantContext → Policy` is the authorization chain. Tenant-owned models carry `organization_id`; jobs carry the organization ID; controllers do not contain business rules; slow side effects are queued. See `AGENTS.md` and `docs/ARCHITECTURE.md` before adding a module.

Classic FrankenPHP mode is deliberate. Worker mode remains disabled until request-state isolation is proven under concurrent tenants. Redis is deliberately absent for the single-host baseline; add it when scaling across hosts or when Reverb needs shared pub/sub.

## Production

The Dockerfile pins the proven runtime by multi-arch index digest and produces an immutable app image without Node, dev dependencies or `.env`. `config/deploy.yml` runs web/worker/scheduler; `config/deploy.reverb.yml` deploys Reverb behind kamal-proxy on its own WSS host. Before deployment replace `YOUR_SERVER_IP`, product image names, registry credentials and all secrets.

Keep these proven constraints:

- `bootstrap/cache/*` stays in `.dockerignore`.
- web alone runs boot migrations; migration isolation stays false with database cache.
- app and Reverb each need an image tag whose `service` label matches the Kamal service name.
- tests use Reverb, never the null broadcaster.
