# Trace:
# PRD Section: §3.2 Password reset
# Requirement ID: AUTH-RESET-3.2
# User Story: As an end user, I want to reset my password via an emailed link if I forget it.
# Acceptance Criteria: Standard email-link reset; only works if the user's email is real; a fake-email user must contact support.

@auth @individual
Feature: Password reset via emailed link
  Password recovery is the standard email-link flow. Because email verification is not
  enforced at signup, recovery only succeeds for users whose email is real and reachable.

  Rule: A reset email is sent to the address on file when one is requested

    @happy
    Scenario: Reset request for an existing email sends a reset link to that address
      Given an account "alice@example.test" exists
      When the user submits the password-reset form with email "alice@example.test"
      Then a password-reset email is dispatched to "alice@example.test"
      And the response shown to the requester is "If an account exists for that email, a reset link has been sent."

  Rule: The response to a reset request does not disclose whether the email is registered

    @negative @rbac
    Scenario: Reset request for an unknown email returns the same response without sending mail
      Given no account exists for "ghost@example.test"
      When the user submits the password-reset form with email "ghost@example.test"
      Then no password-reset email is dispatched
      And the response shown to the requester is "If an account exists for that email, a reset link has been sent."

  Rule: A user with a real but unreachable email path must use the support channel

    @negative
    Scenario: User with a fake email cannot self-recover
      Given an account "fake@example.test" exists where the address is not deliverable
      When the user submits the password-reset form with email "fake@example.test"
      Then the response shown to the requester is "If an account exists for that email, a reset link has been sent."
      And the reset email dispatch is recorded as "bounced" by the email provider
      # The PRD explicitly accepts this case must go through support.

  Rule: Reset links are single-use and time-bounded

    @state-transition
    Scenario: A valid reset link sets a new password and invalidates itself
      Given a password-reset email was dispatched to "alice@example.test" with token "RESET-001" at "2026-05-15T09:00:00Z"
      And the current time is "2026-05-15T09:10:00Z"
      When the user opens reset token "RESET-001" and submits new password "Newp@ssw0rd-2"
      Then the password for "alice@example.test" is updated
      And reset token "RESET-001" is marked consumed
      And subsequent use of "RESET-001" is rejected with reason "reset_token_consumed"

    @negative @boundary
    Scenario: An expired reset token is rejected
      Given a password-reset email was dispatched to "alice@example.test" with token "RESET-002" at "2026-05-15T09:00:00Z"
      And reset tokens expire after 60 minutes per the auth provider default
      And the current time is "2026-05-15T10:01:00Z"
      When the user opens reset token "RESET-002"
      Then the reset is rejected with reason "reset_token_expired"
      # The 60-minute window is provider default; if Product wants a different TTL, see OQ-AUTH-001.
