# Deployment automation implementation note

Status: parked for later review. This work is intentionally isolated on a
dedicated branch and has not been merged into or deployed through production.

## What has been implemented

The Laravel deployment kit now composes four established tools:

- OpenTofu declares the DigitalOcean project, droplet, reserved IP, firewall,
  and optional versioned Spaces bucket.
- Ansible creates and hardens the deployment user, installs Docker, configures
  UFW, fail2ban, unattended security upgrades, and optional encrypted
  PostgreSQL backups.
- Kamal deploys the web, queue worker, scheduler, Reverb, PostgreSQL, and Redis
  containers behind Kamal Proxy with TLS and health checks.
- GitHub Actions validates infrastructure changes, creates a reviewable
  OpenTofu plan, gates infrastructure apply through protected environments,
  builds immutable images, deploys the verified revision, checks fidelity, and
  rolls back containers after a failed post-deployment check.

Application support added with the kit includes:

- a `/ready` endpoint that checks PostgreSQL, cache/Redis, and regulatory
  storage;
- environment-driven Kamal hosts instead of a hard-coded production IP;
- optional S3-compatible DigitalOcean Spaces configuration;
- pre-deployment PostgreSQL backup integration;
- on-demand production verification and rollback scripts;
- a portable `deployment-kit/` package and installer for onboarding another
  Laravel repository.

Operational instructions, required GitHub variables and secrets, first-adoption
guidance, storage migration, recovery, and reuse are documented in
`docs/DEPLOYMENT-AUTOMATION.md`.

## Disposable-host validation performed

On 26 July 2026 the complete stack was exercised against a newly provisioned,
isolated DigitalOcean droplet. The existing production droplet was not placed
under test or modified.

The validation covered:

1. OpenTofu creation of a project, droplet, reserved IP, and firewall.
2. A full Ansible convergence followed by an idempotency run with zero changes
   and zero failures.
3. Fresh application and Reverb image builds and publication to GHCR.
4. Kamal startup of PostgreSQL and Redis plus deployment of the web, worker,
   scheduler, and Reverb roles.
5. HTTPS, `/up`, `/ready`, authentication redirects, SSE streaming, and Reverb
   health checks.
6. Browser login as the disposable seeded administrator, operations dashboard,
   archive listing, and document-detail rendering without console errors.
7. Deployment of a second image version and rollback to the original version,
   followed by another successful fidelity check.
8. OpenTofu destruction of all six disposable resources.

Independent DigitalOcean API checks then confirmed that no tagged test
droplet, project, firewall, or reserved IP remained. The existing production
droplet at `168.144.27.66` remained active.

## Findings from the rehearsal

- The original homepage fidelity assertion looked for client-rendered branding
  in the server HTML. It was corrected to verify the actual Inertia `welcome`
  and `auth/login` components.
- The local `gh auth token` did not have suitable GHCR package permissions.
  The CI-style environment secret and the existing Docker credential worked.
  A package-enabled token should be used for future local publication.
- The Docker build context was approximately 48 MB because render/output
  directories are not comprehensively excluded. Tightening `.dockerignore`
  would make future builds leaner.
- The rehearsal used disposable seeded application data. It proved deployment,
  service, UI, release, rollback, and teardown behavior; it was not a production
  data migration or backup-restore drill.

## Before adopting this in production

- Review the workflow permissions, GitHub Environment protections, variables,
  and secrets described in `docs/DEPLOYMENT-AUTOMATION.md`.
- Decide whether the existing production droplet will be imported into
  OpenTofu state or replaced through a separately approved migration.
- Move regulatory originals and extracted artifacts to durable object storage
  before relying on droplet recreation.
- Configure encrypted PostgreSQL backups and complete a restore drill.
- Use a stable-egress runner or another deliberate strategy for the
  DigitalOcean SSH firewall allowlist.
- Review the local GHCR authentication path and reduce the Docker build
  context.

No production merge or production deployment should be inferred from this
rehearsal.
