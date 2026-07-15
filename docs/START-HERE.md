# Start here

This starter removes horizontal SaaS decisions so work can begin with the business use case.

1. Run `./bin/setup` (Docker Desktop is the only prerequisite).
2. Open `http://localhost:8002` and sign in as `demo@example.com` / `password`.
3. Read `docs/ARCHITECTURE.md`.
4. Describe the first business capability to an agent and tell it to copy the Projects vertical slice.
5. Run `composer verify` before merging.

Rename `APP_NAME`, the Composer package name, Kamal `service`/`image`, and the Docker `SERVICE` build argument before the first deploy. Keep app and Reverb as separate Kamal service labels/tags.
