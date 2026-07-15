# NOTE: no `# syntax=` directive on purpose — it forces BuildKit to fetch the
# dockerfile frontend from Docker Hub on every build, which makes builds fail
# whenever Hub is rate-limited/unreachable. The builtin frontend is sufficient.

# ── SahkarAI Laravel application ─────────────────────────────────────────
# Multi-stage build on the company runtime. The final image contains NO node
# runtime, NO dev dependencies, NO source maps and NO build credentials.

# The company runtime, pinned by INDEX digest so the multi-arch manifest list is
# preserved (pinning a per-arch child digest would silently lock us to one CPU).
# Override at build time: --build-arg RUNTIME=...
ARG RUNTIME=ghcr.io/nikitph/laravel-runtime@sha256:620466b2b930f1d21754151ef0f2a96e4c32a3cec7ac02d82657de852aca6c89  # 1.0.0

# ---- Stage 1: composer — optimized production PHP deps -----------------
FROM ${RUNTIME} AS vendor
USER root
WORKDIR /build
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY . .
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative

# ---- Stage 2: Node toolchain source ------------------------------------
# Wayfinder's Vite plugin shells out to `php artisan` during the asset build,
# so a textbook Node-only stage cannot build Laravel's official React starter.
# Pull Node from the ECR Public mirror, then add only its toolchain to a
# disposable PHP runtime stage below.
FROM public.ecr.aws/docker/library/node:22-bookworm-slim AS node-toolchain

# ---- Stage 3: PHP + Node — compile frontend assets ---------------------
FROM ${RUNTIME} AS assets
USER root
WORKDIR /build

COPY --from=node-toolchain /usr/local/bin/node /usr/local/bin/node
COPY --from=node-toolchain /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
 && ln -s /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx

# Artisan must be able to boot with production vendor dependencies when Vite
# invokes Wayfinder. No host-generated route/types output is used as a shortcut.
COPY --from=vendor /build .
RUN npm ci --no-audit --no-fund \
 && npm run build

# ---- Stage 4: the runtime ----------------------------------------------
FROM ${RUNTIME}
USER root
WORKDIR /var/www/html

COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /build/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /build/public/build ./public/build

# Laravel's writable paths (contents are .dockerignore'd, so recreate them).
RUN mkdir -p storage/framework/cache/data storage/framework/sessions \
             storage/framework/views storage/logs bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache

USER www-data

# Kamal refuses to deploy an image without a `service` label matching deploy.yml's
# `service:`. Kamal stamps this itself when IT builds — but our pipeline builds in
# CI/buildx, so we must add it explicitly or `kamal deploy` fails with
# "Image ... is missing the 'service' label". Per-product: --build-arg SERVICE=<name>.
ARG SERVICE=sahkarai-laravel
LABEL service="${SERVICE}"

# Entrypoint / CMD (frankenphp run) / healthcheck inherited from the runtime.
