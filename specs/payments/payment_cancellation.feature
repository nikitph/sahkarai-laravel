# Trace:
# PRD Section: §10.5 Cancellation and failed payments (cancellation half)
# Requirement ID: PAY-10.5-CANCEL
# User Story: As a paying user, when I cancel my subscription I want it treated like a downgrade to Free.
# Acceptance Criteria: User-initiated cancellation is the same as downgrade to Free.

@payments @state-transition
Feature: User-initiated subscription cancellation

  Rule: Cancellation from Tier 1 is the same as downgrade to Free

    @happy
    Scenario: Tier 1 user cancels; pending_change "free" queued to anniversary
      Given a signed-in individual user "bea@example.test" on tier "tier_1"
      And bea's next billing anniversary is "2026-06-15T00:00:00Z"
      When bea cancels her subscription
      Then a pending_change is recorded targeting "free" effective "2026-06-15T00:00:00Z"
      And bea retains Tier 1 capabilities until "2026-06-15T00:00:00Z"

  Rule: Cancellation from Tier 2 is the same as downgrade to Free

    @happy
    Scenario: Tier 2 user cancels; pending_change "free" queued to anniversary
      Given a signed-in individual user "cara@example.test" on tier "tier_2"
      And cara's next billing anniversary is "2026-06-01T00:00:00Z"
      When cara cancels her subscription
      Then a pending_change is recorded targeting "free" effective "2026-06-01T00:00:00Z"
      And cara retains Tier 2 capabilities and credits until "2026-06-01T00:00:00Z"

  Rule: Free users have no subscription to cancel

    @negative @free
    Scenario: A Free user does not see a cancel affordance
      Given a signed-in individual user "fred@example.test" on tier "free"
      When fred opens account settings
      Then no "Cancel subscription" affordance is rendered
