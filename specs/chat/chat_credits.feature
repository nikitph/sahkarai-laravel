# Trace:
# PRD Section: §8.3 Credits
# Requirement ID: CHAT-8.3
# User Story: As a Tier 2 user, I want a monthly credit allowance, one credit per message I send, no rollover, and to always see my balance.
# Acceptance Criteria: 200 credits / month placeholder; 1 credit per user-sent message debited at send time; credits reset on billing cycle anniversary; no rollover; balance visible on Chat and account settings surfaces.

@chat @tier2 @payments
Feature: Tier 2 monthly credit allowance and per-message debit
  Each Tier 2 user has a monthly credit allowance (v1 placeholder: 200). Each user-sent
  message debits exactly one credit at send time. Credits reset on the billing cycle
  anniversary and do not roll over.

  Background:
    Given a signed-in individual user "cara@example.test" on tier "tier_2"
    And a Chat "CHAT-001" bound to "DV-1"

  Rule: 200 credits are granted at the start of each monthly billing cycle

    @happy @state-transition
    Scenario: A grant_cycle ledger entry of +200 is recorded at cycle start
      Given cara's billing cycle begins at "2026-05-01T00:00:00Z"
      When the cycle-grant job runs at "2026-05-01T00:00:00Z"
      Then a grant_cycle ledger entry is recorded with delta=+200 for cara
      And cara's credit balance becomes 200
      # Final allowance number is OPEN per §14 item 4. Tests must read the configured value.

  Rule: Each user-sent message debits exactly 1 credit at send time

    @happy
    Scenario: A user-sent message debits 1 credit
      Given cara's credit balance is 200
      When cara sends "What is CRR?" to "CHAT-001"
      Then the message is persisted with role "user"
      And cara's credit balance is 199
      And a debit_message ledger entry of delta=-1 is recorded referencing the new chat_message_id

  Rule: Assistant messages do not debit credits

    Scenario: An assistant message does not debit a credit
      Given cara's credit balance is 199
      When the assistant responds with a message in "CHAT-001"
      Then the assistant message is persisted with role "assistant"
      And cara's credit balance remains 199
      And no debit_message ledger entry is created for the assistant message

  Rule: Credits reset on the billing cycle anniversary with no rollover

    @state-transition @boundary
    Scenario: Unused credits do not carry over
      Given cara's current cycle ends at "2026-06-01T00:00:00Z" with balance 175
      When the cycle-grant job runs at "2026-06-01T00:00:00Z"
      Then a grant_cycle ledger entry resets the balance to 200
      And the previous 175 credits do not carry over

  Rule: Current balance is visible on the Chat surface and in account settings

    @happy
    Scenario: Balance is visible on the Chat surface
      Given cara's credit balance is 199
      When cara opens "CHAT-001"
      Then the Chat surface displays the credit balance "199"

    @happy
    Scenario: Balance is visible in account settings
      Given cara's credit balance is 199
      When cara opens the account settings surface
      Then the account settings surface displays the credit balance "199"
