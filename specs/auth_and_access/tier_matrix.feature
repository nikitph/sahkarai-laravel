# Trace:
# PRD Section: §2.3 Tier matrix (canonical)
# Requirement ID: MATRIX-2.3
# User Story: As the product, I want a single canonical access-control matrix so that any contradiction in other sections defers to this one.
# Acceptance Criteria: For each (audience, capability) pair, access is exactly as listed in §2.3; contradictory text elsewhere is overridden by this matrix.

@auth @rbac @smoke
Feature: Canonical tier access matrix
  The PRD declares the §2.3 matrix as the single source of truth for capability access.
  Every capability/audience pair in the matrix is enforced here so that contradictions
  elsewhere in the spec package are decidable against this file.

  Rule: Visitors (unauthenticated) are denied every gated capability

    @visitor @negative
    Scenario Outline: Visitor cannot access any capability gated by Free or above
      Given an unauthenticated visitor
      When the visitor attempts to "<capability>"
      Then access is denied
      And the visitor is redirected to the sign-in surface

      Examples:
        | capability                  |
        | Browse Archive              |
        | Search Archive              |
        | View raw Document           |
        | View Interpretation         |
        | Export Interpretation       |
        | Receive Notifications       |
        | Chat with a Document        |
        | Export Chat                 |

  Rule: Free tier users can browse, search, and view raw Documents only

    @free @individual @happy
    Scenario Outline: Free user can perform a Free-tier capability
      Given a signed-in individual user "alice@example.test" on tier "free"
      When the user attempts to "<capability>"
      Then the action succeeds

      Examples:
        | capability         |
        | Browse Archive     |
        | Search Archive     |
        | View raw Document  |

    @free @individual @negative
    Scenario Outline: Free user is denied a paid capability
      Given a signed-in individual user "alice@example.test" on tier "free"
      When the user attempts to "<capability>"
      Then access is denied with reason "tier_insufficient"

      Examples:
        | capability             |
        | View Interpretation    |
        | Export Interpretation  |
        | Receive Notifications  |
        | Chat with a Document   |
        | Export Chat            |

  Rule: Tier 1 users can do everything Free can plus Interpretation features and Notifications

    @tier1 @individual @happy
    Scenario Outline: Tier 1 user can perform a Tier 1 capability
      Given a signed-in individual user "bea@example.test" on tier "tier_1"
      When the user attempts to "<capability>"
      Then the action succeeds

      Examples:
        | capability             |
        | Browse Archive         |
        | Search Archive         |
        | View raw Document      |
        | View Interpretation    |
        | Export Interpretation  |
        | Receive Notifications  |

    @tier1 @individual @negative
    Scenario Outline: Tier 1 user is denied Tier 2-only capabilities
      Given a signed-in individual user "bea@example.test" on tier "tier_1"
      When the user attempts to "<capability>"
      Then access is denied with reason "tier_insufficient"

      Examples:
        | capability             |
        | Chat with a Document   |
        | Export Chat            |

  Rule: Tier 2 users can perform every end-user capability

    @tier2 @individual @happy
    Scenario Outline: Tier 2 user can perform every gated capability
      Given a signed-in individual user "cara@example.test" on tier "tier_2"
      When the user attempts to "<capability>"
      Then the action succeeds

      Examples:
        | capability             |
        | Browse Archive         |
        | Search Archive         |
        | View raw Document      |
        | View Interpretation    |
        | Export Interpretation  |
        | Receive Notifications  |
        | Chat with a Document   |
        | Export Chat            |

  Rule: The matrix overrides contradictory section text

    @rbac
    Scenario: A per-section assertion contradicting the matrix defers to the matrix
      Given a signed-in individual user "alice@example.test" on tier "free"
      And a per-section example elsewhere implies the Free user may export an Interpretation
      When the Free user attempts to "Export Interpretation"
      Then access is denied with reason "tier_insufficient"
      And the matrix decision wins over the contradictory example
