# Trace:
# PRD Section: §2.2 Tiers — Tier 1
# Requirement ID: TIER-2.2-T1
# User Story: As a Tier 1 user, I want everything Free has plus Interpretations, exports, and Notifications.
# Acceptance Criteria: Tier 1 adds view/export Interpretations in any Locale; receive Notifications; configure Notification preferences; may not Chat; may upgrade to Tier 2.

@auth @tier1 @individual
Feature: Tier 1 capabilities
  Tier 1 adds Interpretations (view + export), Notifications, and preference configuration on
  top of Free. It does not grant Chat.

  Background:
    Given a signed-in individual user "bea@example.test" on tier "tier_1"

  Rule: Tier 1 can view Interpretations in any supported Locale

    @happy @locale
    Scenario Outline: Tier 1 user reads an Interpretation in each Locale
      Given a Document Version "RBI-CIRC-2026-001" with Interpretation blocks present for all of [en, hi, gu, mr]
      When bea opens "RBI-CIRC-2026-001" with Locale "<locale>"
      Then the "<locale>" plain-language summary is rendered

      Examples:
        | locale |
        | en     |
        | hi     |
        | gu     |
        | mr     |

  Rule: Tier 1 can export Interpretations

    @happy
    Scenario Outline: Tier 1 user exports an Interpretation
      Given a Document Version "RBI-CIRC-2026-001" with a published Interpretation
      When bea exports the Interpretation as "<format>"
      Then a file in format "<format>" is produced

      Examples:
        | format |
        | pdf    |
        | md     |

  Rule: Tier 1 receives Notifications for newly published Interpretations after their subscription start

    @happy @notifications
    Scenario: Tier 1 user receives an in-app + email Notification for a new Interpretation
      Given bea's subscription started at "2026-05-01T00:00:00Z"
      And a Document Version "RBI-CIRC-2026-010" is ingested at "2026-05-15T09:00:00Z"
      And its Interpretation is successfully published at "2026-05-15T09:05:00Z"
      When the Notification dispatcher runs
      Then an in-app Notification is created for bea referencing "RBI-CIRC-2026-010"
      And an email Notification dispatch is recorded for bea according to her configured cadence

  Rule: Tier 1 can configure Notification preferences

    @happy
    Scenario: Tier 1 user updates Notification preferences
      When bea sets source "RBI" to enabled=true with email_cadence "weekly_digest"
      Then bea's preference for "RBI" is enabled=true and email_cadence="weekly_digest"

  Rule: Tier 1 cannot use Chat

    @negative @chat
    Scenario: Tier 1 user does not see "Start chat with this document"
      Given a Document Version "RBI-CIRC-2026-001" with a published Interpretation
      When bea opens "RBI-CIRC-2026-001"
      Then no "Start chat with this document" button is rendered

  Rule: Tier 1 can upgrade to Tier 2 at any time, prorated

    @state-transition @payments
    Scenario: Tier 1 user reaches the Tier 2 upgrade surface
      When bea opens the account billing surface
      Then the upgrade option "tier_2" is offered
      And the surface states the upgrade is prorated for the remainder of the current cycle
