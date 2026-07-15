# Trace:
# PRD Section: §8.4 Credit exhaustion — soft stop
# Requirement ID: CHAT-8.4
# User Story: As a Tier 2 user who has used all my credits, I want to still read and export my Chats and keep using non-Chat features; only new message sending is blocked.
# Acceptance Criteria: At zero balance the user can open/read/export Chats and use Archive/Interpretations/Notifications; the user cannot send new messages; the send UI is disabled with: "You've used all your credits for this cycle. Your allowance resets on `<date>`."

@chat @tier2 @state-transition
Feature: Credit exhaustion soft-stop
  A zero-balance user retains full read access and export. Only new message sending is blocked,
  with an explicit reset-date copy.

  Background:
    Given a signed-in individual user "cara@example.test" on tier "tier_2"
    And cara's credit balance is 0
    And cara's allowance resets on "2026-06-01T00:00:00Z"
    And cara has a Chat "CHAT-001" with at least one prior message

  Rule: The user can open, read, and export any of their Chats at zero balance

    @happy
    Scenario: Open and read an existing Chat at zero balance
      When cara opens "CHAT-001"
      Then the conversation thread renders fully

    @happy
    Scenario Outline: Export an existing Chat at zero balance
      When cara exports "CHAT-001" as "<format>"
      Then a file in format "<format>" is produced

      Examples:
        | format |
        | json   |
        | md     |
        | pdf    |

  Rule: The user can still browse, search, view Interpretations, and receive Notifications at zero balance

    @happy
    Scenario: Read access and Notifications continue at zero balance
      When cara opens the Archive
      Then the Archive browse surface renders with Tier 2 access
      And Interpretation views remain accessible
      And new Notifications continue to be delivered to cara

  Rule: The user cannot send a new message at zero balance

    @negative
    Scenario: Sending a message at zero balance is blocked
      When cara attempts to send "follow up?" to "CHAT-001"
      Then the write is denied with reason "no_credits_remaining"
      And no message is persisted
      And cara's credit balance remains 0

  Rule: The send-message UI is disabled with the explicit reset-date copy

    Scenario: Composer is disabled and shows the exact §8.4 copy with the reset date
      When cara opens "CHAT-001"
      Then the message composer is disabled
      And the composer shows exactly: "You've used all your credits for this cycle. Your allowance resets on 2026-06-01."
