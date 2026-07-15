# Trace:
# PRD Section: §4.2 Discovery
# Requirement ID: ING-DISC-4.2
# User Story: As the platform, I want each Source's adapter to return candidate Documents and dedupe by (source, source_document_id) so I never re-ingest the same artefact.
# Acceptance Criteria: Adapter returns candidates with source_document_id, title, source_url, published_date, document_type, download handle; dedupe by (source, source_document_id).

@ingestion
Feature: Adapter discovery and deduplication
  Each Source adapter (HTML scraper, RSS reader, PDF index walker) returns candidate Documents
  with a uniform record. The pipeline deduplicates already-known (source, source_document_id) pairs.

  Rule: A candidate Document carries the contractually required fields

    Scenario: The RBI adapter returns a candidate with the required fields
      Given the RBI adapter discovers a Document with id "RBI-CIRC-2026-001"
      Then the candidate record includes source_document_id="RBI-CIRC-2026-001"
      And it includes a non-empty title
      And it includes a non-empty source_url
      And it includes a published_date in ISO-8601
      And it includes a document_type in [master_direction, circular, notification, press_release, faq, other]
      And it includes a download handle

  Rule: A (source, source_document_id) already ingested is skipped on rediscovery

    @idempotency
    Scenario: An already-ingested Document is skipped on the next poll
      Given a Document with (source="RBI", source_document_id="RBI-CIRC-2026-001") was previously ingested
      When the RBI adapter rediscovers the same (source, source_document_id) pair
      Then no new Document row is created
      And no new Document Version row is created when the content hash is unchanged

  Rule: Discovery counts include all candidates, ingestion counts include only new ones

    Scenario: A poll with one new and one existing candidate
      Given the RBI adapter returns 2 candidates: a new "RBI-CIRC-2026-002" and an existing "RBI-CIRC-2026-001"
      When the RBI poll completes
      Then the poll_run row has documents_discovered=2, documents_ingested=1

  Rule: A malformed candidate is skipped and recorded as an error

    @negative
    Scenario Outline: Malformed candidates are skipped with the matching error
      Given the RBI adapter returns a candidate missing "<field>"
      When the RBI poll runs
      Then the candidate is skipped
      And the poll_run errors include "candidate_invalid:<field>"

      Examples:
        | field              |
        | source_document_id |
        | title              |
        | source_url         |
        | published_date     |
        | document_type      |
        | download_handle    |
