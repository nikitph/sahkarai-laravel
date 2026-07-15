# Trace:
# PRD Section: §12.3 Auditability
# Requirement ID: NFR-12.3
# User Story: As ops, I want every Chat message, credit movement, and Notification send to be observable in an immutable audit trail.
# Acceptance Criteria: Every Chat Message persisted with server-side timestamp, immutable once written; every credit movement is a separate ledger entry of one of [grant_cycle, debit_message (refs chat_message_id), topup, adjustment]; every Notification send logged with channel, status, timestamp.

@nfr @audit
Feature: Audit guarantees for chat messages, credits, and notifications

  Rule: Every Chat Message is persisted with a server-side timestamp and is immutable

    @audit
    Scenario: A new Chat Message gets a server-side timestamp
      Given a signed-in individual user "cara@example.test" on tier "tier_2" with a Chat "CHAT-001"
      And the server time is "2026-05-15T10:00:00Z"
      When cara sends "What is CRR?" to "CHAT-001"
      Then the persisted Chat Message has role "user", content "What is CRR?", created_at "2026-05-15T10:00:00Z"

    @negative
    Scenario: A persisted Chat Message cannot be edited or deleted via any user-facing surface
      Given an existing Chat Message in "CHAT-001"
      When cara attempts to edit or delete the message via any UI affordance
      Then no UI exposes an edit or delete affordance for a persisted chat message
      And any direct API attempt is denied with reason "forbidden"

  Rule: Every credit movement is a separate ledger entry with one of the fixed reasons

    @audit @boundary
    Scenario Outline: A ledger entry's reason is one of the fixed values
      Given a credit_ledger row exists with reason "<reason>"
      Then "<reason>" is one of [grant_cycle, debit_message, topup, adjustment]

      Examples:
        | reason         |
        | grant_cycle    |
        | debit_message  |
        | topup          |
        | adjustment     |

  Rule: A debit_message ledger entry references the chat_message_id that caused it

    @audit
    Scenario: A debit_message entry carries the chat_message_id reference
      Given cara sends a message to "CHAT-001" and the message id is "MSG-001"
      Then a debit_message ledger entry is recorded with delta=-1 and reference="MSG-001"

  Rule: Every Notification send is logged with channel, status, and timestamp

    @audit
    Scenario: An in-app dispatch is logged
      Given a Notification "NOTIF-1" for a Tier 1 user
      When the in-app dispatcher delivers "NOTIF-1"
      Then a delivery log entry is written with notification_id="NOTIF-1", channel="in_app", status, and a server timestamp

    @audit
    Scenario: An email dispatch is logged
      Given a Notification "NOTIF-1" for a Tier 1 user with email cadence "immediate"
      When the email dispatcher delivers "NOTIF-1"
      Then a delivery log entry is written with notification_id="NOTIF-1", channel="email", status, and a server timestamp
