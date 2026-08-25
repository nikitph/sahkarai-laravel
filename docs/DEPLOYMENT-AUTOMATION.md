# Reproducible production deployment

SahkarAI production is reconstructed and deployed from version-controlled
declarations:

```text
OpenTofu -> DigitalOcean resources
Ansible  -> host configuration
Kamal    -> immutable application releases
GitHub Actions -> merge orchestration and safety gates
```

The deployment system deliberately composes established tools. It does not
implement a custom control plane or duplicate Forge.

## Production topology

One DigitalOcean droplet runs Docker and Kamal Proxy. The same immutable
application image runs the web, worker, and scheduler roles. A second service
uses the image for Reverb. PostgreSQL and Redis are durable Kamal accessories.

OpenTofu owns:

- the DigitalOcean project;
- the droplet and reserved IP;
- the cloud firewall;
- the optional private, versioned Spaces bucket.

Ansible owns:

- the deployment user and SSH access;
- Docker installation through `geerlingguy.docker`;
- UFW, fail2ban, automatic security updates, and host packages;
- the optional encrypted restic/PostgreSQL backup timer;
- database backup and explicitly confirmed restore commands.

Kamal owns:

- GHCR image rollout;
- web, worker, scheduler, and Reverb containers;
- PostgreSQL and Redis accessories;
- TLS termination and application health checks;
- release rollback.

## What happens after a PR merge

Every push and pull request must first pass the `verify` workflow. A successful
verified revision on `main` triggers `deploy production`.

1. The workflow classifies whether `infra/` changed.
2. OpenTofu creates an immutable plan artifact for the verified merge SHA.
3. Infrastructure changes enter the protected `production-infrastructure`
   GitHub Environment; after approval, OpenTofu applies that exact plan.
4. Ansible idempotently converges the resulting host.
5. GitHub builds immutable application and Reverb images tagged with the
   verified commit SHA.
6. A database backup runs before migrations when backups are enabled.
7. Kamal activates the new application release and then Reverb.
8. `ops/verify-production` checks liveness, database, cache, regulatory
   storage, authentication redirects, SSE, and Reverb.
9. A failed post-deployment fidelity check rolls the containers back to the
   previously active image.

The workflow has a `production` concurrency lock, so two merges cannot deploy
simultaneously.

Infrastructure planning and apply are additionally gated by
`INFRASTRUCTURE_AUTOMATION_ENABLED=true`. When the flag is absent or false,
even a merge containing `infra/` changes deploys the application to the
configured host without touching OpenTofu. This is the safe bootstrap mode
while the authoritative state is held locally or remote state is unavailable.

Database migrations must remain backward compatible with the preceding
application release. Container rollback cannot reverse a destructive schema
migration.

## One-time prerequisites

Install locally:

```bash
brew install opentofu ansible ansible-lint shellcheck
gem install kamal --version 2.6.1
```

Create a private DigitalOcean Spaces bucket for OpenTofu state. This bucket is
the only intentionally manual bootstrap resource because OpenTofu cannot store
its state in a bucket before that bucket exists.

Copy `infra/tofu/backend.hcl.example` outside the repository, replace its
values, and keep the resulting file private.

Create these GitHub Environments:

- `production`: application secrets and normal deployment controls;
- `production-infrastructure`: required reviewer protection for `tofu apply`;
- `production-infrastructure-plan`: read-only infrastructure planning secrets.

Protect `main`:

- require a pull request;
- require the `verify` workflow;
- prohibit direct pushes;
- dismiss stale approvals;
- prevent force pushes.

## GitHub variables

Repository or environment variables:

| Variable | Purpose |
| --- | --- |
| `DEPLOY_HOST` | Reserved IP when infrastructure is unchanged |
| `APP_HOST` | Public application hostname |
| `DEPLOY_REVERB_HOST` | Public Reverb hostname |
| `DEPLOY_USER` | Normally `deploy` |
| `INFRASTRUCTURE_AUTOMATION_ENABLED` | `true` only after remote OpenTofu state and infrastructure environment protections are ready |
| `REVERB_ENABLED` | `true` for SahkarAI; set `false` in apps without Reverb |
| `BACKUP_ENABLED` | Enables pre-deploy and scheduled database backups |
| `REGULATORY_STORAGE_DISK` | Use `s3` after object-storage migration |
| `SPACES_REGION` | Spaces region, currently `blr1` |
| `SPACES_BUCKET_NAME` | Regulatory originals and backup bucket |
| `SPACES_ENDPOINT` | For example `https://blr1.digitaloceanspaces.com` |
| `CREATE_SPACES_BUCKET` | Whether OpenTofu creates the application bucket |
| `TOFU_STATE_BUCKET` | Existing private state bucket |
| `TOFU_STATE_KEY` | Defaults to `sahkarai/production/tofu.tfstate` |
| `TOFU_STATE_REGION` | State bucket region |

## GitHub secrets

Infrastructure:

- `DIGITALOCEAN_TOKEN`
- `DO_SSH_KEY_FINGERPRINTS_JSON`
- `DO_SSH_ALLOWED_CIDRS_JSON`
- `INFRA_SSH_PRIVATE_KEY`
- `DEPLOY_SSH_PUBLIC_KEY`
- `TOFU_STATE_ACCESS_KEY_ID`
- `TOFU_STATE_SECRET_ACCESS_KEY`

Deployment:

- `DEPLOY_SSH_PRIVATE_KEY`
- `GHCR_TOKEN`
- `APP_KEY`
- `DB_PASSWORD`
- `POSTGRES_PASSWORD`
- `REVERB_APP_SECRET`
- `DEEPSEEK_API_KEY`
- the Razorpay keys and plan IDs already listed in the workflow

Durable storage and backups:

- `SPACES_ACCESS_KEY_ID`
- `SPACES_SECRET_ACCESS_KEY`
- `BACKUP_RESTIC_REPOSITORY`
- `BACKUP_RESTIC_PASSWORD`
- `BACKUP_S3_ACCESS_KEY`
- `BACKUP_S3_SECRET_KEY`

JSON list secrets use this form:

```json
["fingerprint-one", "fingerprint-two"]
```

```json
["203.0.113.4/32"]
```

The workflow must be able to reach port 22. Prefer a self-hosted runner or
runner with stable egress and restrict `DO_SSH_ALLOWED_CIDRS_JSON` to that
address plus trusted operator addresses. Standard GitHub-hosted runner egress
is not stable; if a broad rule is temporarily unavoidable, SSH still requires
keys, password authentication is disabled, and the rule should be narrowed as
soon as a stable runner is available.

## First infrastructure adoption

Never run the first production `tofu apply` without reviewing whether the
existing droplet should be imported or replaced.

For an existing droplet:

```bash
cd infra/tofu
tofu init -backend-config=/secure/path/backend.hcl
tofu import digitalocean_droplet.application DROPLET_ID
tofu plan
```

Import any existing project, reserved IP, firewall, or Spaces bucket into their
matching resource addresses before applying. If the current server is
disposable, approve a new reserved-IP deployment instead and restore data
before switching traffic.

The droplet has `prevent_destroy = true`. External deletion is repaired by the
next apply, but a reviewed code change is required before OpenTofu may
intentionally destroy or replace the managed droplet.

## Local operation

Review infrastructure:

```bash
ops/bootstrap-production
```

Apply and configure:

```bash
export DIGITALOCEAN_TOKEN=...
export DEPLOY_SSH_PUBLIC_KEY='ssh-ed25519 ...'
ops/bootstrap-production --apply
```

Verify production:

```bash
APP_URL=https://app.example.com \
REVERB_URL=https://ws.example.com \
ops/verify-production
```

Roll back to a known image SHA:

```bash
ops/rollback-production COMMIT_SHA
```

## Storage migration

Kamal's Docker volume survives container replacement but not droplet deletion.
Do not switch `REGULATORY_STORAGE_DISK` to `s3` until existing originals and
extracted text have been copied and verified in Spaces.

The migration sequence is:

1. create the private versioned bucket;
2. copy every `storage/app` regulatory object while the application still uses
   `local`;
3. compare object counts and SHA-256 values;
4. set `REGULATORY_STORAGE_DISK=s3` in the production environment;
5. deploy and confirm `/ready`, downloads, extraction, and uploads;
6. retain the Docker volume until a restore drill succeeds.

### Current local-storage bootstrap mode

If Spaces is temporarily unavailable, keep
`INFRASTRUCTURE_AUTOMATION_ENABLED=false`, `REGULATORY_STORAGE_DISK=local`, and
`BACKUP_ENABLED=false`. OpenTofu's authoritative local state must live outside
the repository on a protected operator machine and be backed up independently.
DigitalOcean droplet backups reduce recovery risk, but they are not a substitute
for remote OpenTofu state, application-level PostgreSQL backups, or object
storage. Before enabling infrastructure automation, create the private state
bucket, migrate the local state into it, run `tofu plan` expecting no changes,
and configure the protected GitHub infrastructure environments.

## Recovery

If the droplet is deleted:

1. merge or dispatch the infrastructure workflow;
2. approve the OpenTofu apply;
3. Ansible configures the recreated host;
4. the deployment job boots PostgreSQL and Redis;
5. restore PostgreSQL with the host restore command;
6. deploy the verified image;
7. run production fidelity checks;
8. confirm regulatory objects directly from Spaces.

Backups are not considered valid until a restore has been tested on a separate
database or disposable host.

## Reusing the pipeline

Run `ops/package-laravel-deployment-kit` to create the portable onboarding
archive under `output/deployment/`. Extract it into another Laravel repository
and follow its `ONBOARDING.md`.

The kit assumes:

- a containerized Laravel application;
- GitHub Actions and GHCR;
- one DigitalOcean Docker host;
- PostgreSQL and Redis;
- a liveness endpoint at `/up`;
- an optional dependency readiness endpoint at `/ready`;
- optional Reverb.

Application-specific secrets, runtime commands, health assertions, and storage
paths must be reviewed during onboarding. Infrastructure and deployment
automation can be reused; product behavior cannot be inferred safely.
