# Trace:
# PRD Section: §8.7 Context window handling
# Requirement ID: CHAT-8.7
# User Story: As a Tier 2 user, when a Chat reaches the context limit I want a clean way to continue in a fresh Chat with the same Document.
# Acceptance Criteria: When projected context size on the next user message exceeds the configured threshold, the Chat is set to closed_context_full; a system message renders "This chat has reached its context limit. Please start a new chat with the same document to continue."; an inline "Start new chat with this document" button creates a fresh Chat bound to the same Document Version — clean slate; closed Chats remain readable and exportable.

@chat @tier2 @state-transition
Feature: Chat context-window handling

  Background:
    Given a signed-in individual user "cara@example.test" on tier "tier_2"
    And a Document Version "DV-1"
    And a Chat "CHAT-001" bound to "DV-1" with status "active"
    And the configured context threshold is "the v1 threshold from §14 item 3"

  Rule: When the next user message would exceed the threshold, status transitions to closed_context_full

    @state-transition
    Scenario: Projected context exceeds the threshold and status flips
      Given the projected context size for the next user message exceeds the configured threshold
      When cara attempts to send a new message to "CHAT-001"
      Then "CHAT-001".status becomes "closed_context_full"
      And the new message is not persisted
      And no credit is debited
      And a system message is rendered: "This chat has reached its context limit. Please start a new chat with the same document to continue."

  Rule: An inline "Start new chat with this document" button is rendered on the context-full state

    Scenario: The inline new-chat affordance is rendered
      Given "CHAT-001".status is "closed_context_full"
      When cara opens "CHAT-001"
      Then an inline "Start new chat with this document" button is rendered

  Rule: Starting a new chat from the context-full affordance creates a clean-slate Chat bound to the same Document Version

    @state-transition
    Scenario: New Chat is clean slate, bound to the same Document Version
      Given "CHAT-001".status is "closed_context_full" and is bound to "DV-1"
      When cara clicks the inline "Start new chat with this document" button
      Then a new Chat is created with document_version_id="DV-1", status="active"
      And the new Chat contains zero messages
      And no carry-forward summary of "CHAT-001" is present in the new Chat

  Rule: A closed Chat remains readable and exportable

    @happy
    Scenario: Closed Chat is readable
      Given "CHAT-001".status is "closed_context_full"
      When cara opens "CHAT-001"
      Then the conversation thread renders fully

    @happy
    Scenario Outline: Closed Chat is exportable
      Given "CHAT-001".status is "closed_context_full"
      When cara exports "CHAT-001" as "<format>"
      Then a file in format "<format>" is produced

      Examples:
        | format |
        | json   |
        | md     |
        | pdf    |
