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
| Notifications | 4 | after-commit Reverb/Echo private-channel center, realtime unread badge/toast, read/read-all, deduplicated per-channel delivery log, per-source enablement and email cadence, eligibility/non-trigger rules |
| Payments | 8 | Configured INR plans, Razorpay gateway, signed/idempotent lifecycle and top-up webhooks, proration, queued downgrade/cancel, failure notices, reconciliation |
| Non-functional | 2 | Immutable messages/ledger, delivery audit trail, source health/failure/issue/alert ops dashboard |

## Deliberate adaptations

- The specs use Supabase access/refresh-token language. The shipped initializer already has mature Laravel session authentication, password reset, passkeys, and optional 2FA, so those stronger primitives are retained. Product routes do not require email verification.
- The specs call ownership checks “RLS.” SahkarAI enforces them through policies, owner-scoped Eloquent relationships, transactional domain actions, and cross-user denial tests. Admins have no route or policy to retrieve chat bodies.
- Organization and TOTP scenarios describe dormant v1 surfaces. Organization product routes remain dormant. The initializer's optional 2FA/passkey security page remains available as a deliberate baseline strengthening.
- Pricing is configured in paise (`99900`, `149900`, `249900`) and rendered as INR. No annual or non-INR option exists. Tier 3 inherits document-grounded chat and adds the server-side personalization entitlement.
- Prorated Tier 2 credits round down to whole credits with a minimum grant of one after a successful mid-cycle upgrade.
- Every account has one local entitlement row, including Free accounts, so ownership and state transitions have a uniform boundary. “No Free subscription” in the provider-facing scenarios means `provider_subscription_id` is null and no Razorpay object exists. A checkout target stays in `pending_tier`; current tier and access do not change until a signed webhook arrives.
- Account restoration uses the 30-day temporary signed link sent to the account email. It preserves the spec's proof-of-control requirement without reintroducing an email-verification gate.

## Verification map

- `tests/Feature/SahkarAiProductTest.php`: tier access, registration defaults, admin landing, privacy, credit atomicity/idempotency and cycle reset, context closure/restart pinning, canonical source adapter/acquisition/revisions, malformed candidate auditing, extracted artifacts, structured AI output, shared interpretation metadata, issue capture/triage audit, version-time notification eligibility/revision references, locale fallback, archive sort/tags/snippets, signed subscription/top-up webhooks, proration, and consecutive poll alerts.
- Existing auth/settings/organization/conformance tests continue to protect initializer behavior.
- `composer verify` runs formatting, PHPStan, the complete PHP test suite, ESLint, TypeScript, and a production Vite build.
- `docs/SPEC-EVIDENCE.md` inventories every one of the 59 feature files and maps all 319 declared scenarios to their primary implementation and verification evidence.
- The Docker/PostgreSQL smoke pass covers both a clean 17-migration seed and rollback/re-upgrade from legacy locale-shaped metadata, then exercises the compiled production image as web/worker/scheduler/Reverb roles.

Last offline conformance run (2026-07-16): 97 tests passed with no skips and 542 assertions. ESLint, Prettier, TypeScript, Pint, PHPStan, Vite production build, PostgreSQL 17 clean/upgrade migrations, Docker health, PostgreSQL full-text search, and compiled browser checks passed.

## Live provider status

Automated tests use Laravel AI SDK fakes and signed Razorpay fixtures. The deterministic suite has no credential-dependent tests or skips. On 2026-07-16, `php artisan sahkarai:providers:verify --only=ai` passed against DeepSeek for both validated structured output and streaming; the provider reported `deepseek-v4-flash`. No key was stored in source or the image. Razorpay read-only plan validation and one real test-mode checkout/webhook cycle remain pending until those credentials are supplied.
