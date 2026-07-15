# Traceability Matrix

Each PRD section maps to one or more feature files. Each feature file maps back to the PRD via
its `# Trace:` block. Scenario IDs are inferred and stable (see `specs/README.md`).

| PRD Reference | Requirement Summary | Feature File(s) | Inferred Requirement ID |
|---|---|---|---|
| §2.1 | Roles (RBAC): saas_admin, individual_member reachable; org_admin/org_member schema-only | `auth_and_access/role_individual_member.feature`, `auth_and_access/role_saas_admin.feature`, `auth_and_access/role_org_dormant.feature` | ROLE-2.1-INDIV, ROLE-2.1-ADMIN, ROLE-2.1-ORG |
| §2.2 | Tier feature access — Free | `auth_and_access/tier_free_capabilities.feature` | TIER-2.2-FREE |
| §2.2 | Tier feature access — Tier 1 | `auth_and_access/tier_1_capabilities.feature` | TIER-2.2-T1 |
| §2.2 | Tier feature access — Tier 2 | `auth_and_access/tier_2_capabilities.feature` | TIER-2.2-T2 |
| §2.3 | Canonical tier access matrix | `auth_and_access/tier_matrix.feature` | MATRIX-2.3 |
| §2.4 | Admin access (ops dashboard, triage, lookup) | `auth_and_access/role_saas_admin.feature`, `non_functional/ops_dashboard.feature` | ADMIN-2.4, NFR-12.5 |
| §2.5 | RLS — Chat privacy | `auth_and_access/rls_chat_privacy.feature` | RLS-2.5-CHAT |
| §2.5 | RLS — Interpretation access | `auth_and_access/rls_interpretation_access.feature` | RLS-2.5-INTERP |
| §2.5 | RLS — Ingestion writes service-role only | `auth_and_access/rls_ingestion_writes.feature` | RLS-2.5-INGEST |
| §2.5 | RLS — Subscription & ledger privacy | `auth_and_access/rls_subscription_privacy.feature` | RLS-2.5-SUB |
| §3.1 | Email + password sign-up; no verification gate | `auth_and_access/signup.feature` | AUTH-SIGNUP-3.1 |
| §3.1 | Email + password sign-in | `auth_and_access/signin.feature` | AUTH-SIGNIN-3.1 |
| §3.2 | Password reset via emailed link | `auth_and_access/password_reset.feature` | AUTH-RESET-3.2 |
| §3.3 | TOTP dormant — no UI surface | `auth_and_access/totp_dormant.feature` | AUTH-TOTP-3.3 |
| §3.4 | Session management | `auth_and_access/session_management.feature` | AUTH-SESSION-3.4 |
| §3.5 | Admin authentication uses standard sign-in | `auth_and_access/admin_authentication.feature` | AUTH-ADMIN-3.5 |
| §4.1 | Source polling produces auditable poll runs | `ingestion/polling.feature` | ING-POLL-4.1 |
| §4.2 | Adapter discovery and (source, source_document_id) dedupe | `ingestion/discovery_dedup.feature` | ING-DISC-4.2 |
| §4.3 | Acquisition and local storage of originals | `ingestion/acquisition_storage.feature` | ING-ACQ-4.3 |
| §4.4 | Text extraction; extraction_failed state | `ingestion/extraction.feature` | ING-EXT-4.4 |
| §4.5 | Document versioning and revision linking | `ingestion/versioning_revisions.feature` | ING-VER-4.5 |
| §4.6 | Interpretation generation pipeline | `ingestion/interpretation_generation.feature` | ING-INTERP-4.6 |
| §4.7 | Historical backfill (1 year) | `ingestion/historical_backfill.feature` | ING-BACK-4.7 |
| §5.1, §5.2 | Per-Locale content + Locale-independent metadata | `interpretation/interpretation_payload.feature` | INTERP-5.1, INTERP-5.2 |
| §5.3 | Provenance (model_id, prompt_version, generated_at) | `interpretation/interpretation_provenance.feature` | INTERP-5.3 |
| §5.4 | Locale display defaults and switcher | `interpretation/interpretation_locale_display.feature` | INTERP-5.4-DISPLAY |
| §5.4 | Locale fallback to English with banner | `interpretation/interpretation_locale_fallback.feature` | INTERP-5.4-FALLBACK |
| §5.5 | Report Issue affordance | `interpretation/interpretation_report_issue.feature` | INTERP-5.5 |
| §5.6 | Terminal interpretation failure | `interpretation/interpretation_terminal_failure.feature` | INTERP-5.6 |
| §6.1 | Browse the Archive | `archive/archive_browse.feature` | ARCH-6.1 |
| §6.2 | Search the Archive | `archive/archive_search.feature` | ARCH-6.2 |
| §6.3 | Document Version detail page | `archive/archive_document_detail.feature` | ARCH-6.3 |
| §7.1 | Notification channels (in-app + email) | `notifications/notification_channels.feature` | NOTIF-7.1 |
| §7.2 | Notification triggers | `notifications/notification_triggers.feature` | NOTIF-7.2 |
| §7.3 | Notification non-triggers | `notifications/notification_non_triggers.feature` | NOTIF-7.3 |
| §7.4, §7.5 | Notification preferences and out-of-scope filters | `notifications/notification_preferences.feature` | NOTIF-7.4 |
| §8.1, §8.9 | Chat scope and ownership; v1 out-of-scope assertions | `chat/chat_scope.feature` | CHAT-8.1 |
| §8.2 | Chat creation | `chat/chat_creation.feature` | CHAT-8.2 |
| §8.3 | Credits — monthly allowance and per-message debit | `chat/chat_credits.feature` | CHAT-8.3 |
| §8.4 | Credit exhaustion soft-stop | `chat/chat_credit_exhaustion.feature` | CHAT-8.4 |
| §8.5 | Top-up CTA wired but disabled | `chat/chat_topup_cta_dormant.feature` | CHAT-8.5 |
| §8.6 | Chat surface layout | `chat/chat_surface.feature` | CHAT-8.6 |
| §8.7 | Context window handling and clean-slate restart | `chat/chat_context_full.feature` | CHAT-8.7 |
| §8.8 | Chat exports (json, md, pdf) | `chat/chat_exports.feature` | CHAT-8.8 |
| §9.1 | Account settings | `account/account_settings.feature` | ACCT-9.1 |
| §9.2 | Account deletion (30-day soft-delete) | `account/account_deletion.feature` | ACCT-9.2 |
| §10.1, §10.2 | Plans (Free / Tier 1 / Tier 2) | `payments/payment_plans.feature` | PAY-10.2 |
| §10.3 | Upgrades (Free→T1/T2, T1→T2 prorated) | `payments/payment_upgrade.feature` | PAY-10.3 |
| §10.4 | Downgrades queued to anniversary | `payments/payment_downgrade.feature` | PAY-10.4 |
| §10.5 | Cancellation = downgrade to Free | `payments/payment_cancellation.feature` | PAY-10.5-CANCEL |
| §10.5 | Failed renewal flow | `payments/payment_failed_renewal.feature` | PAY-10.5-FAILED |
| §10.6 | Razorpay webhook verification, idempotency, reconciliation | `payments/payment_webhooks.feature` | PAY-10.6 |
| §10.7 | Top-up infrastructure wired but unused | `payments/payment_topup_dormant.feature` | PAY-10.7 |
| §10.8 | Refunds — manual via support | `payments/payment_refunds.feature` | PAY-10.8 |
| §11 | UI localisation in en/hi/gu/mr; originals never translated | `i18n/ui_localization.feature` | I18N-11-UI |
| §11, §7.4 | Email content in recipient's Locale | `i18n/email_localization.feature` | I18N-11-EMAIL |
| §12.3 | Auditability — messages, ledger, notifications | `non_functional/auditability.feature` | NFR-12.3 |
| §12.5, §2.4 | Ops dashboard observability surfaces | `non_functional/ops_dashboard.feature` | NFR-12.5 |
| §13 | Data model sketch (referenced by RLS + ledger + chats specs) | `auth_and_access/rls_*.feature`, `chat/chat_credits.feature` | DATA-13 |
| §14 | Placeholders pending pricing/threshold/model decisions | OPEN_QUESTIONS.md OQ-PAY-001, OQ-INTERP-001, OQ-CHAT-001, OQ-NOTIF-001 | N/A (gate) |
| §15 | Conversion notes — observed by file layout | `specs/README.md` | N/A (doc) |

## Coverage notes

- The `out of scope for v1` items in the PRD preamble are covered by the `@dormant` and negative-route scenarios in `auth_and_access/totp_dormant.feature`, `auth_and_access/role_org_dormant.feature`, `chat/chat_topup_cta_dormant.feature`, and `payments/payment_topup_dormant.feature`.
- `§12.1 Hosting`, `§12.2 Security boundaries`, and `§12.4 Availability` are environment/SRE concerns and do not produce user-observable scenarios. See COVERAGE_GAPS.md.
