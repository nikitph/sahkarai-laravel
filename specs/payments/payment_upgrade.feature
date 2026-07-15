# Trace:
# PRD Section: §10.3 Upgrade
# Requirement ID: PAY-10.3
# User Story: As an end user, I want to upgrade my tier with the change taking effect on payment success, prorated for mid-cycle T1→T2.
# Acceptance Criteria: Free → Tier 1 or Tier 2 via Razorpay checkout, access activates on payment success; Tier 1 → Tier 2 prorated for remainder of current cycle, new monthly cycle starts from upgrade date, credits issued immediately and prorated to remaining cycle days.

@payments @state-transition
Feature: Tier upgrade flows

  Rule: Free → Tier 1 upgrade activates Tier 1 on Razorpay payment success

    @happy
    Scenario: Free → Tier 1 upgrade on payment success
      Given a signed-in individual user "bea@example.test" on tier "free"
      When bea completes Razorpay checkout for plan "tier_1" and Razorpay reports payment success
      Then bea's tier is "tier_1"
      And bea receives Tier 1 capabilities access immediately
      And Tier 1 Notification preferences default rows are created (enabled=true, daily_digest) for [RBI, IT, GST]

  Rule: Free → Tier 2 upgrade activates Tier 2 and grants credits on Razorpay payment success

    @happy
    Scenario: Free → Tier 2 upgrade on payment success
      Given a signed-in individual user "cara@example.test" on tier "free"
      When cara completes Razorpay checkout for plan "tier_2" and Razorpay reports payment success
      Then cara's tier is "tier_2"
      And a grant_cycle ledger entry of +200 is recorded for cara
      And cara's credit balance is 200

  Rule: A failed Razorpay checkout leaves the tier unchanged

    @negative
    Scenario: Failed checkout does not change tier
      Given a signed-in individual user "fred@example.test" on tier "free"
      When fred attempts Razorpay checkout for plan "tier_1" and Razorpay reports payment failure
      Then fred's tier remains "free"
      And no subscription row is created for fred

  Rule: Tier 1 → Tier 2 upgrade is prorated, starts a fresh monthly cycle, and grants prorated credits

    @happy
    Scenario: T1 → T2 prorated upgrade with prorated credits
      Given a signed-in individual user "bea@example.test" on tier "tier_1"
      And bea's current Tier 1 cycle period is 2026-05-01..2026-06-01 (30 days)
      And the current time is "2026-05-15T00:00:00Z" (15 days remaining)
      When bea completes Razorpay checkout for plan "tier_2" with prorated charge for the remaining 15 days
      And Razorpay reports payment success
      Then bea's tier is "tier_2"
      And bea's new Tier 2 cycle starts at "2026-05-15T00:00:00Z"
      And a grant_cycle ledger entry is recorded for bea with a credit grant prorated to the remaining cycle days
      # PRD says "credits issued immediately, prorated to remaining cycle days". The exact rounding rule (ceil, floor, nearest) is an OPEN QUESTION (OQ-PAY-002).

  Rule: A user cannot "upgrade" to a lower tier; that path is downgrade

    @negative
    Scenario: Tier 2 user cannot upgrade to Tier 1
      Given a signed-in individual user "cara@example.test" on tier "tier_2"
      When cara opens the upgrade surface
      Then no "upgrade to Tier 1" option is offered
