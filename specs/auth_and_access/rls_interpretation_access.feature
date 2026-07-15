# Trace:
# PRD Section: §2.5 RLS (Interpretation access bullet)
# Requirement ID: RLS-2.5-INTERP
# User Story: As the platform, I want Interpretations readable only by Tier 1 and Tier 2 users.
# Acceptance Criteria: A User can read Interpretations only if Tier 1 or Tier 2.

@auth @rls @interpretation
Feature: Row-Level Security for Interpretations
  Interpretation rows are accessible only to users on tier_1 or tier_2.
  Visitors and Free users cannot read Interpretation rows even via direct API call.

  Background:
    Given a Document Version "RBI-CIRC-2026-001" with a published Interpretation

  Rule: Visitors cannot read Interpretation rows

    @visitor @negative @rbac
    Scenario: An unauthenticated request for an Interpretation is denied
      Given an unauthenticated visitor
      When the visitor requests the Interpretation for "RBI-CIRC-2026-001"
      Then the request is denied with reason "unauthenticated"

  Rule: Free users cannot read Interpretation rows

    @free @individual @negative @rbac
    Scenario: A Free user requesting an Interpretation is denied
      Given a signed-in individual user "fred@example.test" on tier "free"
      When fred requests the Interpretation for "RBI-CIRC-2026-001"
      Then the request is denied with reason "tier_insufficient"

  Rule: Tier 1 and Tier 2 users can read Interpretation rows

    @tier1 @happy
    Scenario: A Tier 1 user reads the Interpretation
      Given a signed-in individual user "bea@example.test" on tier "tier_1"
      When bea requests the Interpretation for "RBI-CIRC-2026-001"
      Then the response contains the Interpretation payload

    @tier2 @happy
    Scenario: A Tier 2 user reads the Interpretation
      Given a signed-in individual user "cara@example.test" on tier "tier_2"
      When cara requests the Interpretation for "RBI-CIRC-2026-001"
      Then the response contains the Interpretation payload

  Rule: A user cannot write to Interpretation rows via any client surface

    @negative @rbac
    Scenario Outline: Direct writes to Interpretation rows are denied for non-service principals
      Given a signed-in user on role "<role>"
      When the user attempts to insert, update, or delete an Interpretation row
      Then the write is denied with reason "forbidden"

      Examples:
        | role                |
        | individual_member   |
        | saas_admin          |
