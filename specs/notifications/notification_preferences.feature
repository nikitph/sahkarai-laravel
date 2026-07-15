# Trace:
# PRD Section: §7.4 User preferences; §7.5 Out of scope for v1
# Requirement ID: NOTIF-7.4
# User Story: As a Tier 1/Tier 2 user, I want to enable/disable Sources and choose an email cadence; in-app is always real-time.
# Acceptance Criteria: Per Source: enabled/disabled (default enabled at signup); email cadence one of [immediate, daily_digest, weekly_digest] default daily_digest; in-app real-time only; locale of delivery defaults to user account Locale; no per-tag, per-document-type, or "follow this document" filtering in v1.

@notifications @tier1 @tier2
Feature: Notification preferences
  Tier 1 and Tier 2 users configure Notification preferences per Source. In-app is real-time;
  email has a cadence. There is no per-tag, per-document-type, or watchlist filter in v1.

  Background:
    Given a Tier 1 user "bea@example.test"

  Rule: Defaults at signup are enabled=true and email_cadence="daily_digest" for every Source

    @happy
    Scenario Outline: New account defaults per Source
      Given a fresh signup that just produced a Tier 1 account "newt1@example.test"
      Then the Notification preference for source "<source>" is enabled=true with email_cadence="daily_digest"

      Examples:
        | source |
        | RBI    |
        | IT     |
        | GST    |

  Rule: A user can update enabled and email_cadence per Source

    @happy
    Scenario Outline: Update preferences for a Source
      When bea sets source "RBI" to enabled=<enabled> and email_cadence "<cadence>"
      Then bea's preference for "RBI" is enabled=<enabled> with email_cadence="<cadence>"

      Examples:
        | enabled | cadence       |
        | true    | immediate     |
        | true    | daily_digest  |
        | true    | weekly_digest |
        | false   | daily_digest  |

  Rule: email_cadence must be one of the fixed values

    @negative @boundary
    Scenario: An unknown email_cadence is rejected
      When bea attempts to set source "RBI" email_cadence "hourly"
      Then the update is rejected with reason "email_cadence_invalid"
      And bea's preference is unchanged

  Rule: In-app cadence is always real-time and not configurable

    @negative
    Scenario: No in-app cadence setting is offered
      When bea opens her Notification preferences
      Then no per-source in-app cadence selector is rendered

  Rule: Locale of delivery defaults to the user's account Locale

    @locale
    Scenario Outline: Email Notification is delivered in the user's account Locale
      Given bea's account Locale is "<locale>"
      And bea has email_cadence "immediate" for source "RBI"
      And a Document Version is ingested for "RBI" with a published Interpretation
      When the email Notification for bea is dispatched
      Then the email body is rendered in Locale "<locale>"

      Examples:
        | locale |
        | en     |
        | hi     |
        | gu     |
        | mr     |

  Rule: Per-tag, per-document-type, and watchlist filtering are not exposed in v1

    @negative
    Scenario Outline: Out-of-scope filtering options are absent from preferences
      When bea opens her Notification preferences
      Then no UI control labelled with any of ["<label>"] is rendered

      Examples:
        | label                       |
        | PACS-relevant only          |
        | Per document type           |
        | Follow this document        |
        | Watchlist                   |
