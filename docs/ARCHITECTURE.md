# Architecture

## Product flow

`Route → Form Request → Policy → Controller → Action/model → Audit/event → queued side effect → Inertia response`

The Projects module is the canonical vertical slice. Business logic belongs in an action when it is more than a simple persistence operation. Frontend code is TypeScript and uses Inertia, shadcn-style primitives, Tailwind and Motion; there is no SSR service.

## Tenancy and authorization

Organizations share one PostgreSQL schema. Membership roles are owner, admin, member and viewer; roles map to typed permissions. `SetCurrentOrganization` establishes a request-scoped `TenantContext`. Tenant-owned Eloquent models use `BelongsToOrganization`, database keys include `organization_id`, and policies are the authorization boundary. This is application-enforced tenant isolation, not PostgreSQL native RLS.

The global scope is defense-in-depth, not PostgreSQL RLS and not permission logic. Raw SQL, jobs and scheduled commands must establish context explicitly. Cross-tenant denial tests are mandatory.

## Runtime topology

One immutable image runs web, worker, scheduler and Reverb roles with different commands. The image inherits the frozen multi-arch `ghcr.io/nikitph/laravel-runtime:1.0.0` digest. Classic FrankenPHP mode only. PostgreSQL-backed sessions, cache and queues are the single-host default. Reverb is a separate proxied Kamal service for WSS.

## Included product primitives

- Fortify auth: registration, verification, reset, 2FA and passkeys
- organization switching, memberships and queued invitations
- typed RBAC permissions and policies
- audit events and a tenant-aware reference module
- Laravel AI SDK configuration/stubs
- SSE/Reverb-ready runtime and queue/scheduler roles
- CI and one-command `composer verify`

Billing, social auth, object storage, webhooks and API tokens are intentionally seams, not pretend implementations. Add them when a real product requires them.
