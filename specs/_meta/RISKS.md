# Risk Analysis

Risks that emerge from ambiguity, missing validations, unclear ownership, hidden workflow
branches, state inconsistencies, concurrency, security, and undefined edge cases. Each entry
links back to the PRD section and to the spec file(s) that document the scenarios.

## Ambiguous business rules

- **R-AMB-01 — Combined trigger duplication.** §7.2 has two notification triggers; both can apply to the same user/Document Version (a revision of a Document the user viewed, ingested after their subscription started). Without explicit deduplication, users may receive two notifications. Tracked in `notifications/notification_triggers.feature` and OQ-NOTIF-002.
- **R-AMB-02 — "Source disabled" scope.** §7.3 says no notifications for disabled Sources, but §7.4 says in-app is "always real-time". Whether disabling a Source kills in-app delivery for that Source is undefined. See OQ-NOTIF-003.
- **R-AMB-03 — Prorated credits rounding.** §10.3 defines prorating without a rounding rule. Different rounding choices produce different balances. See OQ-PAY-002.
- **R-AMB-04 — Soft-deleted account sign-in.** §3.4 vs §9.2 leaves the restore-via-sign-in mechanism implicit. The current spec assumes a separate restore surface (ASSUMPTION-A011 / OQ-ACCT-001).
- **R-AMB-05 — Free user receiving a revision notification.** The §2.3 matrix denies "Receive Notifications" for Free; §7.2 trigger phrasing doesn't gate on tier explicitly. Matrix wins (per §2.3 canonical rule).

## Missing validations

- **R-VAL-01 — No rate limit on sign-up, sign-in, or password reset is specified.** Lack of rate limiting exposes account-enumeration and credential-stuffing risk. See COVERAGE_GAPS.
- **R-VAL-02 — No max file size for Document originals.** Acquisition may hang or OOM on unusually large PDFs. See COVERAGE_GAPS.
- **R-VAL-03 — Issue report description length not bounded.** §5.5 captures free-text without a max length. Risk: abuse vector or DB bloat.
- **R-VAL-04 — Email cadence value not constrained on save.** §7.4 enumerates three values; spec asserts enum validation. If the implementation does not enforce the enum at write time, an invalid cadence could break the digest scheduler.

## Unclear ownership

- **R-OWN-01 — Adjustment ledger entries.** §12.3 lists `adjustment` as a ledger reason but does not say who writes them. We've assumed `saas_admin` via authenticated route or service-role (ASSUMPTION-A018).
- **R-OWN-02 — Org tables in production.** §2.1 says these tables are empty in v1 production. Who is responsible for *keeping them empty*? Without a guard, an accidental insert via service role could leak Org-mode UI affordances.
- **R-OWN-03 — Manual refund recording.** §10.8 says "manual via support" but does not name the surface where support records the corresponding credit-ledger adjustment.

## Hidden workflow branches

- **R-WF-01 — Revision arrives mid-generation.** A revision Document Version can arrive while the prior version's Interpretation is still in retry. Precedence is undefined. See OQ-ING-002.
- **R-WF-02 — Partial per-Locale generation.** If one Locale block fails while three succeed, do we publish the partial Interpretation (Locale fallback kicks in for the missing block) or retry holistically? See OQ-INTERP-002.
- **R-WF-03 — Locale change mid-flight notification.** If a user changes their account Locale between when a notification is queued and when it's delivered, which Locale wins? See COVERAGE_GAPS.
- **R-WF-04 — Razorpay webhook arrives during account soft-delete window.** A renewal-charged webhook for a soft-deleted user must be handled idempotently and must not re-activate the account. PRD does not say this; spec doesn't currently assert it.

## State inconsistencies

- **R-STATE-01 — Subscription status drift.** §10.6 promises a daily reconciliation job to detect drift. Until the next reconciliation run, local state and Razorpay state can diverge. Window length is ~24h; users may see stale tier during that window.
- **R-STATE-02 — Chat status vs. credit balance race.** A Chat can be `active` with the owner's balance at zero. The composer must be disabled by *either* condition. `chat_surface.feature` covers both; the implementation must check both at send time.
- **R-STATE-03 — Closed-chat re-opening attempt.** A `closed_context_full` chat cannot accept new messages. If a client retries (e.g. queued from offline), the retry must be rejected without leaving partial state.

## Concurrency risks

- **R-CON-01 — Simultaneous send-message attempts.** Two concurrent send requests from the same user could each pass the "balance > 0" check and both debit. Credit-debit must be atomic (e.g. `UPDATE ... WHERE balance >= 1` with row lock).
- **R-CON-02 — Concurrent extraction retries.** Two retry attempts on the same Document Version could both produce extracted artefacts. Extraction should use an idempotency key per Document Version.
- **R-CON-03 — Concurrent Interpretation generation.** Same as above for the Interpretation pipeline. Spec asserts idempotency at the row level; implementation needs a write-time guard.
- **R-CON-04 — Notification dispatcher fan-out.** For a single Document Version publish, many users may match the trigger. The dispatcher must avoid double-sending to a user if it's re-invoked.
- **R-CON-05 — Razorpay webhook redelivery during processing.** A webhook may be redelivered while the first delivery is still in flight. Idempotency must be by event_id; in-flight locking is needed.

## Security risks

- **R-SEC-01 — Email-as-identifier without verification.** §3.1 accepts unverified emails. A malicious actor could register with someone else's email; the legitimate owner cannot self-recover via reset (no account is bound to them). Acceptable per PRD but documented.
- **R-SEC-02 — saas_admin can read aggregated user metadata.** Per §2.5; spec covers this. The PII boundary between "aggregated metadata" and "private data" is not formally defined.
- **R-SEC-03 — Top-up webhook receives money-equivalent payloads.** Even though the UI is dormant, the endpoint is live and signed. Signature verification + idempotency are essential to prevent credit fabrication. Spec covers both.
- **R-SEC-04 — Chat content private even from staff.** §2.5 explicitly forbids staff from reading chat bodies. Any future analytics on chat content must be reconciled against this constraint.
- **R-SEC-05 — RLS bypass via service role.** The service role exists for ingestion and webhooks. Any code path that uses service role must be audited so user-supplied IDs cannot escape RLS via that path.
- **R-SEC-06 — Locale change as enumeration surface.** Account-settings Locale change is unauthenticated-equivalent (same surface). Not a security risk per se; flagged as low-risk.
- **R-SEC-07 — Soft-deleted account auto-revival.** If sign-in restores via standard surface (OQ-ACCT-001), credential-stuffing of a soft-deleted account could revive it silently. Tracked.

## Undefined edge cases

- **R-EDGE-01 — A user signs up, immediately upgrades to Tier 2, signs out, signs back in, sends a chat: are credits granted before send?** Cycle grant should be triggered by subscription activation, not first sign-in. Spec covers this in `payment_upgrade.feature`.
- **R-EDGE-02 — A user views DV-1 as Free, upgrades to Tier 1 between DV-1 and DV-2 publication. Revision trigger applies?** Yes, per spec (`notification_triggers.feature`), because "previously viewed" is a viewing fact independent of tier; subscription-start gate is the post-start trigger (Rule 1), and Rule 2 (revision) does not gate on tier directly. Confirm with Product (OQ-NOTIF-004).
- **R-EDGE-03 — A `closed_context_full` Chat's `last_activity` for sidebar ordering.** Does it use closed_at, last message timestamp, or chat created_at? Spec uses "last activity" loosely; needs locking.
- **R-EDGE-04 — A Chat created in Locale "en" while the user is later set to "hi".** Does the chat's system prompt language update or remain pinned? §8.2 says creation-time Locale drives the system-prompt language; we treat it as pinned.
- **R-EDGE-05 — A user deletes their account during a queued downgrade.** Soft-delete cancels the Razorpay subscription immediately (§9.2). The queued downgrade is moot. Spec implicitly handles via account_deletion.feature but does not call this out explicitly.
- **R-EDGE-06 — A user filing an Issue Report and then hard-deleting.** §9.2 says the issue report is retained with anonymised user_id; this is covered. But does the report retain the chat/context the user attached? PRD doesn't say.
