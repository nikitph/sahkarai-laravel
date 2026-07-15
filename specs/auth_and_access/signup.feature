# Trace:
# PRD Section: §3.1 Sign-up and sign-in
# Requirement ID: AUTH-SIGNUP-3.1
# User Story: As a prospective end user, I want to create an account with email + password and start using the product immediately, without email-verification friction.
# Acceptance Criteria: Email + password is the only auth method; signup completes with no verification gate; user is signed in immediately; email is captured but advisory only.

@auth @individual @happy
Feature: Account sign-up with email and password
  v1 supports email + password signup only. No OAuth. No email verification is enforced.
  The new user is signed in immediately on a successful signup.

  Background:
    Given the auth providers configured for v1 are exactly ["email_password"]

  Rule: A new email + password signup creates an individual_member account and an active session

    @smoke
    Scenario: Successful signup with new email creates an account and signs the user in
      Given no account exists for "newuser@example.test"
      When the user submits the signup form with email "newuser@example.test", password "Str0ng-Pass!1", and locale "en"
      Then an account is created for "newuser@example.test"
      And the account role is "individual_member"
      And the account tier is "free"
      And the account locale is "en"
      And a session is established for the user
      And the user is redirected to the authenticated landing surface

    @locale
    Scenario Outline: Successful signup persists the chosen locale
      Given no account exists for "newuser@example.test"
      When the user submits the signup form with email "newuser@example.test", password "Str0ng-Pass!1", and locale "<locale>"
      Then the account locale is "<locale>"

      Examples:
        | locale |
        | en     |
        | hi     |
        | gu     |
        | mr     |

  Rule: Email is captured but is not verified at signup

    Scenario: No verification email is required to complete signup
      Given no account exists for "newuser@example.test"
      When the user submits the signup form with email "newuser@example.test" and password "Str0ng-Pass!1"
      Then the signup completes without any "verify your email" gate
      And the user is signed in immediately
      And the account is marked email_verified=false

    Scenario: Account is fully usable with an unverified email
      Given an account "newuser@example.test" exists with email_verified=false
      And the user is signed in
      When the user opens the Archive
      Then the Archive loads with Free-tier access
      And no banner blocks usage on the basis of email verification

  Rule: Only the email + password provider is offered in v1

    @negative
    Scenario Outline: OAuth provider attempt is rejected
      Given the signup surface is rendered
      When the user attempts to sign up via "<provider>"
      Then the provider is not offered on the signup surface
      And no account is created

      Examples:
        | provider |
        | google   |
        | github   |
        | apple    |

  Rule: Duplicate email signups are rejected

    @negative
    Scenario: Signup with an email that already exists is rejected
      Given an account exists for "alice@example.test"
      When a user submits the signup form with email "alice@example.test" and password "Str0ng-Pass!1"
      Then the signup fails with reason "email_already_in_use"
      And no second account is created
      And no session is created for the submitter

  Rule: Malformed credentials are rejected with field-level reasons

    @negative @boundary
    Scenario Outline: Invalid signup input is rejected
      Given no account exists for "<email>"
      When a user submits the signup form with email "<email>" and password "<password>"
      Then the signup fails with reason "<reason>"
      And no account is created

      Examples:
        | email                  | password      | reason             |
        |                        | Str0ng-Pass!1 | email_required     |
        | not-an-email           | Str0ng-Pass!1 | email_malformed    |
        | newuser@example.test   |               | password_required  |
