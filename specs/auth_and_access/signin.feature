# Trace:
# PRD Section: §3.1 Sign-up and sign-in
# Requirement ID: AUTH-SIGNIN-3.1
# User Story: As an existing user, I want to sign in with my email and password.
# Acceptance Criteria: Email + password is the only sign-in method; correct credentials produce a session; incorrect credentials are rejected.

@auth @happy
Feature: Sign-in with email and password
  v1 sign-in supports email + password only. A successful sign-in establishes a Supabase Auth session.

  Rule: Correct email + password produces an active session

    @smoke
    Scenario: Existing account signs in with correct credentials
      Given an account "alice@example.test" exists with password "Str0ng-Pass!1"
      When the user submits the sign-in form with email "alice@example.test" and password "Str0ng-Pass!1"
      Then a session is established for "alice@example.test"
      And the user is redirected to the authenticated landing surface

  Rule: Wrong credentials are rejected without leaking which field was wrong

    @negative
    Scenario: Wrong password is rejected with a generic reason
      Given an account "alice@example.test" exists with password "Str0ng-Pass!1"
      When the user submits the sign-in form with email "alice@example.test" and password "wrong-password"
      Then sign-in fails with reason "invalid_credentials"
      And no session is established

    @negative
    Scenario: Unknown email is rejected with a generic reason
      Given no account exists for "ghost@example.test"
      When the user submits the sign-in form with email "ghost@example.test" and password "anything"
      Then sign-in fails with reason "invalid_credentials"
      And no session is established

  Rule: Email verification status does not gate sign-in

    Scenario: Account with email_verified=false can sign in
      Given an account "newuser@example.test" exists with email_verified=false and password "Str0ng-Pass!1"
      When the user submits the sign-in form with email "newuser@example.test" and password "Str0ng-Pass!1"
      Then a session is established for "newuser@example.test"

  Rule: Soft-deleted accounts cannot sign in via the sign-in surface

    @negative @state-transition
    Scenario: An account inside its 30-day soft-delete window cannot sign in via the sign-in surface
      Given an account "deleted@example.test" was soft-deleted on "2026-05-01T10:00:00Z"
      And the current time is "2026-05-15T10:00:00Z"
      When the user submits the sign-in form with email "deleted@example.test" and password "Str0ng-Pass!1"
      Then sign-in fails with reason "account_pending_deletion"
      And no session is established
      # Note: §9.2 says signing back in restores the account during the soft-delete window;
      # the surface that performs that restore (vs. the normal sign-in surface) is an OPEN QUESTION (see _meta/OPEN_QUESTIONS.md OQ-ACCT-001).

  Rule: Hard-deleted accounts behave as unknown emails

    @negative
    Scenario: After hard-delete (>30 days), the account is unknown
      Given the account previously at "deleted@example.test" was hard-deleted on "2026-04-01T10:00:00Z"
      And the current time is "2026-05-15T10:00:00Z"
      When the user submits the sign-in form with email "deleted@example.test" and password "anything"
      Then sign-in fails with reason "invalid_credentials"
