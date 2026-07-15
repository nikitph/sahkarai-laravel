# Trace:
# PRD Section: §4.3 Acquisition
# Requirement ID: ING-ACQ-4.3
# User Story: As the platform, I want to permanently store every original file locally and treat the regulator URL as attribution-only.
# Acceptance Criteria: Originals stored at `originals/{source}/{yyyy}/{mm}/{source_document_id}.{ext}` in Supabase Storage; SHA-256 recorded; users never redirected to regulator for the file.

@ingestion
Feature: Acquisition and local storage of originals
  Original files are downloaded once and stored locally. The user always downloads from us,
  never from the regulator. A SHA-256 hash is recorded for revision detection.

  Rule: Originals are stored at the canonical storage path

    @happy
    Scenario: An RBI PDF circular is stored at the canonical path
      Given the RBI adapter returns a candidate with source_document_id "RBI-CIRC-2026-001", published_date "2026-05-15", and an attached PDF
      When acquisition runs
      Then the file is stored at "originals/rbi/2026/05/RBI-CIRC-2026-001.pdf"

    @happy
    Scenario Outline: Storage path includes source, year, month, id, and extension
      Given a Source Document with source "<source>", source_document_id "<id>", published_date "<date>", and extension "<ext>"
      When acquisition runs
      Then the file is stored at "originals/<source_lower>/<yyyy>/<mm>/<id>.<ext>"

      Examples:
        | source | id                 | date       | ext  | source_lower | yyyy | mm |
        | RBI    | RBI-CIRC-2026-001  | 2026-05-15 | pdf  | rbi          | 2026 | 05 |
        | IT     | IT-NOTIF-2026-007  | 2026-04-09 | pdf  | it           | 2026 | 04 |
        | GST    | GST-CIRC-2026-012  | 2026-03-01 | html | gst          | 2026 | 03 |

  Rule: A SHA-256 hash is recorded on ingestion

    Scenario: The Document Version stores a SHA-256 of the stored bytes
      Given an RBI Document acquisition succeeds with bytes whose SHA-256 is "abc123..."
      When the Document Version is written
      Then the Document Version stores content_hash="abc123..."

  Rule: The source_url is preserved for attribution but never served as the download

    Scenario: Document Version detail page download button serves the locally stored file
      Given a Document Version "RBI-CIRC-2026-001" stored at "originals/rbi/2026/05/RBI-CIRC-2026-001.pdf"
      When a user clicks "Download original" on its detail page
      Then the download is served from local storage at "originals/rbi/2026/05/RBI-CIRC-2026-001.pdf"
      And the user is not redirected to source_url

  Rule: Acquisition failure marks the candidate as not ingested and records the error

    @negative
    Scenario: A 5xx from the Source means the candidate is not ingested this run
      Given the RBI adapter returns a candidate "RBI-CIRC-2026-001"
      And the original download responds with HTTP 503
      When acquisition runs
      Then no Document Version row is created for this candidate in this run
      And the poll_run errors include "acquisition_failed:RBI-CIRC-2026-001"
