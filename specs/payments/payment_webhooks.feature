# Trace:
# PRD Section: §10.6 Webhooks
# Requirement ID: PAY-10.6
# User Story: As the platform, I want Razorpay webhooks as the source of truth for subscription state, verified, idempotent, with a daily reconciliation safety net.
# Acceptance Criteria: All subscription state changes driven by Razorpay webhooks, verified by signature, idempotent handlers; daily reconciliation job detects drift.

@payments @webhook
Feature: Razorpay webhook handling

  Rule: Webhooks are verified by signature

    @negative
    Scenario: An unsigned webhook is rejected
      Given a Razorpay webhook payload for "subscription.activated" with no signature header
      When the webhook endpoint receives the payload
      Then the request is rejected with HTTP 400 or 401
      And no subscription state changes occur

    @negative
    Scenario: A webhook with a bad signature is rejected
      Given a Razorpay webhook payload for "subscription.activated" with an invalid signature
      When the webhook endpoint receives the payload
      Then the request is rejected with HTTP 400 or 401
      And no subscription state changes occur

  Rule: A verified webhook is processed and applied to local state

    @happy
    Scenario: A verified subscription.activated webhook updates local subscription state
      Given a Razorpay webhook payload for "subscription.activated" for "rzp_sub_abc" with a valid signature
      When the webhook endpoint receives the payload
      Then the local subscription for "rzp_sub_abc" is set to status "active"

  Rule: Webhook handlers are idempotent

    @idempotency
    Scenario: A duplicate webhook delivery does not double-apply
      Given the webhook for event_id "evt_001" has already been processed
      When the webhook endpoint receives event_id "evt_001" a second time with a valid signature
      Then no further subscription state change occurs
      And the response is success

  Rule: A daily reconciliation job detects drift between Razorpay state and local DB

    @audit
    Scenario: Reconciliation flags a divergence
      Given Razorpay's authoritative state for "rzp_sub_abc" is "active" but local state shows "cancelled"
      When the daily reconciliation job runs
      Then a drift record is written referencing "rzp_sub_abc" with razorpay_state="active" and local_state="cancelled"
      And ops are alerted on the ops dashboard

  Rule: Webhook handler covers the subscription lifecycle events used by v1

    @happy
    Scenario Outline: Each subscription lifecycle event applies its expected local transition
      Given a valid signed Razorpay webhook for event "<event>" on subscription "rzp_sub_abc"
      When the webhook endpoint receives the payload
      Then the local subscription transitions as described by "<expected>"

      Examples:
        | event                  | expected                                      |
        | subscription.activated | status becomes "active"                        |
        | subscription.charged   | next billing dates advance                     |
        | subscription.halted    | status becomes "halted"                        |
        | subscription.cancelled | status becomes "cancelled"                     |
        | subscription.completed | status becomes "completed"                     |
      # Exact Razorpay event names per provider docs; if any names differ, surface in OQ-PAY-004.
