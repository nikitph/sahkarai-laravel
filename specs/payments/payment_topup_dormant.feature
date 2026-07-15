# Trace:
# PRD Section: §10.7 Top-up infrastructure (wired but unused)
# Requirement ID: PAY-10.7
# User Story: As the platform, I want the top-up purchase webhook handler tested and live, with no UI initiating a purchase in v1.
# Acceptance Criteria: Webhook handler for one-off payments exists and is exercised in tests; it can credit a user's credit ledger with a `topup` ledger entry on receipt of a verified purchase event; no UI initiates such a purchase in v1.

@payments @dormant @webhook
Feature: Top-up purchase webhook is wired but unreachable from the v1 UI

  Rule: A verified top-up purchase webhook credits the user via a `topup` ledger entry

    @audit
    Scenario: A verified top-up webhook applies a topup ledger entry
      Given a signed-in individual user "cara@example.test" on tier "tier_2" with credit balance 0
      And a valid signed Razorpay one-off payment webhook crediting cara with 100 credits
      When the top-up webhook endpoint receives the payload
      Then a credit_ledger entry is recorded for cara with delta=+100 and reason="topup"
      And cara's credit balance is 100

  Rule: Top-up webhooks are signature-verified and idempotent

    @negative
    Scenario: An unsigned top-up webhook is rejected
      Given a top-up webhook payload with no signature header
      When the top-up webhook endpoint receives the payload
      Then the request is rejected with HTTP 400 or 401
      And no ledger entry is written

    @idempotency
    Scenario: A duplicate top-up webhook does not double-credit
      Given the top-up webhook event_id "tup_001" has already been processed crediting +100
      When the top-up webhook endpoint receives event_id "tup_001" a second time with a valid signature
      Then no further ledger entry is written
      And cara's credit balance is unchanged

  Rule: No v1 UI initiates a top-up purchase

    @negative
    Scenario Outline: A would-be top-up purchase route does not render
      Given a signed-in individual user "cara@example.test" on tier "tier_2"
      When cara navigates to "<route>"
      Then the route is not registered in the v1 UI
      And cara is shown the 404 surface

      Examples:
        | route             |
        | /billing/topup    |
        | /credits/purchase |
        | /account/topup    |
