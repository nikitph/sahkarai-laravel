# Trace:
# PRD Section: §8.5 Top-up CTA (wired but disabled); §10.7 Top-up infrastructure
# Requirement ID: CHAT-8.5, PAY-10.7
# User Story: As the platform, I want top-up purchase backend wired and ready but no UI exposure in v1.
# Acceptance Criteria: `topup_url` config exists; when null (v1 default), no top-up CTA renders — only the §8.4 reset-date copy; when set, the CTA renders alongside the reset-date copy: "Need more credits now? Buy a top-up." linking to `topup_url`; backend webhook + credit ledger hooks exist but are unreachable from the v1 UI.

@chat @tier2 @dormant
Feature: Top-up CTA is wired but renders only when topup_url is set
  v1 default is topup_url=null; in that state, no top-up CTA renders. When topup_url is set,
  the CTA renders alongside the §8.4 reset-date copy and links to topup_url.

  Background:
    Given a signed-in individual user "cara@example.test" on tier "tier_2"
    And cara's credit balance is 0
    And cara's allowance resets on "2026-06-01T00:00:00Z"
    And cara has a Chat "CHAT-001"

  Rule: When topup_url is null (v1 default), no top-up CTA renders

    @negative
    Scenario: V1 default — no top-up CTA visible
      Given the platform configuration has topup_url=null
      When cara opens "CHAT-001"
      Then the composer shows exactly: "You've used all your credits for this cycle. Your allowance resets on 2026-06-01."
      And no element labelled with any of ["Need more credits now?", "Buy a top-up", "Top-up"] is rendered

  Rule: When topup_url is set, the CTA renders alongside the reset-date copy and links to topup_url

    @happy
    Scenario: Configured topup_url renders the CTA
      Given the platform configuration has topup_url="https://billing.sahkarai.test/topup"
      When cara opens "CHAT-001"
      Then the composer shows exactly: "You've used all your credits for this cycle. Your allowance resets on 2026-06-01."
      And a CTA "Need more credits now? Buy a top-up." is rendered linking to "https://billing.sahkarai.test/topup"

  Rule: The backend topup credit-ledger entry kind exists and is exercised by the webhook handler

    @audit
    Scenario: A topup ledger-entry kind is defined and writeable by the webhook handler
      Then the credit-ledger schema accepts a reason value "topup"
      And only the service-role webhook handler may write a "topup" entry

  Rule: No UI path exposes a top-up purchase action in v1

    @negative
    Scenario Outline: A would-be top-up purchase route does not render in v1
      Given the platform configuration has topup_url=null
      When cara navigates to "<route>"
      Then the route is not registered in the v1 UI
      And cara is shown the 404 surface

      Examples:
        | route                |
        | /billing/topup       |
        | /credits/purchase    |
        | /account/topup       |
