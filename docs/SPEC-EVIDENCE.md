# Gherkin conformance evidence

This inventory is the review ledger for all **59 feature files / 319 declared scenarios** in `specs/`.
The Gherkin is the product contract, not an executable parallel test stack: behavior is proven through Laravel feature tests, policy tests, AI SDK fakes, signed provider fixtures, PostgreSQL/Docker smoke tests, and compiled-browser checks. Scenario outlines are exercised through enum/config boundaries and representative locale/tier/source cases rather than copied into a second step-definition framework.

| Feature | Scenarios | Primary implementation and evidence |
|---|---:|---|
| `account/account_deletion` | 6 | `ProfileController`, `AccountRestoreController`, `PurgeExpiredAccounts`; soft-delete, provider cancel, signed restore, expiry and personal-data purge tests |
| `account/account_settings` | 11 | profile/security controllers and settings UI; profile/password tests plus tier-scoped ledger/settings assertions |
| `archive/archive_browse` | 10 | `ArchiveController`, `ArchiveSearch`, archive index UI; pagination/filter/sort and tier capability tests |
| `archive/archive_document_detail` | 6 | revision-pinned `ArchiveController::show/download`, document-view tracking and archive UI; revision navigation tests |
| `archive/archive_search` | 8 | PostgreSQL weighted full-text/phrase search with portable SQLite fallback; search snippet/tag tests and PostgreSQL probes |
| `auth_and_access/admin_authentication` | 4 | Fortify authentication plus admin middleware/policy; auth and admin landing tests |
| `auth_and_access/password_reset` | 5 | Fortify reset flow and localized reset notification; stock auth/reset tests |
| `auth_and_access/rls_chat_privacy` | 9 | owner-bound `ChatPolicy`, scoped relationships and route authorization; cross-user read/write/export denial tests |
| `auth_and_access/rls_ingestion_writes` | 2 | ingestion has no user route; jobs/actions own writes and ops routes require admin |
| `auth_and_access/rls_interpretation_access` | 5 | tier policies and server-side serialization; Free/Tier 1/Tier 2 archive tests |
| `auth_and_access/rls_subscription_privacy` | 5 | one-to-one owner subscription policy; billing routes derive subscription from authenticated user |
| `auth_and_access/role_individual_member` | 4 | `UserRole`, registration defaults, product policies; registration test |
| `auth_and_access/role_org_dormant` | 5 | no organization product routes; stronger initializer organization primitives remain isolated/dormant |
| `auth_and_access/role_saas_admin` | 8 | admin middleware, ops-only routes and metadata-only chat access; ops lookup/triage tests |
| `auth_and_access/session_management` | 5 | Laravel session regeneration/logout/remember behavior; authentication suite |
| `auth_and_access/signin` | 6 | Fortify login/throttle/session flow; authentication suite |
| `auth_and_access/signup` | 7 | locale-aware registration, Free subscription and source defaults; registration test |
| `auth_and_access/tier_1_capabilities` | 6 | `Tier` capability methods, policies and UI serialization; archive/chat/notification tests |
| `auth_and_access/tier_2_capabilities` | 5 | `Tier` capabilities, credit ledger and chat policies; chat/credit/export tests |
| `auth_and_access/tier_free_capabilities` | 7 | Free policy gates and upgrade UI; original-only archive test |
| `auth_and_access/tier_matrix` | 7 | centralized `Tier` enum used by every policy/controller; tier matrix integration tests |
| `auth_and_access/totp_dormant` | 5 | no TOTP requirement on product routes; optional initializer 2FA/passkeys retained and tested |
| `chat/chat_context_full` | 5 | atomic projected-token closure and pinned restart; equality/overflow/restart/export tests |
| `chat/chat_creation` | 5 | `ChatController::store` with policy, user locale and revision binding; creation/capability tests |
| `chat/chat_credit_exhaustion` | 5 | `SendChatMessage`, stable rejection reason and reset copy; zero-credit read/export/write tests |
| `chat/chat_credits` | 6 | immutable `CreditLedger`, atomic/idempotent debit and cycle grant/expiry; credit tests |
| `chat/chat_exports` | 6 | owner-authorized JSON/Markdown/PDF export with metadata, revision, messages and insights; all-format tests |
| `chat/chat_scope` | 7 | immutable document-version binding, no share/upload/voice routes or controls; pinned restart/privacy tests |
| `chat/chat_surface` | 8 | owner-scoped activity ordering, badges, reference panel and composer state in chat UI; controller serialization tests |
| `chat/chat_topup_cta_dormant` | 4 | config-gated CTA, no purchase route, signed webhook-only `topup` ledger writes; top-up tests |
| `i18n/email_localization` | 2 | locale-bound regulatory/reset notifications and translation catalog; notification tests |
| `i18n/ui_localization` | 4 | persisted `SupportedLocale` and en/hi/gu/mr client bundle; locale update/display tests |
| `ingestion/acquisition_storage` | 5 | `AcquireDocument`, checksum dedupe, durable originals and canonical IDs; acquisition tests |
| `ingestion/discovery_dedup` | 4 | source adapter contract, unique source identity and auditable malformed candidates; poll tests |
| `ingestion/extraction` | 4 | idempotent extractor, durable text artifact, retry/terminal status; extraction tests |
| `ingestion/historical_backfill` | 3 | one-year backfill command, source jobs and notification suppression; backfill/non-trigger tests |
| `ingestion/interpretation_generation` | 7 | Laravel AI SDK structured agent, per-locale attempts/retries and separate status; AI fake tests |
| `ingestion/polling` | 7 | twice-daily source schedules, partial/failed runs and three-failure alert; poll tests and schedule inspection |
| `ingestion/versioning_revisions` | 6 | checksum-based linked versions and prior-view notification eligibility; revision tests |
| `interpretation/interpretation_locale_display` | 4 | requested locale selection without profile mutation; locale switching tests |
| `interpretation/interpretation_locale_fallback` | 3 | English fallback and explicit fallback marker; locale fallback tests |
| `interpretation/interpretation_payload` | 11 | AI SDK structured schema and domain validation for summary/takeaways/glossary/deadlines/applicability; validation tests |
| `interpretation/interpretation_provenance` | 2 | model ID, prompt version and generated timestamp persisted/rendered; generation tests |
| `interpretation/interpretation_report_issue` | 8 | exact interpretation/version/locale issue capture and audited admin triage; issue tests |
| `interpretation/interpretation_terminal_failure` | 4 | three-attempt terminal failure, ops count and unavailable UI state; generation failure tests |
| `non_functional/auditability` | 6 | immutable messages/ledger, fixed `CreditReason`, delivery records and timestamps; immutability/delivery tests |
| `non_functional/ops_dashboard` | 4 | source health, separate extraction/interpretation counts, open issues/alerts and user lookup; ops tests |
| `notifications/notification_channels` | 4 | after-commit owner-private Reverb/Echo delivery, realtime unread badge/toast, owner-scoped read/read-all, uniquely deduplicated per-channel delivery log; channel, center and compiled-browser tests |
| `notifications/notification_non_triggers` | 3 | backfill, failed interpretation and disabled-source guards; notification tests |
| `notifications/notification_preferences` | 6 | per-source enabled/cadence only, locale delivery and strict cadence validation; preference/UI tests |
| `notifications/notification_triggers` | 4 | subscription-start and prior-version-view eligibility with idempotent dedupe; trigger tests |
| `payments/payment_cancellation` | 3 | provider-scheduled cancellation and local pending Free transition; billing UI/controller tests |
| `payments/payment_downgrade` | 6 | provider cycle-end change, local pending state, resume and anniversary job; deferred-transition tests |
| `payments/payment_failed_renewal` | 4 | provider retry remains authoritative; each failure logs email/in-app, halted expiry downgrades/notifies |
| `payments/payment_plans` | 6 | INR monthly plan/config contract and checkout payload; pricing/checkout tests |
| `payments/payment_refunds` | 2 | no user refund route; signed refund webhook creates idempotent adjustment entry |
| `payments/payment_topup_dormant` | 4 | no purchase route; signed/idempotent constrained top-up webhook; webhook tests |
| `payments/payment_upgrade` | 5 | Checkout launch without premature access, webhook activation, proration and invalid transition gates; billing tests |
| `payments/payment_webhooks` | 6 | signature verification, event idempotency, lifecycle transitions and daily drift reconciliation; webhook/reconciliation tests |

## Evidence levels

- **Direct feature tests** protect state transitions, authorization boundaries, idempotency, response contracts and failure paths.
- **Static/build checks** protect PHP types/formatting and the React/TypeScript production bundle.
- **PostgreSQL checks** protect database-specific migrations, JSONB, locking, and weighted full-text search that SQLite cannot prove.
- **Container/browser checks** protect the shipped FrankenPHP image, worker/scheduler/Reverb roles, compiled assets and user-facing navigation.
- **Live-provider checks** are intentionally the final layer: DeepSeek and Razorpay test mode require credentials and are tracked separately from deterministic offline conformance.
