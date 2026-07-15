# Trace:
# PRD Section: §4.6 Interpretation generation
# Requirement ID: ING-INTERP-4.6
# User Story: As the platform, I want Interpretations generated automatically per Document Version, idempotently, with retries and a terminal-failure path.
# Acceptance Criteria: Triggered when a new Document Version is ingested + extraction succeeded; idempotent per Document Version; retried with backoff up to N (default 3); terminal failure marks `interpretation_failed`; no human review gate in v1.

@ingestion @interpretation
Feature: Interpretation generation pipeline
  Interpretations are generated automatically per Document Version when extraction has succeeded.
  Generation is idempotent per Document Version; transient failures are retried up to N times;
  terminal failure is observable in the ops dashboard.

  Rule: Generation is triggered automatically after successful extraction

    @happy @state-transition
    Scenario: A new Document Version with extraction_ok triggers generation
      Given a Document Version "DV-1" has just been created with extraction_status "ok"
      When the ingestion pipeline continues
      Then an Interpretation generation job is enqueued referencing "DV-1"

  Rule: Generation is not triggered when extraction failed

    @negative @state-transition
    Scenario: Extraction failure suppresses Interpretation generation
      Given a Document Version "DV-3" with extraction_status "extraction_failed"
      When the ingestion pipeline continues
      Then no Interpretation generation job is enqueued for "DV-3"

  Rule: Generation is idempotent per Document Version

    @idempotency
    Scenario: A repeated trigger for the same Document Version does not create a duplicate Interpretation
      Given Document Version "DV-1" already has a published Interpretation
      When an Interpretation generation job for "DV-1" is enqueued a second time
      Then no second Interpretation row is created for "DV-1"

  Rule: A failed attempt is retried with backoff up to N (default 3)

    @state-transition
    Scenario: First and second attempts fail; third attempt succeeds
      Given Document Version "DV-1" with extraction_status "ok" and the retry limit N=3
      And the generator transient-fails the first two attempts with "model_timeout"
      When the third attempt succeeds
      Then an Interpretation row is published bound to "DV-1"
      And exactly one Interpretation row exists for "DV-1"

  Rule: Terminal failure marks the Document Version `interpretation_failed` and queues ops review

    @state-transition @negative
    Scenario: All N attempts fail; the Document Version is marked interpretation_failed
      Given Document Version "DV-1" with extraction_status "ok" and the retry limit N=3
      And every generation attempt fails with "model_timeout"
      When the retry budget is exhausted
      Then "DV-1".interpretation_status is "interpretation_failed"
      And "DV-1" appears in the ops dashboard `interpretation_failed` count
      And no Notification is dispatched for "DV-1"

  Rule: No human review gate exists in v1

    Scenario: A freshly generated Interpretation is published without any approval step
      Given Document Version "DV-1" generation has just succeeded
      Then the Interpretation is immediately readable by Tier 1 and Tier 2 users
      And no review or approval queue blocks the Interpretation from publishing

  Rule: Provenance is recorded for every Interpretation

    @audit
    Scenario: model_id, prompt_version, generated_at are recorded
      Given Document Version "DV-1" generation has just succeeded with model_id "model-x" and prompt_version "p-2026-05-01"
      Then the Interpretation row has model_id "model-x", prompt_version "p-2026-05-01", and a non-empty generated_at timestamp
