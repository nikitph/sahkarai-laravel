# Trace:
# PRD Section: §2.5 RLS guarantees (Chat privacy bullets)
# Requirement ID: RLS-2.5-CHAT
# User Story: As a Tier 2 user, I want my chat content to be private even from SahkarAI staff, because this is a compliance product.
# Acceptance Criteria: A user can read only their own Chats and Chat Messages; saas_admin cannot read Chat Message bodies; only Tier 2 with credit > 0 can write a Chat Message.

@auth @rls @chat
Feature: Row-Level Security for Chats and Chat Messages
  Chat content is private to the owning user. saas_admin can read chat metadata where
  needed for ops but cannot read chat message bodies — this is a deliberate trust constraint
  for the compliance product.

  Background:
    Given an individual user "alice@example.test" on tier "tier_2"
    And an individual user "bob@example.test" on tier "tier_2"
    And a saas_admin "ops@sahkarai.test"
    And a Document Version "RBI-CIRC-2026-001" with a published Interpretation
    And alice has a Chat "CHAT-001" bound to "RBI-CIRC-2026-001" with message "Alice asked about CRR"

  Rule: A user can read only their own Chats and Chat Messages

    @rbac @negative
    Scenario: Bob cannot read Alice's chat
      When bob requests "CHAT-001"
      Then the request is denied with reason "forbidden"
      And no message body is returned

    @rbac @negative
    Scenario: Bob cannot list Alice's chats
      When bob requests the chat-list scoped to alice
      Then the request is denied with reason "forbidden"

    @happy
    Scenario: Alice can read her own chat
      When alice requests "CHAT-001"
      Then the response includes Chat metadata and the message "Alice asked about CRR"

  Rule: saas_admin cannot read Chat Message bodies

    @admin @rbac @negative
    Scenario: saas_admin requesting chat message bodies is denied at the row level
      When ops requests the messages of "CHAT-001"
      Then the request is denied with reason "forbidden"
      And no message body is returned

    @admin
    Scenario: saas_admin may see non-body chat metadata required for ops aggregation
      When ops requests the aggregated chat metadata for "CHAT-001"
      Then the response includes chat id, owner id, document version id, created_at, status
      And the response excludes any message body content

  Rule: Only a Tier 2 owner with credit > 0 can write a new Chat Message

    @tier2 @happy
    Scenario: Alice with credits remaining writes a new message
      Given alice has 50 credits remaining in the current cycle
      When alice sends "What is CRR?" to "CHAT-001"
      Then the message is persisted
      And alice's credit balance is debited by 1

    @tier2 @negative
    Scenario: Alice with zero credits cannot write a new message
      Given alice has 0 credits remaining in the current cycle
      When alice sends "follow-up?" to "CHAT-001"
      Then the write is denied with reason "no_credits_remaining"
      And no message is persisted

    @tier1 @negative
    Scenario: A Tier 1 user cannot write a Chat Message under any circumstances
      Given an individual user "bea@example.test" on tier "tier_1"
      When bea attempts to send a message to any chat
      Then the write is denied with reason "tier_insufficient"
      And no message is persisted

    @free @negative
    Scenario: A Free user cannot write a Chat Message under any circumstances
      Given an individual user "fred@example.test" on tier "free"
      When fred attempts to send a message to any chat
      Then the write is denied with reason "tier_insufficient"
      And no message is persisted
