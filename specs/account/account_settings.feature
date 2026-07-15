# Trace:
# PRD Section: §9.1 Settings surface
# Requirement ID: ACCT-9.1
# User Story: As an end user, I want one place to change my profile fields, locale, notification preferences, tier, and billing.
# Acceptance Criteria: Change name, email (no re-verification), password; change Locale; manage Notification preferences; view tier/billing date/credit balance (Tier 2 only); initiate Tier change; delete account.

@account @individual
Feature: Account settings surface

  Background:
    Given a signed-in individual user "alice@example.test" on tier "tier_2"

  Rule: A user can change name, email, and password from settings

    @happy
    Scenario: Change display name
      When alice updates her display name to "Alice Cooper"
      Then alice's display name is "Alice Cooper"

    @happy
    Scenario: Change email without re-verification
      When alice updates her email to "alice.new@example.test"
      Then alice's account email is "alice.new@example.test"
      And no re-verification email is dispatched
      And the account remains email_verified=false (or unchanged from prior state)

    @happy
    Scenario: Change password
      Given alice's current password is "Str0ng-Pass!1"
      When alice updates her password to "Strong3r-Pass!2" with the current password "Str0ng-Pass!1"
      Then alice's password is updated
      And subsequent sign-in with "Strong3r-Pass!2" succeeds

    @negative
    Scenario: Change password fails when the current password is incorrect
      When alice attempts to update her password to "Strong3r-Pass!2" with the current password "wrong"
      Then the update is rejected with reason "current_password_invalid"
      And alice's password is unchanged

  Rule: A user can change Locale from settings

    @happy @locale
    Scenario Outline: Change account Locale
      When alice updates her Locale to "<locale>"
      Then alice's Locale is "<locale>"

      Examples:
        | locale |
        | en     |
        | hi     |
        | gu     |
        | mr     |

  Rule: A user can manage Notification preferences from settings

    @happy
    Scenario: Update a Source preference
      When alice sets source "GST" to enabled=false
      Then alice's preference for "GST" is enabled=false

  Rule: Tier, billing date, and credit balance are visible (credit balance Tier 2 only)

    @happy
    Scenario: Tier 2 user sees tier, billing date, and credit balance
      Given alice's billing anniversary is "2026-06-01T00:00:00Z" and her credit balance is 150
      When alice opens account settings
      Then the surface shows tier "tier_2"
      And next billing date "2026-06-01"
      And credit balance "150"

    @tier1
    Scenario: Tier 1 user sees tier and billing date but no credit balance
      Given a Tier 1 user "bea@example.test" with billing anniversary "2026-06-15T00:00:00Z"
      When bea opens account settings
      Then the surface shows tier "tier_1"
      And next billing date "2026-06-15"
      And no credit-balance field is rendered

    @free
    Scenario: Free user sees tier but no billing date or credit balance
      Given a Free user "fred@example.test"
      When fred opens account settings
      Then the surface shows tier "free"
      And no billing-date field is rendered
      And no credit-balance field is rendered

  Rule: Tier change is initiated from settings

    @state-transition @payments
    Scenario: A user can reach the Tier change surface from settings
      When alice opens account settings
      Then a "Change plan" affordance is rendered linking to the Tier change surface

  Rule: Account deletion is initiated from settings

    @state-transition
    Scenario: A user can reach Account deletion from settings
      When alice opens account settings
      Then a "Delete account" affordance is rendered
