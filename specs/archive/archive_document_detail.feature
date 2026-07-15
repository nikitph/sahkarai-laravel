# Trace:
# PRD Section: §6.3 Document Version detail page
# Requirement ID: ARCH-6.3
# User Story: As a signed-in user, I want a Document Version detail page that adapts to my tier.
# Acceptance Criteria: All users see title/source/source_url/published_date/document_type/Download original; Tier 1+ see full Interpretation, Locale switcher, Report Issue; Tier 2 sees Start chat; revisions show a Versions panel.

@archive @individual
Feature: Document Version detail page rendering by tier

  Background:
    Given a Document Version "RBI-CIRC-2026-001" with:
      | field               | value                                       |
      | title               | Capital Adequacy Update                     |
      | source              | RBI                                         |
      | source_url          | https://rbi.org.in/notifications/example    |
      | published_date      | 2026-05-15                                  |
      | document_type       | circular                                    |
      | interpretation      | published                                   |

  Rule: All authenticated users see core metadata and a Download original button

    @free @happy
    Scenario: Free user sees core metadata and Download original
      Given a signed-in individual user "fred@example.test" on tier "free"
      When fred opens "RBI-CIRC-2026-001"
      Then the page shows title "Capital Adequacy Update"
      And source "RBI"
      And source URL "https://rbi.org.in/notifications/example" rendered as an outbound attribution link
      And published date "2026-05-15"
      And document type "circular"
      And a "Download original" button

  Rule: Tier 1 sees the full Interpretation and Report Issue affordance

    @tier1 @happy
    Scenario: Tier 1 user sees Interpretation, Locale switcher, and Report Issue
      Given a signed-in individual user "bea@example.test" on tier "tier_1"
      When bea opens "RBI-CIRC-2026-001"
      Then the Interpretation block is rendered
      And a Locale switcher offering ["en", "hi", "gu", "mr"] is rendered
      And a "Report issue" button is rendered

  Rule: Tier 2 sees the "Start chat with this document" button

    @tier2 @happy
    Scenario: Tier 2 user sees "Start chat with this document"
      Given a signed-in individual user "cara@example.test" on tier "tier_2"
      When cara opens "RBI-CIRC-2026-001"
      Then a "Start chat with this document" button is rendered

    @tier1 @negative
    Scenario: Tier 1 user does not see "Start chat with this document"
      Given a signed-in individual user "bea@example.test" on tier "tier_1"
      When bea opens "RBI-CIRC-2026-001"
      Then no "Start chat with this document" button is rendered

  Rule: When the Document has revisions, a Versions panel lists all versions

    Scenario: Versions panel renders for a Document with multiple versions
      Given Document "RBI-CIRC-2026-001" has versions ["DV-1" (2026-05-10), "DV-2" (2026-05-15)]
      And a Tier 1 user "bea@example.test"
      When bea opens "DV-2"
      Then a "Versions" panel lists both ["DV-1", "DV-2"] with their dates and links

    Scenario: Versions panel is absent for a single-version Document
      Given Document "RBI-CIRC-2026-009" has only version "DV-9"
      And a Tier 1 user "bea@example.test"
      When bea opens "DV-9"
      Then no "Versions" panel is rendered
