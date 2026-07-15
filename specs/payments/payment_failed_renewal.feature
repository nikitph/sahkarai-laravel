# Trace:
# PRD Section: §10.5 Cancellation and failed payments (failed renewal half)
# Requirement ID: PAY-10.5-FAILED
# User Story: As the platform, I want a clear path when a Razorpay renewal fails: retries, user notifications, and eventual downgrade to Free at end of paid period.
# Acceptance Criteria: Failed renewal: Razorpay retry policy applies; after all retries fail, account downgrades to Free at end of current paid period; user notified at each retry and at downgrade (email + in-app).

@payments @state-transition
Feature: Failed Razorpay renewal handling

  Background:
    Given a signed-in individual user "cara@example.test" on tier "tier_2"
    And cara's current paid period ends at "2026-06-01T00:00:00Z"

  Rule: A renewal failure triggers a retry per the Razorpay retry policy

    @state-transition
    Scenario: First renewal failure is retried by Razorpay
      When Razorpay reports renewal failure 1 for cara at "2026-06-01T00:05:00Z"
      Then a retry attempt is scheduled per the Razorpay retry policy
      And cara's tier remains "tier_2"

  Rule: The user is notified on each renewal failure (email + in-app)

    @notifications @audit
    Scenario Outline: Each failed renewal sends an email and in-app notification
      When Razorpay reports renewal failure <n> for cara
      Then an in-app Notification is created for cara about the failed renewal
      And an email Notification dispatch is recorded for cara

      Examples:
        | n |
        | 1 |
        | 2 |
        | 3 |

  Rule: When all retries fail, the account is downgraded to Free at end of current paid period

    @state-transition
    Scenario: After all retries fail, account downgrades to Free at period end
      Given Razorpay has reported every retry attempt as failed
      When the current paid period ends at "2026-06-01T00:00:00Z"
      Then cara's tier becomes "free"
      And cara's Razorpay subscription is in a terminal failed/cancelled state

  Rule: The user is notified at downgrade (email + in-app)

    @notifications @audit
    Scenario: A downgrade Notification is dispatched at the time of downgrade
      Given cara's tier transitions to "free" at "2026-06-01T00:00:00Z" due to failed retries
      Then an in-app Notification is created for cara indicating the downgrade
      And an email Notification dispatch is recorded for cara
