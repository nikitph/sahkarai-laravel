# Laravel deployment kit onboarding

This archive contains the infrastructure, host configuration, Kamal release
configuration, GitHub Actions workflows, operational commands, and detailed
deployment documentation used by SahkarAI.

It is an onboarding accelerator, not a claim that every Laravel application has
the same runtime.

## Install

Extract the archive outside the target repository:

```bash
tar -xzf laravel-deployment-kit.tar.gz
cd laravel-deployment-kit
./install /path/to/repository APP_SLUG GITHUB_OWNER "Display Name"
```

The target must contain `artisan` and `composer.json`. The installer refuses to
merge into existing deployment directories unless `FORCE_INSTALL=true`.

## Mandatory review

Before the first PR:

1. Ensure the repository has a successful GitHub Actions workflow whose
   top-level `name` is `verify`; deployment listens for that exact workflow.
   Adapt `docs/verify.example.yml` if the repository has no equivalent CI.
2. Confirm the Docker image starts the Laravel web process on port 8080.
3. Confirm `/up` is a cheap process liveness endpoint.
4. Implement `/ready` for the target database, cache, and durable storage.
5. Adapt the web, worker, scheduler, and optional Reverb roles.
6. Remove SahkarAI-specific DeepSeek and Razorpay secrets.
7. Review PostgreSQL and Redis versions.
8. Replace fidelity assertions with product-specific checks.
9. Decide whether user uploads belong in S3-compatible object storage.
10. Configure and test database restoration.
11. Protect the GitHub Environments before enabling infrastructure apply.

## Supported baseline

- Laravel with Composer and a production Dockerfile
- GitHub Actions and GHCR
- DigitalOcean
- one Docker host
- PostgreSQL
- Redis
- Kamal 2
- optional Laravel Reverb

Applications with multiple hosts, managed databases, Octane, Kubernetes,
non-Docker runtimes, or another cloud provider need an explicit adaptation.

## Validation

Run:

```bash
tofu -chdir=infra/tofu fmt -check -recursive
tofu -chdir=infra/tofu init -backend=false
tofu -chdir=infra/tofu validate

ansible-galaxy install -r infra/ansible/requirements.yml
ANSIBLE_CONFIG="$PWD/infra/ansible/ansible.cfg" \
  ansible-lint infra/ansible/site.yml infra/ansible/verify.yml

shellcheck ops/*
```

Then open a PR and review the production OpenTofu plan before merging.
