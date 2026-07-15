# Trace:
# PRD Section: §8.2 Creation
# Requirement ID: CHAT-8.2
# User Story: As a Tier 2 user, I want to start a Chat from a Document Version detail page with a single click.
# Acceptance Criteria: From a Document Version detail page, a Tier 2 User clicks "Start chat with this document"; a new Chat is created with chat_id, user_id, document_version_id, created_at, locale, status.

@chat @tier2
Feature: Chat creation from a Document Version detail page

  Background:
    Given a signed-in individual user "cara@example.test" on tier "tier_2" with account Locale "en"
    And a Document Version "DV-1" with a published Interpretation

  Rule: Tier 2 user creates a Chat via the detail page CTA

    @happy
    Scenario: Tier 2 user clicks "Start chat with this document" and a Chat is created
      When cara clicks "Start chat with this document" on "DV-1"
      Then a Chat is created with: user_id=cara, document_version_id="DV-1", locale="en", status="active", created_at=server time
      And cara is taken to the Chat surface for the new Chat

  Rule: The new Chat's locale matches the user's current Locale

    @locale
    Scenario Outline: New Chat locale matches user's current Locale
      Given cara's current Locale is "<locale>"
      When cara clicks "Start chat with this document" on "DV-1"
      Then the new Chat has locale "<locale>"

      Examples:
        | locale |
        | en     |
        | hi     |
        | gu     |
        | mr     |

  Rule: Free and Tier 1 users do not see and cannot trigger the chat-creation CTA

    @tier1 @negative
    Scenario: Tier 1 user does not see the CTA
      Given a signed-in individual user "bea@example.test" on tier "tier_1"
      When bea opens "DV-1"
      Then no "Start chat with this document" button is rendered

    @free @negative
    Scenario: Free user does not see the CTA
      Given a signed-in individual user "fred@example.test" on tier "free"
      When fred opens "DV-1"
      Then no "Start chat with this document" button is rendered

  Rule: Creating a Chat does not by itself consume credits

    @audit
    Scenario: Chat creation does not write a debit_message ledger entry
      Given cara has 200 credits remaining this cycle
      When cara clicks "Start chat with this document" on "DV-1"
      Then a new Chat is created
      And cara's credit balance remains 200
      And no debit_message ledger entry is created
