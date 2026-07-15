# Trace:
# PRD Section: §4.7 Historical backfill
# Requirement ID: ING-BACK-4.7
# User Story: As a user opening v1, I want about a year of historical Documents in the Archive at launch.
# Acceptance Criteria: ~1 year backfill across the three Sources at launch; run as a one-off batch job, not via the polling cron; backfilled Documents are indistinguishable from live ones in the user-facing Archive; backfilled Documents do not trigger Notifications.

@ingestion @notifications
Feature: One-time historical backfill at launch
  v1 launches with about one year of backfill per Source. The backfill is a separate one-off job,
  is invisible to users as "backfilled", and does not generate Notifications.

  Rule: Backfill runs as a one-off batch job, not via the polling cron

    Scenario: The backfill runner is not the polling cron
      Given the launch backfill job has been configured
      Then the backfill is invoked by a one-off batch entry point
      And the polling cron does not invoke the backfill entry point

  Rule: Backfilled Documents are indistinguishable from live-ingested ones in the user-facing Archive

    @happy
    Scenario: A backfilled Document appears in the Archive list with the same affordances as a live-ingested one
      Given a backfilled Document Version "RBI-CIRC-2025-300" with a published Interpretation
      When a Tier 1 user opens the Archive browse surface
      Then the row for "RBI-CIRC-2025-300" renders with the same fields as live-ingested rows
      And no "backfilled" badge or other distinguishing affordance is rendered

  Rule: Backfilled Document Versions do not trigger Notifications

    @negative @notifications
    Scenario: A successful Interpretation publish on a backfilled Document Version produces no Notification
      Given a backfilled Document Version "RBI-CIRC-2025-300" with a successful Interpretation
      And a Tier 1 user "bea@example.test" whose subscription started "2026-05-01T00:00:00Z"
      When the Notification dispatcher considers "RBI-CIRC-2025-300"
      Then no in-app Notification is created for bea
      And no email Notification is dispatched for bea
