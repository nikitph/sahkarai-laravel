# Trace:
# PRD Section: §11 Internationalisation (email half) and §7.4 (Locale of delivery)
# Requirement ID: I18N-11-EMAIL
# User Story: As an end user, I want email content in my preferred Locale.
# Acceptance Criteria: Email content sent in the User's preferred Locale.

@i18n @locale @notifications
Feature: Email content localisation

  Rule: Notification emails are rendered in the recipient's account Locale

    @happy
    Scenario Outline: Notification email body Locale matches recipient's Locale
      Given a Tier 1 user with account Locale "<locale>"
      And the user has Notification preferences enabled for "RBI" with email_cadence "immediate"
      And a new Document Version is ingested for "RBI" with a published Interpretation
      When the email Notification is dispatched
      Then the email body is rendered in Locale "<locale>"

      Examples:
        | locale |
        | en     |
        | hi     |
        | gu     |
        | mr     |

  Rule: Password-reset email is rendered in the recipient's account Locale

    @auth
    Scenario Outline: Password-reset email Locale matches recipient's Locale
      Given an account "user@example.test" with account Locale "<locale>"
      When the user submits the password-reset form
      Then the dispatched email body is rendered in Locale "<locale>"

      Examples:
        | locale |
        | en     |
        | hi     |
        | gu     |
        | mr     |
