# Trace:
# PRD Section: §4.1 Polling
# Requirement ID: ING-POLL-4.1
# User Story: As the platform, I want each Source polled on its own schedule with observable status, so ops can see ingestion health.
# Acceptance Criteria: Twice-daily default per Source (staggered); each run writes a poll_run row with started_at/finished_at/status/counts/errors; failed runs don't block the next run; 3 consecutive failures alert ops.

@ingestion @nfr
Feature: Source polling produces auditable poll runs
  Each Source is polled on a schedule. Every poll run produces a poll_run record consumed by the
  ops dashboard. Failures do not block the schedule.

  Background:
    Given the Sources configured for v1 are exactly ["RBI", "IT", "GST"]
    And each Source has a poll schedule of twice daily, staggered across Sources

  Rule: A successful poll run writes a poll_run row with status "ok"

    @happy
    Scenario: A successful RBI poll writes a complete poll_run row
      Given the RBI adapter returns 5 candidate Documents at "2026-05-15T06:00:00Z" and ingests 5 new ones
      When the RBI poll completes successfully at "2026-05-15T06:02:00Z"
      Then a poll_run row is written with: source="RBI", started_at="2026-05-15T06:00:00Z", finished_at="2026-05-15T06:02:00Z", status="ok", documents_discovered=5, documents_ingested=5, errors=[]

  Rule: A poll run that ingests no new documents still writes a poll_run row with status "ok"

    @happy @boundary
    Scenario: A no-new-documents poll writes an "ok" run with zero counts
      Given the RBI adapter returns 0 candidate Documents
      When the RBI poll completes at "2026-05-15T18:00:00Z"
      Then a poll_run row is written with status "ok", documents_discovered=0, documents_ingested=0

  Rule: A poll run that ingests some but errors on others is "partial"

    @state-transition
    Scenario: Some candidates fail acquisition; run status is "partial"
      Given the RBI adapter returns 3 candidate Documents
      And acquisition succeeds for 2 and fails for 1 with error "download_timeout"
      When the RBI poll completes
      Then the poll_run row has status "partial", documents_discovered=3, documents_ingested=2, errors contains "download_timeout"

  Rule: A poll run that fails entirely is recorded with status "failed"

    @state-transition
    Scenario: The RBI adapter is unreachable; run status is "failed"
      Given the RBI adapter raises "adapter_unreachable"
      When the RBI poll completes
      Then a poll_run row is written with status "failed", documents_discovered=0, documents_ingested=0, errors contains "adapter_unreachable"

  Rule: A failed poll run does not block the next scheduled run

    @state-transition
    Scenario: After a failed RBI run, the next scheduled RBI run still fires
      Given the previous RBI poll_run at "2026-05-15T06:00:00Z" has status "failed"
      When the schedule reaches "2026-05-15T18:00:00Z"
      Then a new RBI poll is invoked

  Rule: Three consecutive failures for a Source raise an ops alert

    @state-transition @ops
    Scenario: Third consecutive RBI failure raises an alert visible to ops
      Given the last two RBI poll_run rows have status "failed"
      When a third consecutive RBI poll fails
      Then an ops alert is raised tagged "ingestion.rbi.three_consecutive_failures"
      And the ops dashboard surfaces this alert in the "last successful poll per Source" panel

    @boundary
    Scenario: Two consecutive failures do not raise an ops alert
      Given the last RBI poll_run has status "failed"
      When a second consecutive RBI poll fails
      Then no ops alert is raised
