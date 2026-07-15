# Trace:
# PRD Section: §4.4 Extraction
# Requirement ID: ING-EXT-4.4
# User Story: As the platform, I want text extracted once per Document Version and persisted, with a clear failure path that still lets users download the original.
# Acceptance Criteria: Extracted text stored as a separate artefact; never re-extracted on the fly; extraction failure marks the version `extraction_failed` and is queued for ops review.

@ingestion
Feature: Text extraction from acquired originals
  Each Document Version's original is extracted into text once and the extracted artefact
  is persisted. On failure, the raw download remains available but no Interpretation is produced.

  Rule: Successful extraction persists an extracted-text artefact and sets status "ok"

    @happy
    Scenario: A PDF is extracted and the text is persisted
      Given a Document Version "RBI-CIRC-2026-001" with original at "originals/rbi/2026/05/RBI-CIRC-2026-001.pdf"
      When extraction succeeds
      Then an extracted-text artefact is persisted at "extracted/rbi/2026/05/RBI-CIRC-2026-001.txt"
      And the Document Version's extraction_status is "ok"

  Rule: Extracted text is read from storage on every subsequent need; never re-extracted

    @idempotency
    Scenario: A second consumer reads the same extracted artefact rather than re-extracting
      Given a Document Version "RBI-CIRC-2026-001" with extraction_status "ok"
      When the Interpretation generator and the search indexer each need the extracted text
      Then both read the same persisted extracted-text artefact
      And no extraction job is invoked a second time

  Rule: Extraction failure marks the Document Version `extraction_failed` and queues ops review

    @state-transition @negative
    Scenario: A non-extractable scanned-only PDF is marked `extraction_failed`
      Given a Document Version "RBI-CIRC-2026-002" whose original yields no extractable text
      When extraction runs
      Then the Document Version's extraction_status is "extraction_failed"
      And it appears in the ops dashboard `extraction_failed` count
      And no Interpretation generation is triggered

  Rule: A Document Version with extraction_failed remains visible as a raw download

    @state-transition
    Scenario: Users can still download an extraction_failed Document Version's original
      Given a Document Version "RBI-CIRC-2026-002" with extraction_status "extraction_failed"
      And an individual user "fred@example.test" on tier "free"
      When fred opens "RBI-CIRC-2026-002"
      Then the page shows title, source, published date, document type
      And a "Download original" button is rendered
      And no Interpretation block is rendered
