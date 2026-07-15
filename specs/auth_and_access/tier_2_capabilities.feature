# Trace:
# PRD Section: §2.2 Tiers — Tier 2
# Requirement ID: TIER-2.2-T2
# User Story: As a Tier 2 user, I want everything Tier 1 has plus Chat with monthly credits.
# Acceptance Criteria: Tier 2 adds Chat creation; 1 credit per message; resume + export Chat history; cannot downgrade mid-cycle.

@auth @tier2 @individual
Feature: Tier 2 capabilities
  Tier 2 adds Chat on top of Tier 1. Chat consumes monthly credits and produces exportable
  conversation transcripts. Downgrade is queued to the next anniversary, not immediate.

  Background:
    Given a signed-in individual user "cara@example.test" on tier "tier_2" with 200 credits granted this cycle

  Rule: Tier 2 can start a Chat bound to any Document Version

    @happy @chat
    Scenario: Tier 2 user sees and uses "Start chat with this document"
      Given a Document Version "RBI-CIRC-2026-001" with a published Interpretation
      When cara opens "RBI-CIRC-2026-001"
      Then a "Start chat with this document" button is rendered
      When cara clicks "Start chat with this document"
      Then a Chat is created bound to "RBI-CIRC-2026-001"

  Rule: Each user-sent message debits 1 credit

    @happy @chat
    Scenario: A user-sent message debits exactly 1 credit
      Given cara has a Chat "CHAT-001"
      And cara's credit balance is 200
      When cara sends "What is CRR?" to "CHAT-001"
      Then the message is persisted
      And cara's credit balance is 199
      And a debit_message ledger entry referencing the new chat_message_id is recorded

  Rule: Tier 2 can view, resume, and export their own Chats

    @happy @chat
    Scenario: Tier 2 user resumes a still-active Chat
      Given cara has a Chat "CHAT-001" with status "active"
      When cara opens "CHAT-001"
      Then the conversation thread renders
      And the message input is enabled

    @happy @chat
    Scenario Outline: Tier 2 user exports a Chat
      Given cara has a Chat "CHAT-001" with at least one message
      When cara exports "CHAT-001" as "<format>"
      Then a file in format "<format>" is produced

      Examples:
        | format |
        | json   |
        | md     |
        | pdf    |

  Rule: Tier 2 cannot downgrade mid-cycle; downgrade is queued

    @state-transition @payments
    Scenario: Downgrade from Tier 2 is queued to next anniversary
      Given cara's billing anniversary is "2026-06-01T00:00:00Z"
      And the current time is "2026-05-15T09:00:00Z"
      When cara requests a downgrade to "tier_1"
      Then the downgrade is queued to "2026-06-01T00:00:00Z"
      And cara's effective tier remains "tier_2" until that date
      And cara's credit allowance is preserved until that date
