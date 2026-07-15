# Trace:
# PRD Section: §10.1 Provider; §10.2 Plans
# Requirement ID: PAY-10.2
# User Story: As the platform, I want exactly the v1 plans wired to Razorpay subscriptions, in INR, with the published prices.
# Acceptance Criteria: Razorpay Subscriptions API; Free has no payment; Tier 1 is ₹499/month (one plan, no annual); Tier 2 is ₹1,499/month including 200 credits/month (one plan); INR only.

@payments
Feature: Plan definitions and Razorpay wiring

  Rule: Free has no payment record

    Scenario: A Free user has no Razorpay subscription
      Given a signed-in individual user "fred@example.test" on tier "free"
      Then no Razorpay subscription record exists for fred

  Rule: Tier 1 is ₹499/month, one plan, no annual variant

    Scenario: A new Tier 1 subscription is created at ₹499/month
      Given a signed-in individual user "bea@example.test" on tier "free"
      When bea completes Razorpay checkout for plan "tier_1"
      Then a Razorpay subscription is created on the "tier_1_monthly_inr" plan
      And the plan amount is ₹499 in INR
      And the plan interval is monthly

    @negative
    Scenario: No annual Tier 1 plan is offered in v1
      Given a signed-in individual user "bea@example.test" on tier "free"
      When bea opens the upgrade surface
      Then only the "tier_1_monthly_inr" Tier 1 plan is offered
      And no Tier 1 annual plan is listed

  Rule: Tier 2 is ₹1,499/month including 200 credits/month, one plan

    Scenario: A new Tier 2 subscription is created at ₹1,499/month with 200 credits
      Given a signed-in individual user "cara@example.test" on tier "free"
      When cara completes Razorpay checkout for plan "tier_2"
      Then a Razorpay subscription is created on the "tier_2_monthly_inr" plan
      And the plan amount is ₹1,499 in INR
      And the plan interval is monthly
      And cara is granted 200 credits for the new cycle via a grant_cycle ledger entry

  Rule: All payments are in INR only

    @negative
    Scenario Outline: Non-INR currency is not offered
      Given a signed-in individual user "bea@example.test" on tier "free"
      When bea opens the upgrade surface
      Then no plan listed is denominated in "<currency>"

      Examples:
        | currency |
        | USD      |
        | EUR      |
        | GBP      |

  Rule: Final pricing is a v1 placeholder pending settlement (§14 item 1)

    Scenario: Pricing values are read from configuration, not hard-coded in the UI
      Given the platform configuration defines plan amounts
      Then the upgrade surface reads plan amounts from configuration
      # See OQ-PAY-001: confirm/lock pricing before launch.
