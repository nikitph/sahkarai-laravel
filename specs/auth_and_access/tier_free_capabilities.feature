# Trace:
# PRD Section: §2.2 Tiers — Free
# Requirement ID: TIER-2.2-FREE
# User Story: As a Free user, I want to browse and search the Archive and view raw Documents, even though Interpretations and Chat are gated.
# Acceptance Criteria: Free users may browse, search, view raw Documents, view Document metadata; may not view Interpretations, receive Notifications, or use Chat; may upgrade.

@auth @free @individual
Feature: Free tier capabilities
  Free is the default tier on signup. It grants Archive access but not Interpretation or Chat.

  Background:
    Given a signed-in individual user "fred@example.test" on tier "free"

  Rule: Free can browse and search the Archive

    @happy
    Scenario: Free user opens the Archive browse surface
      When fred opens the Archive
      Then the browse surface renders a paginated list of Document Versions

    @happy
    Scenario: Free user executes a search
      Given the Archive contains a Document with title "Liquidity Coverage Ratio (LCR) revision"
      When fred searches for "Liquidity Coverage Ratio"
      Then the matching Document Version stub is returned

  Rule: Free can view raw Documents and their metadata

    @happy
    Scenario: Free user views a Document Version detail page
      Given a Document Version "RBI-CIRC-2026-001" with title "Capital Adequacy Update"
      When fred opens "RBI-CIRC-2026-001"
      Then the page shows: title, source, source URL, published date, document type
      And the page shows a "Download original" button

  Rule: Free cannot view Interpretations

    @negative @rbac
    Scenario: Free user does not see the Interpretation block on the detail page
      Given a Document Version "RBI-CIRC-2026-001" with a published Interpretation
      When fred opens "RBI-CIRC-2026-001"
      Then no Interpretation summary, takeaways, or glossary is rendered
      And a tier-upsell affordance is rendered pointing to the upgrade flow

  Rule: Free cannot receive Notifications

    @negative @notifications
    Scenario: No Notifications are dispatched to Free users
      Given a new Document Version is ingested with a successfully published Interpretation
      When the Notification dispatcher runs
      Then no Notification is created for fred

  Rule: Free cannot use Chat

    @negative @chat
    Scenario: Free user does not see "Start chat with this document"
      Given a Document Version "RBI-CIRC-2026-001" with a published Interpretation
      When fred opens "RBI-CIRC-2026-001"
      Then no "Start chat with this document" button is rendered

  Rule: Free can upgrade to Tier 1 or Tier 2 at any time

    @happy @state-transition
    Scenario: Free user can reach the upgrade surface
      When fred opens the account billing surface
      Then the upgrade options "tier_1" and "tier_2" are offered
