# SahkarAI v1 — Gherkin Specification Package

This package is the executable behavioural contract for SahkarAI v1, derived from `docs/prd.md`.

## Source of truth precedence

1. The PRD §2.3 Tier Matrix is canonical for access control. If any scenario in this package contradicts the matrix, the matrix wins.
2. PRD text wins over any inferred behaviour in these specs.
3. Items in `_meta/OPEN_QUESTIONS.md` are unresolved and must be settled before the affected scenarios are runnable.

## Directory layout

```
specs/
  _meta/
    TRACEABILITY.md      # PRD section → scenario ID matrix
    ASSUMPTIONS.md       # Behaviour inferred but not explicit in PRD
    OPEN_QUESTIONS.md    # Ambiguities/contradictions/gaps for Product
    COVERAGE_GAPS.md     # Areas the PRD does not specify and we cannot scenarioise
    RISKS.md             # Concurrency, security, ambiguity risks
    TEST_DATA.md         # Deterministic test data fixtures
  auth_and_access/       # PRD §2 + §3
  ingestion/             # PRD §4
  interpretation/        # PRD §5
  archive/               # PRD §6
  notifications/         # PRD §7
  chat/                  # PRD §8
  account/               # PRD §9
  payments/              # PRD §10
  i18n/                  # PRD §11
  non_functional/        # PRD §12
```

## Tag taxonomy

Tags are stable and CI-sliceable.

### Area tags
`@auth` `@ingestion` `@interpretation` `@archive` `@notifications` `@chat` `@account` `@payments` `@i18n` `@nfr` `@ops`

### Role tags
`@visitor` `@individual` `@admin` `@org` (org tag only appears on dormant negative scenarios)

### Tier tags
`@free` `@tier1` `@tier2`

### Kind tags
`@happy` `@negative` `@boundary` `@rbac` `@rls` `@state-transition` `@idempotency` `@locale` `@audit`

### Operational tags
`@dormant` — feature whose backend exists but no v1 UI surface; scenarios prove the absence of UI exposure
`@smoke` — minimum critical-path coverage
`@webhook` — Razorpay or other external webhook handling

## Scenario ID convention

Each scenario carries an inferred stable ID in its trace block. Format: `<PREFIX>-<SECTION>-<SEQ>`. Example: `RLS-2.5-003`. Prefixes:

| Prefix | PRD Section |
|---|---|
| ROLE | §2.1 |
| TIER | §2.2 |
| MATRIX | §2.3 |
| ADMIN | §2.4 |
| RLS | §2.5 |
| AUTH-SIGNUP | §3.1 |
| AUTH-RESET | §3.2 |
| AUTH-TOTP | §3.3 |
| AUTH-SESSION | §3.4 |
| AUTH-ADMIN | §3.5 |
| ING-POLL | §4.1 |
| ING-DISC | §4.2 |
| ING-ACQ | §4.3 |
| ING-EXT | §4.4 |
| ING-VER | §4.5 |
| ING-INTERP | §4.6 |
| ING-BACK | §4.7 |
| INTERP | §5 |
| ARCH | §6 |
| NOTIF | §7 |
| CHAT | §8 |
| ACCT | §9 |
| PAY | §10 |
| I18N | §11 |
| NFR | §12 |
| DATA | §13 |

## Determinism rules

- Test data values are fixed strings declared in `_meta/TEST_DATA.md`.
- Times are absolute (`2026-05-15T09:00:00Z`), never "now" or "today" — unless the behaviour under test is itself relative.
- Money values are integer paise where shown; rupees only in display strings.
- Locales are exactly `en` `hi` `gu` `mr`.
- Document identifiers follow `<SOURCE>-<TYPE>-<YYYY>-<SEQ>` (e.g. `RBI-CIRC-2026-001`).

## What is NOT in this package

- Implementation details (component names, internal class structure, framework specifics).
- Schema migrations beyond what §13 already describes as observable user/admin behaviour.
- Performance SLAs not present in the PRD.
- Behaviour for any feature flagged "Out of scope for v1" in the PRD preamble.
