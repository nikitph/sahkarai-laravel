# Trace:
# PRD Section: §5.4 Locale fallback rule
# Requirement ID: INTERP-5.4-FALLBACK
# User Story: As a Tier 1/Tier 2 user, when my preferred Locale is unavailable I want to see the English version with a banner explaining the substitution.
# Acceptance Criteria: If a non-English Locale's block is missing, show English with a banner: "This interpretation is not available in `<Locale>`. Showing the English version." English is the fallback. If English is also missing, the user sees the §5.6 terminal-failure message.

@interpretation @locale
Feature: Locale fallback to English when a non-English block is missing
  English is the canonical fallback. A banner explicitly states the substitution.

  Rule: When a non-English block is missing, English is shown with a substitution banner

    @state-transition
    Scenario Outline: Non-English Locale fallback to English with banner
      Given a Tier 1 user "bea@example.test" with account Locale "<locale>"
      And a Document Version "DV-1" whose Interpretation is missing the "<locale>" block but has the "en" block
      When bea opens "DV-1"
      Then the rendered plain-language summary is the "en" block
      And a banner reads exactly: "This interpretation is not available in <locale>. Showing the English version."

      Examples:
        | locale |
        | hi     |
        | gu     |
        | mr     |

  Rule: English is never used as fallback when the user's preferred Locale is English

    Scenario: An English-preferring user sees no fallback banner when only English exists
      Given a Tier 1 user "bea@example.test" with account Locale "en"
      And a Document Version "DV-1" whose Interpretation has only the "en" block
      When bea opens "DV-1"
      Then the rendered plain-language summary is the "en" block
      And no Locale-fallback banner is rendered

  Rule: When English is also missing, the user sees the terminal-failure message

    @negative
    Scenario: All Locale blocks missing yields the §5.6 terminal message
      Given a Tier 1 user "bea@example.test" with account Locale "hi"
      And a Document Version "DV-1" with interpretation_status "interpretation_failed"
      When bea opens "DV-1"
      Then the page renders exactly the message "Interpretation not available for this document."
      And no fallback banner is rendered
