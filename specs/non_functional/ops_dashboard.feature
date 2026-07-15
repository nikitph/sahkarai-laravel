# Trace:
# PRD Section: §2.4 Admin access; §12.5 Observability
# Requirement ID: NFR-12.5
# User Story: As ops, I want a single dashboard showing ingestion health, failure counts, and the open issue-report queue.
# Acceptance Criteria: Ops dashboard surfaces last successful poll per Source, count of extraction_failed Document Versions, count of interpretation_failed Document Versions, open issue reports queue.

@nfr @ops @admin
Feature: Ops dashboard surfaces

  Background:
    Given a signed-in saas_admin "ops@sahkarai.test"

  Rule: The dashboard shows last successful poll per Source

    @happy
    Scenario: Last-successful-poll panel shows one row per Source
      Given the latest successful poll_run per Source is:
        | source | finished_at          |
        | RBI    | 2026-05-15T06:02:00Z |
        | IT     | 2026-05-15T07:01:00Z |
        | GST    | 2026-05-15T08:03:00Z |
      When ops opens the dashboard
      Then the "last successful poll per Source" panel lists exactly those three rows with their finished_at values

  Rule: The dashboard shows the count of `extraction_failed` Document Versions

    Scenario: Extraction-failed count panel
      Given the count of Document Versions in extraction_status="extraction_failed" is 4
      When ops opens the dashboard
      Then the "extraction_failed" panel shows count 4

  Rule: The dashboard shows the count of `interpretation_failed` Document Versions

    Scenario: Interpretation-failed count panel
      Given the count of Document Versions in interpretation_status="interpretation_failed" is 7
      When ops opens the dashboard
      Then the "interpretation_failed" panel shows count 7

  Rule: The dashboard shows the open issue reports queue

    Scenario: Open issue reports queue
      Given there are 12 issue reports with triage_status "open"
      When ops opens the dashboard
      Then the "open issue reports" panel shows count 12 and a link to the triage surface
