# Trace:
# PRD Section: §3.3 TOTP (dormant)
# Requirement ID: AUTH-TOTP-3.3
# User Story: As the platform, I want TOTP infrastructure ready in v1 but with no UI surface, so that future launch is a UI-only change.
# Acceptance Criteria: Backend endpoints + data model exist; no UI route exposes enrolment, prompts, or settings entries; recovery-codes infra is stubbed.

@auth @dormant @totp
Feature: TOTP enrolment is wired in but has no v1 UI surface
  TOTP is "dormant infrastructure" per the PRD design principle: schema + endpoints exist,
  but the v1 UI must not expose it anywhere. These scenarios protect that contract by asserting
  the absence of UI exposure.

  Rule: No UI route renders a TOTP enrolment surface

    @negative
    Scenario Outline: A would-be TOTP enrolment route does not render in v1
      Given a signed-in individual user "alice@example.test" on tier "tier_1"
      When the user navigates to "<route>"
      Then the route is not registered in the v1 UI
      And the user is shown the 404 surface

      Examples:
        | route                            |
        | /account/security/totp           |
        | /account/security/two-factor     |
        | /account/totp/enrol              |

  Rule: Account settings expose no TOTP affordance

    @negative
    Scenario: TOTP enrolment is absent from account settings
      Given a signed-in individual user "alice@example.test" on tier "tier_1"
      When the user opens the account settings surface
      Then no link, button, or section labelled with any of ["TOTP", "Two-factor", "2FA", "Authenticator"] is rendered

  Rule: Sign-in flow does not prompt for TOTP

    @negative
    Scenario: Sign-in completes without any TOTP prompt
      Given an account "alice@example.test" exists with password "Str0ng-Pass!1"
      When the user submits the sign-in form with email "alice@example.test" and password "Str0ng-Pass!1"
      Then a session is established directly
      And no surface asks for a TOTP code or a recovery code

  Rule: Recovery-code surfaces are absent

    @negative
    Scenario Outline: Recovery-code routes do not render in v1
      Given a signed-in individual user "alice@example.test" on tier "tier_1"
      When the user navigates to "<route>"
      Then the route is not registered in the v1 UI
      And the user is shown the 404 surface

      Examples:
        | route                                     |
        | /account/security/recovery-codes          |
        | /account/security/recovery-codes/regenerate |

  Rule: The backend TOTP schema exists but holds no production data

    @rls
    Scenario: The totp_enrolments storage is empty in production
      Given v1 is running in production
      Then no rows exist in the dormant TOTP enrolment storage
      And RLS prohibits writes from any non-service-role principal
