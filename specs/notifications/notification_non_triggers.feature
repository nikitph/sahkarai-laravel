# Trace:
# PRD Section: §7.3 Non-triggers
# Requirement ID: NOTIF-7.3
# User Story: As an end user, I want Notifications to be predictable: no spam from backfill, no noise from failures, and nothing from Sources I've disabled.
# Acceptance Criteria: No Notification fires for: backfilled Document Versions; Document Versions where Interpretation generation terminally failed; Document Versions for Sources the User has disabled.

@notifications
Feature: Non-trigger conditions
  Three classes of events explicitly do not trigger a Notification.

  Background:
    Given a Tier 1 user "bea@example.test" whose subscription started "2026-05-01T00:00:00Z"

  Rule: Backfilled Document Versions never trigger Notifications

    @negative
    Scenario: A successful Interpretation publish on a backfilled Document Version produces no Notification
      Given a backfilled Document Version "RBI-CIRC-2025-300" ingested via the backfill batch job
      And its Interpretation was just published
      And bea has Notification preferences enabled for "RBI"
      When the Notification dispatcher considers the publication
      Then no Notification is created for bea referencing "RBI-CIRC-2025-300"

  Rule: Document Versions with terminal interpretation failure never trigger Notifications

    @negative
    Scenario: An interpretation_failed Document Version produces no Notification
      Given a Document Version "DV-3" with interpretation_status "interpretation_failed"
      And bea has Notification preferences enabled for the source of "DV-3"
      When the Notification dispatcher runs
      Then no Notification is created for bea referencing "DV-3"

  Rule: A user with a Source disabled receives no Notifications for that Source

    @negative
    Scenario: bea has RBI disabled and a new RBI Interpretation publishes
      Given bea has set source "RBI" to enabled=false
      And a Document Version "DV-NEW" was ingested for "RBI" at "2026-05-15T09:00:00Z"
      When the Interpretation for "DV-NEW" is successfully published
      Then no Notification is created for bea referencing "DV-NEW"
