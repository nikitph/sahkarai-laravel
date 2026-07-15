# SaaS starter architecture

Prefer Laravel conventions and the installed package versions. Domain work follows: validated request → policy → action → audit/event → queued side effects. `App\Support\Tenancy\TenantContext` is request scoped and must be explicit in HTTP, jobs, scheduler tasks, caches, files, broadcasts, and audit events. Copy the Projects module when creating a tenant-owned feature. Run `composer verify` before declaring work complete.
