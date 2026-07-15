# Trace:
# PRD Section: §3.5 Admin authentication; §2.4 Admin access
# Requirement ID: AUTH-ADMIN-3.5
# User Story: As SahkarAI staff, I want to sign in with the same email/password mechanism and reach the ops surface without a separate admin login.
# Acceptance Criteria: No separate admin login UI; admin status is set by DB assignment or bootstrap; no self-service "request admin" flow exists.

@auth @admin
Feature: Admin authentication uses the standard sign-in surface
  saas_admin accounts use the same email/password mechanism as end users.
  Admin status is provisioned by another saas_admin via DB assignment or a one-off bootstrap script.

  Rule: No separate admin login surface exists

    @negative
    Scenario Outline: An "admin login" route does not render
      Given an unauthenticated visitor
      When the visitor navigates to "<route>"
      Then the route is not registered in the v1 UI
      And the visitor is shown the 404 surface

      Examples:
        | route               |
        | /admin/login        |
        | /admin/sign-in      |
        | /staff/login        |

  Rule: A saas_admin signs in via the same sign-in surface as end users

    @happy
    Scenario: saas_admin signs in via /sign-in and reaches the ops dashboard
      Given an account "ops@sahkarai.test" exists with password "Str0ng-Pass!1" and role "saas_admin"
      When the user submits the sign-in form with email "ops@sahkarai.test" and password "Str0ng-Pass!1"
      Then a session is established
      And the user can navigate to the ops dashboard surface

  Rule: Admin status is not self-service

    @negative
    Scenario Outline: A would-be "request admin" route does not render
      Given a signed-in individual user "alice@example.test" on tier "tier_1"
      When the user navigates to "<route>"
      Then the route is not registered in the v1 UI
      And the user is shown the 404 surface

      Examples:
        | route                          |
        | /account/request-admin         |
        | /account/elevate               |

  Rule: Only an existing saas_admin can grant the saas_admin role to another account

    @rls @rbac @negative
    Scenario: An individual_member cannot self-promote to saas_admin via any UI
      Given a signed-in individual user "alice@example.test" on tier "tier_2"
      When the user attempts to update their own role to "saas_admin" through any UI affordance
      Then no UI exposes a role-edit affordance for the user's own role
      And any direct API attempt is denied with reason "forbidden"
