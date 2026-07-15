# Agent operating guide

Read `docs/START-HERE.md` and `docs/ARCHITECTURE.md` before changing code. This repository is a product foundation, not a generic Laravel sandbox.

## Non-negotiable invariants

- Authentication proves identity; `TenantContext` selects the organization; policies authorize the operation.
- Never query a tenant-owned model without an established `TenantContext`. Tenant models must use `BelongsToOrganization` and stamp `organization_id` on create.
- Never use UI visibility, route middleware, or a role string as the only authorization check. State-changing requests authorize through a policy.
- Keep controllers thin: Form Request → policy → Action/domain operation → event/audit → response.
- Queue email and slow side effects. A queued job must carry an organization ID and establish tenant context before querying tenant data.
- Database uniqueness for tenant data includes `organization_id`. Add cross-tenant denial tests for every tenant-owned module.
- Do not enable FrankenPHP worker mode. It is intentionally unproven for request-state isolation.
- Do not add Redis until the product needs multiple hosts, shared Reverb pub/sub, or higher queue throughput.
- Do not remove `bootstrap/cache/*` from `.dockerignore`, change the frozen runtime digest casually, or use the null broadcaster in tests.

## Definition of done

Run `composer verify`. Add an authorization test for every state change and an isolation test for every tenant-owned model. Update docs when an architectural invariant changes.

Use `Projects` as the reference vertical slice. Follow its file placement before inventing new structure.
