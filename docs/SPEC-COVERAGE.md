# Specification coverage

The source contract contains 59 Gherkin feature files. TypeScript files alongside them are test-authoring helpers and are intentionally ignored.

| Area | Feature files | Implementation |
|---|---:|---|
| Account | 2 | Profile/password/locale/source preferences, tier summary, 30-day soft delete, signed restore, provider cancellation, purge/anonymization job |
| Archive | 3 | Latest-version browse, linked revision history, revision-pinned downloads and chat, 20-row pagination, title/newest sort, source/type/date/tag filters, literal/quoted search, English relevance bias, detail/download |
| Auth and access | 17 | Laravel Fortify sessions, registration/reset, user/admin roles, tier policies, owner-scoped product data, dormant organization routes |
| Chat | 8 | Version-bound private chats, streaming Laravel AI SDK responses, atomic/idempotent debits, context closure, reset copy, dormant top-up CTA, exports |
| Internationalization | 2 | Static en/hi/gu/mr UI bundle, persisted locale, localized regulatory/reset mail, English interpretation fallback |
| Ingestion | 7 | Scheduled source adapters, auditable/partial polls with candidate errors, three-failure alerts, canonical source-ID originals, SHA dedupe, durable extracted-text artifacts plus searchable mirror, revisioning, backfill, generation retries |
| Interpretation | 6 | Laravel AI SDK structured responses, 150–300-word validation, provenance, applicability metadata, locale switch/fallback, version/locale-aware issue reporting, terminal failure surface |
| Notifications | 4 | Real-time in-app center, read/read-all, immutable delivery log, per-source enablement and email cadence, eligibility/non-trigger rules |
| Payments | 8 | Configured INR plans, Razorpay gateway, signed/idempotent lifecycle and top-up webhooks, proration, queued downgrade/cancel, failure notices, reconciliation |
| Non-functional | 2 | Immutable messages/ledger, delivery audit trail, source health/failure/issue/alert ops dashboard |

## Deliberate adaptations

- The specs use Supabase access/refresh-token language. The shipped initializer already has mature Laravel session authentication, password reset, passkeys, and optional 2FA, so those stronger primitives are retained. Product routes do not require email verification.
- The specs call ownership checks “RLS.” SahkarAI enforces them through policies, owner-scoped Eloquent relationships, transactional domain actions, and cross-user denial tests. Admins have no route or policy to retrieve chat bodies.
- Organization and TOTP scenarios describe dormant v1 surfaces. Organization product routes remain dormant. The initializer's optional 2FA/passkey security page remains available as a deliberate baseline strengthening.
- Pricing is configured in paise (`49900`, `149900`) and rendered as INR. No annual or non-INR option exists.
- Prorated Tier 2 credits round down to whole credits with a minimum grant of one after a successful mid-cycle upgrade.

## Verification map

- `tests/Feature/SahkarAiProductTest.php`: tier access, registration defaults, privacy, credit atomicity/idempotency and cycle reset, context closure/restart pinning, canonical acquisition/revisions, extracted artifacts, structured AI output, issue capture, notification eligibility/revision references, locale fallback, archive sort/tags, signed subscription/top-up webhooks, proration, candidate poll errors, and consecutive poll alerts.
- Existing auth/settings/organization/conformance tests continue to protect initializer behavior.
- `composer verify` runs formatting, PHPStan, the complete PHP test suite, ESLint, TypeScript, and a production Vite build.
- The Docker/PostgreSQL smoke pass migrates and seeds an isolated database and exercises the compiled production image.

## Pending live credentials

Automated tests use Laravel AI SDK fakes and signed Razorpay fixtures. One live DeepSeek interpretation/chat run and one Razorpay test-mode checkout/webhook cycle remain pending until credentials are supplied. No production secret is required or committed for offline conformance.
