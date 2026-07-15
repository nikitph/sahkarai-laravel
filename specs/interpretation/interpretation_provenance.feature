# Trace:
# PRD Section: §5.3 Provenance
# Requirement ID: INTERP-5.3
# User Story: As ops, I want to know which model and prompt produced each Interpretation, and when.
# Acceptance Criteria: model_id, prompt_version, generated_at recorded for every Interpretation.

@interpretation @audit
Feature: Interpretation provenance
  Every published Interpretation carries provenance metadata.

  Rule: model_id, prompt_version, generated_at are non-empty for every published Interpretation

    Scenario: Provenance fields are present and non-empty
      Given a Document Version "DV-1" whose Interpretation has just been published
      Then the Interpretation row has a non-empty model_id
      And the Interpretation row has a non-empty prompt_version
      And the Interpretation row has a non-empty generated_at timestamp

  Rule: A regenerated Interpretation (after revision) carries the fresh provenance

    Scenario: A revision-generated Interpretation has its own provenance, not the prior's
      Given Document Version "DV-1" has an Interpretation with model_id "model-x" generated_at "2026-05-10T09:00:00Z"
      And a revision Document Version "DV-2" is created and its Interpretation is generated with model_id "model-y" at "2026-05-15T09:05:00Z"
      Then "DV-2"'s Interpretation has model_id "model-y" and generated_at "2026-05-15T09:05:00Z"
      And "DV-1"'s Interpretation provenance remains model_id "model-x" and generated_at "2026-05-10T09:00:00Z"
