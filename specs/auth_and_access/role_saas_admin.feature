# Trace:
# PRD Section: §2.1, §2.4
# Requirement ID: ROLE-2.1-ADMIN
# User Story: As SahkarAI staff, I want a saas_admin role that gives me ops visibility and triage but no end-user features.
# Acceptance Criteria: saas_admin sees ops dashboard, issue triage, user lookup; cannot start Chats; cannot consume credits; cannot receive end-user Notifications; cannot impersonate.

@auth @admin
Feature: saas_admin role capabilities and constraints
  saas_admin is the staff role. It is the only role besides individual_member reachable through
  any v1 UI surface. It has ops scope only.

  Background:
    Given a signed-in saas_admin "ops@sahkarai.test"

  Rule: saas_admin can read ops surfaces

    @happy
    Scenario: saas_admin opens the ops dashboard
      When ops navigates to the ops dashboard
      Then the dashboard renders with: last successful poll per Source, count of extraction_failed Document Versions, count of interpretation_failed Document Versions, open issue reports queue

    @happy
    Scenario: saas_admin opens the issue triage UI
      When ops navigates to the issue triage surface
      Then the queue lists all open issue reports

    @happy
    Scenario Outline: saas_admin looks up an end-user by email
      Given an individual user "alice@example.test" on tier "<tier>" with subscription "<sub_status>", credit balance <credits>, chat count <chats>, last activity "2026-05-14T12:00:00Z"
      When ops looks up "alice@example.test"
      Then the response shows tier "<tier>", subscription status "<sub_status>", credit balance <credits>, chat count <chats>, and last activity "2026-05-14T12:00:00Z"

      Examples:
        | tier   | sub_status | credits | chats |
        | free   | none       | 0       | 0     |
        | tier_1 | active     | 0       | 0     |
        | tier_2 | active     | 150     | 3     |

  Rule: saas_admin cannot start Chats or consume credits

    @negative @chat
    Scenario: saas_admin cannot start a Chat
      Given a Document Version "RBI-CIRC-2026-001" exists
      When ops attempts to start a Chat bound to "RBI-CIRC-2026-001"
      Then the action is denied with reason "role_not_permitted"
      And no chat row is created

    @negative @chat
    Scenario: saas_admin cannot send a Chat Message
      Given an existing chat "CHAT-001"
      When ops attempts to send a message to "CHAT-001"
      Then the action is denied with reason "role_not_permitted"
      And no message is persisted
      And no credit ledger entry is created

  Rule: saas_admin does not receive end-user Notifications

    @negative @notifications
    Scenario: A new published Interpretation does not produce a Notification for saas_admin
      Given a new Document Version is ingested for source "RBI" with a successfully published Interpretation
      When the Notification dispatcher runs
      Then no Notification is created for "ops@sahkarai.test"

  Rule: saas_admin cannot impersonate any user

    @negative @rbac
    Scenario Outline: A would-be impersonation route does not render
      When ops navigates to "<route>"
      Then the route is not registered in the v1 UI
      And ops is shown the 404 surface

      Examples:
        | route                          |
        | /admin/users/alice/impersonate |
        | /admin/impersonate             |

  Rule: saas_admin can triage issue reports

    @happy @state-transition
    Scenario Outline: saas_admin transitions an issue report
      Given an open issue report "ISSUE-001"
      When ops sets its triage_status to "<new_status>" with internal note "<note>"
      Then "ISSUE-001" has triage_status "<new_status>"
      And the internal note "<note>" is recorded
      And triaged_by is "ops@sahkarai.test"
      And triaged_at is set to the server time

      Examples:
        | new_status | note                          |
        | triaged    | Reviewed; assigning to ML team |
        | resolved   | Re-generated; new payload OK   |
        | wontfix    | User misunderstood applicability tag |
