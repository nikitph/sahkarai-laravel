# Trace:
# PRD Section: §4.5 Versioning and revisions
# Requirement ID: ING-VER-4.5
# User Story: As an end user, I want revisions of a Document treated as new versions, with the prior version preserved and linked.
# Acceptance Criteria: A new content hash for an existing (source, source_document_id) creates a new Document Version row; supersedes link recorded; prior version preserved; Notification references the prior version; Archive search returns latest by default; revision triggers fresh Interpretation generation.

@ingestion
Feature: Document versioning and revision linking
  When the same Source Document reappears with a different content hash, the new bytes become
  a new Document Version. The older version is preserved unchanged and linked via supersedes.

  Background:
    Given a Document "RBI-CIRC-2026-001" with an existing Document Version "DV-1" content_hash "abc123" published "2026-05-10"

  Rule: A new content hash creates a new Document Version

    @state-transition
    Scenario: A revised circular ingests as a new Document Version
      Given the RBI adapter rediscovers "RBI-CIRC-2026-001" with new bytes content_hash "def456" published "2026-05-15"
      When acquisition and extraction succeed
      Then a new Document Version "DV-2" is created for "RBI-CIRC-2026-001" with content_hash "def456"
      And "DV-1" is preserved unchanged

  Rule: The new Document Version links to the prior via supersedes

    Scenario: supersedes link is recorded on the new version
      Given a new Document Version "DV-2" was just created for "RBI-CIRC-2026-001"
      Then "DV-2".supersedes_version_id is "DV-1"
      And "DV-1".supersedes_version_id is null

  Rule: A revision triggers a fresh Interpretation generation against the new version

    @state-transition
    Scenario: A fresh Interpretation is generated for the new version
      Given Document Version "DV-2" was just created with extraction_status "ok"
      When the Interpretation pipeline runs
      Then an Interpretation is generated bound to "DV-2"
      And the existing Interpretation bound to "DV-1" remains unchanged

  Rule: Archive browse and search return the latest Document Version by default

    Scenario: Browse list shows only the latest version per Document
      Given Document "RBI-CIRC-2026-001" has versions "DV-1" (2026-05-10) and "DV-2" (2026-05-15)
      When a user opens the Archive browse surface
      Then the list contains exactly one row for "RBI-CIRC-2026-001" referencing "DV-2"
      And "DV-1" does not appear in the default browse list

  Rule: The Document Version detail page exposes earlier versions

    Scenario: Versions panel lists all known versions
      Given Document "RBI-CIRC-2026-001" has versions ["DV-1" (2026-05-10), "DV-2" (2026-05-15)]
      When a user opens "DV-2"
      Then a "Versions" panel lists both versions with their dates and links

  Rule: The Notification for the new version references the prior version

    @notifications
    Scenario: The revision Notification cites the prior version
      Given Document Version "DV-2" was just published with a successful Interpretation at "2026-05-15T09:05:00Z"
      And Tier 1 user "bea@example.test" previously viewed "DV-1"
      When the Notification dispatcher runs
      Then bea receives an in-app Notification for "DV-2" that explicitly references "DV-1"
