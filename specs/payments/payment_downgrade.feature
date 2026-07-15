# Trace:
# PRD Section: §10.4 Downgrade
# Requirement ID: PAY-10.4
# User Story: As a paying user, when I downgrade I keep my current tier and credits until the next anniversary; the change applies then.
# Acceptance Criteria: Tier 2 → Tier 1 or Free: queued, applies at next billing anniversary, user retains current tier and credits until then; Tier 1 → Free: same — queued.

@payments @state-transition
Feature: Tier downgrade flows queued to next anniversary

  Background:
    Given the current time is "2026-05-15T10:00:00Z"

  Rule: Tier 2 → Tier 1 downgrade is queued to the next anniversary

    @happy
    Scenario: T2 → T1 downgrade queued; user retains Tier 2 and credits until anniversary
      Given a signed-in individual user "cara@example.test" on tier "tier_2"
      And cara's next billing anniversary is "2026-06-01T00:00:00Z"
      And cara's credit balance is 150
      When cara requests a downgrade to "tier_1"
      Then a pending_change is recorded on cara's subscription targeting "tier_1" effective "2026-06-01T00:00:00Z"
      And cara's effective tier remains "tier_2" until "2026-06-01T00:00:00Z"
      And cara's credit balance remains 150 and continues to be debited normally

    @state-transition
    Scenario: The queued downgrade applies at the anniversary
      Given cara has a pending_change of "tier_1" effective "2026-06-01T00:00:00Z"
      When the time reaches "2026-06-01T00:00:00Z" and the renewal job runs
      Then cara's tier becomes "tier_1"
      And the credit ledger no longer issues grant_cycle entries
      And cara's credit balance is set to 0 from the perspective of Chat write access

  Rule: Tier 2 → Free downgrade is queued to the next anniversary

    @happy
    Scenario: T2 → Free downgrade queued; subscription is cancelled at anniversary
      Given a signed-in individual user "cara@example.test" on tier "tier_2"
      And cara's next billing anniversary is "2026-06-01T00:00:00Z"
      When cara requests a downgrade to "free"
      Then a pending_change is recorded on cara's subscription targeting "free" effective "2026-06-01T00:00:00Z"
      And cara retains Tier 2 capabilities until "2026-06-01T00:00:00Z"

    @state-transition
    Scenario: The queued T2→Free downgrade cancels Razorpay subscription at anniversary
      Given cara has a pending_change of "free" effective "2026-06-01T00:00:00Z"
      When the time reaches "2026-06-01T00:00:00Z" and the renewal job runs
      Then cara's tier becomes "free"
      And cara's Razorpay subscription is cancelled

  Rule: Tier 1 → Free downgrade is queued to the next anniversary

    @happy
    Scenario: T1 → Free downgrade queued
      Given a signed-in individual user "bea@example.test" on tier "tier_1"
      And bea's next billing anniversary is "2026-06-15T00:00:00Z"
      When bea requests a downgrade to "free"
      Then a pending_change is recorded on bea's subscription targeting "free" effective "2026-06-15T00:00:00Z"
      And bea retains Tier 1 capabilities until "2026-06-15T00:00:00Z"

  Rule: A user can cancel a queued downgrade before it takes effect

    @state-transition
    Scenario: Cancelling a queued downgrade restores the prior state
      Given cara has a pending_change of "tier_1" effective "2026-06-01T00:00:00Z"
      And the current time is "2026-05-20T10:00:00Z"
      When cara cancels the queued downgrade
      Then cara's subscription has no pending_change
      And cara's tier remains "tier_2"
      # The PRD does not explicitly state cancellation of a queued downgrade. Captured as OQ-PAY-003.
