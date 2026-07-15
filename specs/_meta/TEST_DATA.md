# Test Data Requirements

All scenarios use deterministic test data. This document lists the canonical fixtures the
automation harness should provide. Tests should reference these by name rather than inventing
new data.

## Identifiers and naming conventions

| Data Type | Constraints | Example |
|-----------|-------------|---------|
| User email (test) | `<name>@example.test` (reserved test TLD) | `alice@example.test` |
| Admin email (test) | `<name>@sahkarai.test` | `ops@sahkarai.test` |
| User password (test) | At least 12 chars, mixed case, digit, symbol | `Str0ng-Pass!1` |
| Stronger user password (test) | Differs by ≥2 chars from baseline | `Strong3r-Pass!2` |
| Document ID | `<SOURCE>-<TYPE_ABBR>-<YYYY>-<SEQ>` | `RBI-CIRC-2026-001` |
| Document Version ID | `DV-<n>` | `DV-1` |
| Chat ID | `CHAT-<n>` or `CHAT-<LABEL>` | `CHAT-001`, `CHAT-A` |
| Issue Report ID | `ISSUE-<n>` | `ISSUE-001` |
| Razorpay subscription ID | `rzp_sub_<n>` | `rzp_sub_abc` |
| Webhook event ID | `evt_<n>` or `tup_<n>` | `evt_001`, `tup_001` |
| Reset token | `RESET-<n>` | `RESET-001` |
| Refresh token (test) | `REFRESH-<LETTER>` | `REFRESH-A` |
| Storage path | `originals/<source_lower>/<yyyy>/<mm>/<source_document_id>.<ext>` | `originals/rbi/2026/05/RBI-CIRC-2026-001.pdf` |

## Canonical users

| Handle | Email | Role | Tier | Locale | Notes |
|--------|-------|------|------|--------|-------|
| alice | alice@example.test | individual_member | tier_2 | en | Reused for general Tier 2 scenarios |
| bob | bob@example.test | individual_member | tier_2 | en | Used to test cross-user RLS denials |
| bea | bea@example.test | individual_member | tier_1 | en | General Tier 1 user |
| ben | ben@example.test | individual_member | tier_1 | en | Used to test "did not view prior version" branches |
| cara | cara@example.test | individual_member | tier_2 | en | Used for chat/credit scenarios |
| fred | fred@example.test | individual_member | free | en | General Free user |
| newuser | newuser@example.test | individual_member | free | (varies) | Used for signup scenarios; account does not pre-exist |
| ghost | ghost@example.test | (no account) | n/a | n/a | Used to test "unknown email" rejections |
| ops | ops@sahkarai.test | saas_admin | n/a | en | Staff |

## Canonical sources

| Source | Display | Adapter kind (PRD §4.2) |
|--------|---------|--------------------------|
| RBI | Reserve Bank of India | HTML scraper |
| IT | Income Tax | (implementation detail) |
| GST | GST | (implementation detail) |

## Canonical Documents

| Document ID | Source | Document Type | Published date | Note |
|-------------|--------|---------------|----------------|------|
| RBI-CIRC-2026-001 | RBI | circular | 2026-05-15 | General Tier 1/2 view target |
| RBI-CIRC-2026-002 | RBI | circular | 2026-05-15 | Used for revision scenarios (DV-1/DV-2) |
| RBI-CIRC-2025-300 | RBI | circular | 2025-09-01 | Backfilled — used for no-notification scenarios |
| IT-NOTIF-2026-007 | IT | notification | 2026-04-09 | Cross-source browse |
| GST-CIRC-2026-012 | GST | circular | 2026-03-01 | Cross-source browse |

## Canonical Document Versions

| Document Version ID | Document ID | Content hash | Supersedes | Status |
|---------------------|-------------|--------------|------------|--------|
| DV-1 | RBI-CIRC-2026-001 | abc123 | null | extraction_ok, interpretation published |
| DV-2 | RBI-CIRC-2026-001 | def456 | DV-1 | extraction_ok, interpretation published |
| DV-3 | (any) | (any) | null | interpretation_failed |
| DV-9 | RBI-CIRC-2026-009 | (any) | null | single-version Document |

## Canonical Interpretations

- Every successfully published Interpretation in fixtures must have all four Locale blocks present unless a scenario explicitly says otherwise.
- Word counts in fixtures: 220 words (en), 200 words (hi), 215 words (gu), 205 words (mr) — to land in the §5.1 150–300 boundary.
- Key takeaways: 5 items per Locale by default — to land in the §5.1 3–7 boundary.
- Applicability tags: `["ucb"]` by default; vary to `[]` to exercise the zero-tags boundary.
- Effective date: `2026-07-01` by default; null in the "no effective date" boundary.
- Compliance deadlines: `[]` by default; multi-deadline fixture used in §5.2 scenarios.

## Canonical Chats

| Chat ID | Owner | Bound DV | Status | Messages |
|---------|-------|----------|--------|----------|
| CHAT-001 | cara | DV-1 | active | 0 to N depending on scenario |
| CHAT-A | cara | DV-1 | active | 4 (sidebar ordering fixture) |
| CHAT-B | cara | DV (any) | closed_context_full | 20+ |
| CHAT-C | cara | DV (any) | closed_by_user | 6 |

## Canonical Razorpay artefacts

- Subscriptions: `rzp_sub_abc` for general Tier 2 user (cara); `rzp_sub_bea` for Tier 1 user.
- Plans: `tier_1_monthly_inr` (₹499 placeholder), `tier_2_monthly_inr` (₹1,499 placeholder).
- Webhook signing secret: provided to the test harness via env var; tests use a fixed valid signature in fixtures, and pre-defined invalid signature `INVALID_SIG`.

## Canonical dates

- Test "today" anchor: `2026-05-15T10:00:00Z`. Other dates expressed relative to this anchor.
- Cycle anniversaries used: `2026-06-01T00:00:00Z` (cara), `2026-06-15T00:00:00Z` (bea).
- Backfill cutoff: any Document Version with `created_via_backfill=true` regardless of published_date.

## Configuration knobs assumed in fixtures

| Config | Default in tests | PRD reference / OQ |
|--------|------------------|--------------------|
| `topup_url` | `null` (v1 default) — set to URL for the corresponding scenarios | §8.5 / CHAT-8.5 |
| `interpretation_retry_max_attempts` (N) | 3 | §4.6 |
| `monthly_credit_allowance` | 200 | §8.3 / OQ-CHAT-002 |
| `context_window_threshold` | provided to tests via config | §14 item 3 / OQ-CHAT-001 |
| `password_reset_token_ttl_minutes` | 60 (Supabase Auth default) | ASSUMPTION-A003 / OQ-AUTH-001 |
| `polling_cadence_per_source` | twice daily, staggered | §4.1 |
| `archive_page_size_default` | 20 | ASSUMPTION-A004 |
