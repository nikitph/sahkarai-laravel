# Trace:
# PRD Section: §5.4 Locale display and fallback (default Locale + switcher half)
# Requirement ID: INTERP-5.4-DISPLAY
# User Story: As a Tier 1/Tier 2 user, I want the Interpretation to default to my preferred Locale and to let me switch on demand.
# Acceptance Criteria: Default Locale is the user's preferred Locale (set at signup, editable in settings); a Locale switcher allows on-demand switching among the four Locales.

@interpretation @locale @tier1 @tier2
Feature: Locale default and switching on the Interpretation surface

  Rule: The user's preferred Locale is the default when opening an Interpretation

    @happy
    Scenario Outline: Default Locale matches the user's account Locale
      Given a Tier 1 user "bea@example.test" with account Locale "<locale>"
      And a Document Version "DV-1" with Interpretation blocks present for all of [en, hi, gu, mr]
      When bea opens "DV-1"
      Then the rendered plain-language summary is the "<locale>" block

      Examples:
        | locale |
        | en     |
        | hi     |
        | gu     |
        | mr     |

  Rule: A Locale switcher offers exactly the four supported Locales

    Scenario: The switcher offers en, hi, gu, mr
      Given a Tier 1 user "bea@example.test" viewing an Interpretation
      Then the Locale switcher offers exactly ["en", "hi", "gu", "mr"]

  Rule: Switching Locale renders the chosen block when present

    @happy @state-transition
    Scenario: Switching from en to hi renders the hi block
      Given a Tier 1 user "bea@example.test" with account Locale "en" viewing "DV-1" whose Interpretation has all four blocks
      When bea selects Locale "hi" in the switcher
      Then the rendered plain-language summary is the "hi" block

  Rule: Switching Locale does not change the user's stored account Locale

    Scenario: On-demand Locale switch does not persist
      Given a Tier 1 user "bea@example.test" with account Locale "en"
      When bea selects Locale "hi" in the switcher on "DV-1"
      Then the rendered plain-language summary is the "hi" block
      And bea's account Locale remains "en"
