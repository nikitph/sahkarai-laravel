# Trace:
# PRD Section: §8.8 Exports
# Requirement ID: CHAT-8.8
# User Story: As a Tier 2 user, I want to export any of my Chats as JSON, Markdown, or PDF, with the JSON including chat metadata, the bound Document reference, full history, and any in-conversation `insights` blocks.
# Acceptance Criteria: A Chat can be exported at any time by its owning Tier 2 User, regardless of status; formats: json, md, pdf chosen at export time; JSON contains chat metadata, bound Document Version reference, full message history with timestamps and roles, and an `insights` block.

@chat @tier2 @audit
Feature: Chat exports

  Background:
    Given a signed-in individual user "cara@example.test" on tier "tier_2"
    And a Chat "CHAT-001" bound to Document Version "DV-1" with three messages

  Rule: A Chat can be exported regardless of status by its owning user

    @happy
    Scenario Outline: Export succeeds for any chat status
      Given "CHAT-001".status is "<status>"
      When cara exports "CHAT-001" as "json"
      Then a JSON file is produced

      Examples:
        | status              |
        | active              |
        | closed_context_full |
        | closed_by_user      |

  Rule: Exports are restricted to the owning user

    @negative @rbac
    Scenario: Bob cannot export Alice's chat
      Given a Tier 2 user "bob@example.test"
      And cara owns "CHAT-001"
      When bob attempts to export "CHAT-001"
      Then the action is denied with reason "forbidden"

  Rule: The export format must be one of json, md, pdf

    @happy
    Scenario Outline: Each supported format succeeds
      When cara exports "CHAT-001" as "<format>"
      Then a file in format "<format>" is produced

      Examples:
        | format |
        | json   |
        | md     |
        | pdf    |

    @negative @boundary
    Scenario: Unknown export format is rejected
      When cara attempts to export "CHAT-001" as "docx"
      Then the action is rejected with reason "export_format_invalid"

  Rule: The JSON export contains the contractually required sections

    Scenario: JSON export includes chat metadata, bound Document Version reference, message history, and insights block
      When cara exports "CHAT-001" as "json"
      Then the JSON contains a "chat" section with id, owner_id, document_version_id, locale, status, created_at, closed_at
      And the JSON contains a "document_version" reference with at least: id, source, source_document_id, title
      And the JSON contains a "messages" array where each entry has id, role, content, created_at
      And the JSON contains an "insights" block

  Rule: The insights block captures structured artefacts the user asked the AI to extract

    @happy
    Scenario: An in-chat extraction artefact appears in the insights block on export
      Given cara has asked the assistant during "CHAT-001" to "export the deadlines we discussed as JSON"
      And the assistant produced a structured deadlines artefact rendered inline in the chat
      When cara exports "CHAT-001" as "json"
      Then the JSON "insights" block contains that deadlines artefact
