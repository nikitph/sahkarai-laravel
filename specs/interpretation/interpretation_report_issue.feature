# Trace:
# PRD Section: §5.5 Report Issue affordance
# Requirement ID: INTERP-5.5
# User Story: As a Tier 1/Tier 2 user, I want a Report Issue button on every Interpretation so ops can triage AI mistakes.
# Acceptance Criteria: Button on every Interpretation view; submission captures user_id, document_version_id, locale, free-text description, optional category from a fixed enum; lands in admin triage queue; in-app acknowledgement; no SLA in v1; no automatic re-generation.

@interpretation @tier1 @tier2
Feature: Report Issue on an Interpretation
  Every Interpretation surface carries a Report Issue button. Submissions land in the admin
  triage queue and do not auto-trigger re-generation.

  Background:
    Given a Tier 1 user "bea@example.test"
    And a Document Version "DV-1" with a published Interpretation

  Rule: Every Interpretation view renders a Report Issue button

    @happy
    Scenario: Report Issue is rendered for a Tier 1 user viewing an Interpretation
      When bea opens "DV-1"
      Then a "Report issue" button is rendered

  Rule: Submission requires a description and accepts one of the fixed categories or none

    @happy
    Scenario Outline: Issue submission with a category is captured
      When bea submits a report with category "<category>", locale "en", description "The applicability tag is wrong"
      Then an issue report is recorded with user_id=bea, document_version_id="DV-1", locale="en", category="<category>", description="The applicability tag is wrong"

      Examples:
        | category            |
        | inaccurate          |
        | mistranslation      |
        | missing_takeaway    |
        | wrong_applicability |
        | other               |

    @happy
    Scenario: Issue submission with no category is accepted
      When bea submits a report with no category, locale "hi", description "Glossary missing"
      Then an issue report is recorded with category=null

    @negative
    Scenario: Issue submission with empty description is rejected
      When bea submits a report with category "inaccurate", locale "en", and empty description
      Then the submission is rejected with reason "description_required"
      And no issue report is recorded

  Rule: A submitted report appears in the admin triage queue

    @admin
    Scenario: A new report appears in the admin triage queue
      Given bea has just submitted a report on "DV-1" with category "inaccurate"
      When a saas_admin opens the issue triage surface
      Then the new report is listed with triage_status "open"

  Rule: The reporting user receives an in-app acknowledgement

    @happy
    Scenario: In-app acknowledgement is rendered after submission
      When bea submits any valid report
      Then bea sees an in-app acknowledgement message confirming submission

  Rule: Submitting a report does not automatically regenerate the Interpretation

    Scenario: No regeneration job is enqueued by a Report Issue submission
      Given bea has just submitted a report on "DV-1"
      Then no Interpretation regeneration job is enqueued for "DV-1"

  Rule: v1 has no SLA on report response time

    Scenario: No SLA timer is started on a new report
      Given bea has just submitted a report on "DV-1"
      Then no SLA-bound deadline is recorded against the report
