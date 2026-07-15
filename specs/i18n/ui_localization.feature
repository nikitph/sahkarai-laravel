# Trace:
# PRD Section: §11 Internationalisation (UI half)
# Requirement ID: I18N-11-UI
# User Story: As an end user, I want the entire app UI in my locale from day one without on-the-fly machine translation.
# Acceptance Criteria: Entire app UI ships in en/hi/gu/mr from day one; native i18n via locale-prefixed routes or locale cookie; all UI strings in translation files; no on-the-fly machine translation of chrome; Document originals never translated.

@i18n @locale
Feature: Application UI localisation

  Rule: The UI ships in all four Locales from day one

    @happy
    Scenario Outline: Sign-in surface renders in the chosen Locale
      Given the visitor's Locale is "<locale>"
      When the visitor opens the sign-in surface
      Then the rendered UI strings are the translations for "<locale>"
      And no string falls back to a non-"<locale>" Locale silently

      Examples:
        | locale |
        | en     |
        | hi     |
        | gu     |
        | mr     |

  Rule: UI strings come from translation files; no on-the-fly machine translation of chrome

    Scenario: Translation source is a translation file, not a live-translation API
      Given the platform's translation source for UI strings is configured
      Then the source is a static translation-file bundle per Locale
      And no runtime call is made to a machine-translation service to render UI chrome

  Rule: A signed-in user's Locale change is applied to subsequent UI renders

    @state-transition
    Scenario Outline: Locale change in settings applies to subsequent renders
      Given a signed-in user with current Locale "en"
      When the user updates their Locale to "<locale>"
      And the user navigates to any UI surface
      Then the rendered UI strings are the translations for "<locale>"

      Examples:
        | locale |
        | hi     |
        | gu     |
        | mr     |

  Rule: Document originals are never translated by the application

    @negative
    Scenario: The Document download served is the original bytes, not a translation
      Given a Document Version "DV-1" whose original is in English
      And a signed-in user with Locale "hi"
      When the user clicks "Download original"
      Then the bytes served are the original English file
      And no translated copy is generated or substituted
