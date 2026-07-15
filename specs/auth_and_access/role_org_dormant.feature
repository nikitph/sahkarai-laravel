# Trace:
# PRD Section: §2.1 (Organisations schema-only); §13 Data model sketch
# Requirement ID: ROLE-2.1-ORG
# User Story: As the platform, I want Organisation tables and RLS in place but no UI exposure in v1, so the future Org launch is a UI change only.
# Acceptance Criteria: organizations and organization_members tables exist; org-scoped RLS exists; no UI creates Orgs, invites members, or assigns org_admin / org_member roles.

@auth @org @dormant
Feature: Organisation infrastructure is dormant in v1
  The organisations and organization_members storage exists with RLS scoped policies,
  but no v1 UI exposes Org creation, membership, or role assignment.

  Rule: No v1 UI creates an Organisation

    @negative
    Scenario Outline: A would-be Organisation creation route does not render
      Given a signed-in individual user "alice@example.test" on tier "tier_2"
      When the user navigates to "<route>"
      Then the route is not registered in the v1 UI
      And the user is shown the 404 surface

      Examples:
        | route                       |
        | /organisations              |
        | /organisations/new          |
        | /account/create-organisation|

  Rule: No v1 UI invites or manages Organisation members

    @negative
    Scenario Outline: A would-be Org membership management route does not render
      Given a signed-in individual user "alice@example.test" on tier "tier_2"
      When the user navigates to "<route>"
      Then the route is not registered in the v1 UI
      And the user is shown the 404 surface

      Examples:
        | route                                      |
        | /organisations/ORG-1/members               |
        | /organisations/ORG-1/invites               |
        | /organisations/ORG-1/roles                 |

  Rule: org_admin and org_member roles are not assignable in v1

    @negative @rbac
    Scenario Outline: A role assignment attempt to an Org role is denied
      Given a saas_admin "ops@sahkarai.test"
      When ops attempts to assign role "<role>" to any account
      Then the action is denied with reason "role_not_available_in_v1"

      Examples:
        | role        |
        | org_admin   |
        | org_member  |

  Rule: organizations and organization_members are empty in v1 production

    Scenario: Org tables hold zero rows in production
      Given v1 is running in production
      Then the organisations storage contains 0 rows
      And the organisation-membership storage contains 0 rows

  Rule: Org-scoped RLS policies exist and match no production row

    Scenario: Org-scoped RLS exists but no row matches it
      Given v1 is running in production
      Then RLS policies for org_admin and org_member exist on the relevant tables
      And no row currently matches those policies
