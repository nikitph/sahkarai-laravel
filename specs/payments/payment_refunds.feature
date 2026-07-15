# Trace:
# PRD Section: §10.8 Refunds
# Requirement ID: PAY-10.8
# User Story: As an end user, I cannot self-serve refunds in v1; refunds are handled by support manually.
# Acceptance Criteria: No automated refunds in v1; manual via support.

@payments @dormant
Feature: Refunds are manual via support in v1

  Rule: No self-service refund flow exists

    @negative
    Scenario Outline: A would-be refund-request route does not render in v1
      Given a signed-in individual user "cara@example.test" on tier "tier_2"
      When cara navigates to "<route>"
      Then the route is not registered in the v1 UI
      And cara is shown the 404 surface

      Examples:
        | route                  |
        | /billing/refund        |
        | /account/refund        |
        | /support/refund-request |

  Rule: A manual refund issued by support is reflected as a credit-ledger adjustment

    @audit
    Scenario: A manual refund flows through an "adjustment" credit-ledger entry where applicable
      Given a saas_admin issues a manual refund for cara of value equivalent to N credits
      When the manual refund is recorded
      Then an "adjustment" credit_ledger entry is written referencing the refund
      # The cash-side refund itself happens in the payment provider's UI, not in this product. Captured in COVERAGE_GAPS.
