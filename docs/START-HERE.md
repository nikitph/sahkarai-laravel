# Start here

SahkarAI is now a product application, not a generic starter. The Gherkin files in `specs/` are the product contract.

1. Run `./bin/setup`.
2. Open `http://localhost:8002`; use `demo@example.com` / `password` for Tier 2 or `admin@example.com` / `password` for ops.
3. Read `docs/ARCHITECTURE.md`, `docs/SPEC-COVERAGE.md`, and the 319-scenario ledger in `docs/SPEC-EVIDENCE.md`.
4. Put domain behavior in actions/jobs and keep authorization in policies or the admin middleware.
5. Preserve document-version immutability, user ownership, webhook idempotency, and ledger append-only rules.
6. Run `composer verify` before committing.

For live AI/payment work, add the DeepSeek and Razorpay values documented in `.env.example`. Never insert provider calls directly in controllers: AI calls go through Laravel AI SDK agents; payment state changes are confirmed by signed Razorpay webhooks.
