# Trace:
# PRD Section: §3.4 Session management
# Requirement ID: AUTH-SESSION-3.4
# User Story: As an end user, I want to stay signed in across visits and to be able to sign out.
# Acceptance Criteria: Supabase Auth sessions; long-lived refresh; access tokens rotate per provider defaults; sign-out invalidates the refresh token.

@auth @individual
Feature: Sign-in session lifecycle
  v1 uses standard Supabase Auth sessions. Access tokens rotate per provider defaults;
  refresh tokens are long-lived; sign-out invalidates the refresh token.

  Rule: A new session has a refresh token and an access token

    @happy
    Scenario: Sign-in produces a session with both tokens
      Given an account "alice@example.test" exists with password "Str0ng-Pass!1"
      When the user submits the sign-in form with correct credentials
      Then a session is established with a non-empty access token
      And a non-empty refresh token

  Rule: An expired access token can be refreshed with a valid refresh token

    @state-transition
    Scenario: An expired access token is rotated when the refresh token is still valid
      Given a signed-in user "alice@example.test"
      And the access token has expired but the refresh token is still valid
      When the client invokes the token-refresh endpoint
      Then a new access token is issued
      And the same refresh token remains valid until its own expiry

  Rule: Sign-out invalidates the refresh token immediately

    @state-transition
    Scenario: Sign-out invalidates the refresh token
      Given a signed-in user "alice@example.test" with refresh token "REFRESH-A"
      When the user signs out
      Then "REFRESH-A" is invalidated
      And a subsequent token-refresh attempt using "REFRESH-A" is rejected with reason "refresh_token_revoked"

    @negative
    Scenario: A request bearing only the refresh token after sign-out is rejected
      Given a signed-in user "alice@example.test" who has just signed out
      When the client attempts a token-refresh using the previously issued refresh token
      Then the request is rejected with reason "refresh_token_revoked"

  Rule: An invalid or absent session denies access to authenticated routes

    @negative @rbac
    Scenario: Request with no access token is denied on authenticated routes
      Given no session is established
      When the client requests an authenticated route
      Then the request is denied with reason "unauthenticated"
      And the user is redirected to the sign-in surface
