# Trace:
# PRD Section: §7.2 Triggers
# Requirement ID: NOTIF-7.2
# User Story: As a Tier 1/Tier 2 user, I want Notifications for new Interpretations published after my subscription started, and for revisions of Documents I've previously viewed.
# Acceptance Criteria: A Notification fires on (a) successful publication of an Interpretation for a Document Version ingested after the user's subscription start date, and (b) successful publication of an Interpretation for a revision that supersedes a Document Version the user has previously viewed.

@notifications
Feature: Notification trigger conditions
  Notifications fire on two specific events, scoped per user.

  Background:
    Given a Tier 1 user "bea@example.test" whose subscription started "2026-05-01T00:00:00Z"

  Rule: A new Interpretation publication after a user's subscription start triggers a Notification

    @happy @state-transition
    Scenario: A post-subscription-start Interpretation publish triggers a Notification
      Given a Document Version "DV-NEW" was ingested at "2026-05-15T09:00:00Z" with source "RBI"
      And bea has Notification preferences enabled for "RBI"
      When the Interpretation for "DV-NEW" is successfully published at "2026-05-15T09:05:00Z"
      Then a Notification is created for bea referencing "DV-NEW"

  Rule: A Document Version ingested before a user's subscription start does not trigger that user's Notification

    @negative @boundary
    Scenario: A pre-subscription-start Document Version does not trigger bea's Notification
      Given a Document Version "DV-OLD" was ingested at "2026-04-15T09:00:00Z"
      And bea has Notification preferences enabled for "RBI"
      When the Interpretation for "DV-OLD" is successfully published at "2026-04-15T09:05:00Z"
      Then no Notification is created for bea referencing "DV-OLD"

  Rule: A revision Interpretation publish triggers Notifications for users who previously viewed the prior version

    @state-transition
    Scenario: bea previously viewed DV-1; the DV-2 publication notifies her and references DV-1
      Given Document Version "DV-1" was previously viewed by bea
      And a revision Document Version "DV-2" supersedes "DV-1"
      And bea has Notification preferences enabled for the source of "DV-2"
      When the Interpretation for "DV-2" is successfully published
      Then a Notification is created for bea referencing "DV-2"
      And the Notification explicitly references "DV-1" as the prior version

    @negative
    Scenario: A revision does not notify users who never viewed the prior version
      Given Document Version "DV-1" was never viewed by Tier 1 user "ben@example.test"
      And ben has Notification preferences enabled for the source of "DV-2"
      When the Interpretation for "DV-2" is successfully published
      Then no Notification is created for ben referencing "DV-2" on the revision-trigger basis
      # Note: ben may still receive a Notification under the post-subscription-start trigger (Rule 1).
