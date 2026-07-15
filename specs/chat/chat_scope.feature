# Trace:
# PRD Section: §8.1 Scope; §8.9 Out of scope for v1 Chat
# Requirement ID: CHAT-8.1
# User Story: As a Tier 2 user, I want a Chat that is bound to one Document Version, owned by me, and not sharable or multi-document in v1.
# Acceptance Criteria: A Chat is bound to one Document Version at creation and cannot be re-bound; a Chat belongs to one User; no multi-document, sharing, file upload, voice, or carry-forward in v1.

@chat @tier2
Feature: Chat scope and ownership

  Rule: A Chat is bound to exactly one Document Version at creation

    @happy
    Scenario: A new Chat carries the bound Document Version id
      Given a Tier 2 user "cara@example.test"
      And a Document Version "DV-1"
      When cara starts a Chat from "DV-1"
      Then the new Chat has document_version_id "DV-1"

  Rule: A Chat cannot be re-bound to a different Document Version

    @negative
    Scenario: Re-bind attempt is rejected
      Given a Chat "CHAT-001" with document_version_id "DV-1"
      When cara attempts to re-bind "CHAT-001" to "DV-2"
      Then the action is denied with reason "chat_rebind_not_supported"
      And "CHAT-001".document_version_id remains "DV-1"

  Rule: A Chat belongs to exactly one user; no sharing or collaboration in v1

    @negative
    Scenario Outline: A share/invite route does not render
      Given a Tier 2 user "cara@example.test" with a Chat "CHAT-001"
      When cara navigates to "<route>"
      Then the route is not registered in the v1 UI
      And cara is shown the 404 surface

      Examples:
        | route                          |
        | /chats/CHAT-001/share          |
        | /chats/CHAT-001/invite         |
        | /chats/CHAT-001/collaborators  |

  Rule: Multi-document chat is not available in v1

    @negative
    Scenario: A user cannot add a second Document Version to an existing Chat
      Given a Chat "CHAT-001" bound to "DV-1"
      When cara attempts to add "DV-2" to "CHAT-001"
      Then the action is denied with reason "multi_document_chat_not_supported"

  Rule: File or image upload into chat is not available in v1

    @negative
    Scenario: No file upload affordance is rendered in the chat composer
      Given cara has Chat "CHAT-001"
      When cara opens "CHAT-001"
      Then no file or image upload affordance is rendered in the message composer

  Rule: Voice input and output are not available in v1

    @negative
    Scenario: No voice input or output affordance is rendered
      Given cara has Chat "CHAT-001"
      When cara opens "CHAT-001"
      Then no microphone or audio-playback affordance is rendered

  Rule: Conversation does not auto-carry-forward across context-full splits

    @negative
    Scenario: A fresh Chat created after context-full does not include prior messages
      Given Chat "CHAT-001" reached "closed_context_full"
      When cara starts a new Chat from "CHAT-001"'s "Start new chat with this document" affordance
      Then the new Chat contains zero prior messages
      And no system-injected summary of "CHAT-001" is present
