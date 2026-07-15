# Trace:
# PRD Section: §8.6 Chat surface
# Requirement ID: CHAT-8.6
# User Story: As a Tier 2 user, I want a chat layout with my chat history sidebar, the conversation pane, and a pinned reference to the bound Document.
# Acceptance Criteria: Sidebar lists all of the user's Chats, latest activity first; each row shows bound Document title and last-message snippet; closed Chats badged "Context full — start new chat."; main pane shows the conversation thread; composer at bottom, disabled when balance is zero or status is closed; a pinned reference panel shows the bound Document title, source, and a link to its detail page.

@chat @tier2
Feature: Chat surface layout

  Background:
    Given a signed-in individual user "cara@example.test" on tier "tier_2"
    And cara has Chats:
      | chat_id   | bound_document_title       | status                | last_message_snippet         | last_activity            |
      | CHAT-A    | Capital Adequacy Update    | active                | Thanks, that helps           | 2026-05-15T10:00:00Z     |
      | CHAT-B    | LCR Revision               | closed_context_full   | Let me check the deadline... | 2026-05-14T18:30:00Z     |
      | CHAT-C    | TDS Notification 2026-007  | closed_by_user        | (no further messages)        | 2026-05-13T11:15:00Z     |

  Rule: The sidebar lists all of the user's Chats latest-activity-first with title and snippet

    @happy
    Scenario: Sidebar renders chats in last_activity descending order
      When cara opens the Chat surface
      Then the sidebar lists chats in order ["CHAT-A", "CHAT-B", "CHAT-C"]
      And each row shows the bound Document title and the last-message snippet

  Rule: Closed-context-full chats are badged with the exact §8.6 copy

    Scenario: "CHAT-B" carries the context-full badge
      When cara opens the Chat surface
      Then the sidebar row for "CHAT-B" shows the badge "Context full — start new chat."

  Rule: The main pane renders the selected Chat's thread

    @happy
    Scenario: Selecting a Chat renders its thread
      Given cara opens the Chat surface
      When cara selects "CHAT-A"
      Then the main pane renders the conversation thread for "CHAT-A"

  Rule: The composer is disabled when balance is zero or status is closed

    @negative
    Scenario: Composer is disabled when status is closed_context_full
      When cara selects "CHAT-B"
      Then the composer is disabled

    @negative
    Scenario: Composer is disabled when status is closed_by_user
      When cara selects "CHAT-C"
      Then the composer is disabled

    @negative
    Scenario: Composer is disabled when balance is zero on an active Chat
      Given cara's credit balance is 0
      When cara selects "CHAT-A"
      Then the composer is disabled

    @happy
    Scenario: Composer is enabled when balance > 0 on an active Chat
      Given cara's credit balance is 50
      When cara selects "CHAT-A"
      Then the composer is enabled

  Rule: The pinned reference panel shows the bound Document title, source, and a link to its detail page

    @happy
    Scenario: Pinned reference panel for the selected Chat
      Given "CHAT-A" is bound to "DV-1" with title "Capital Adequacy Update" and source "RBI"
      When cara selects "CHAT-A"
      Then the pinned reference panel shows title "Capital Adequacy Update"
      And source "RBI"
      And a link to "DV-1"'s detail page
