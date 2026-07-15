# Trace:
# PRD Section: §5.6 Terminal interpretation failure
# Requirement ID: INTERP-5.6
# User Story: As an end user, when an Interpretation cannot be produced for a Document, I want to still see the original and a clear message about the missing Interpretation.
# Acceptance Criteria: The detail page shows the raw download and metadata as normal plus the message "Interpretation not available for this document." — no apology, no retry-yourself button, no ETA.

@interpretation
Feature: Terminal Interpretation failure rendering
  A Document Version with interpretation_status="interpretation_failed" still renders the raw
  download and metadata. It shows only the §5.6 short message in place of the Interpretation.

  Rule: The detail page shows the raw download and metadata as normal

    @happy
    Scenario: A Tier 1 user opens a Document Version with interpretation_failed
      Given a Tier 1 user "bea@example.test"
      And a Document Version "DV-3" with interpretation_status "interpretation_failed"
      When bea opens "DV-3"
      Then the page shows title, source, source URL, published date, document type
      And a "Download original" button is rendered

  Rule: The page shows exactly the §5.6 message in place of the Interpretation block

    Scenario: The exact §5.6 message is rendered
      Given a Tier 1 user "bea@example.test"
      And a Document Version "DV-3" with interpretation_status "interpretation_failed"
      When bea opens "DV-3"
      Then the page renders exactly the message "Interpretation not available for this document."
      And no Interpretation summary, takeaways, glossary, or applicability tags are rendered

  Rule: The page does not surface a "retry" affordance to the user

    @negative
    Scenario: No retry button is rendered on the terminal-failure page
      Given a Tier 1 user "bea@example.test"
      And a Document Version "DV-3" with interpretation_status "interpretation_failed"
      When bea opens "DV-3"
      Then no button or link labelled with any of ["Retry", "Try again", "Regenerate"] is rendered

  Rule: The page does not state an ETA for the Interpretation

    @negative
    Scenario: No ETA text is rendered on the terminal-failure page
      Given a Tier 1 user "bea@example.test"
      And a Document Version "DV-3" with interpretation_status "interpretation_failed"
      When bea opens "DV-3"
      Then no text containing any of ["soon", "ETA", "coming", "expected by"] is rendered relative to the Interpretation
