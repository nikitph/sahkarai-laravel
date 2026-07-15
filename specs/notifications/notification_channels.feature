# Trace:
# PRD Section: §7.1 Channels
# Requirement ID: NOTIF-7.1
# User Story: As a Tier 1/Tier 2 user, I want both in-app and email Notifications.
# Acceptance Criteria: In-app notification centre with unread/read state; email channel as transactional email (provider TBD); no channel preference recorded.

@notifications @tier1 @tier2
Feature: Notification delivery channels
  Notifications are delivered in-app and via email. The in-app channel exposes a notification
  centre with unread/read state. The email provider is an implementation detail and no per-user
  channel toggle exists in v1.

  Rule: In-app notification centre exposes unread/read state

    @happy
    Scenario: A new Notification appears in the centre as unread
      Given a Tier 1 user "bea@example.test"
      And a new Notification "NOTIF-1" is created for bea
      When bea opens the notification centre
      Then "NOTIF-1" is listed with state "unread"

    @state-transition
    Scenario: Opening a Notification marks it read
      Given a Tier 1 user "bea@example.test"
      And an unread Notification "NOTIF-1" exists for bea
      When bea opens "NOTIF-1"
      Then "NOTIF-1" is in state "read"

    @state-transition
    Scenario: Mark-all-as-read changes state for every unread Notification
      Given a Tier 1 user "bea@example.test" with three unread Notifications and one read
      When bea selects "Mark all as read"
      Then the four Notifications are all in state "read"

  Rule: Every Notification dispatch is logged with channel, status, and timestamp

    @audit
    Scenario Outline: Each channel send is logged
      Given a Notification "NOTIF-1" for user bea targeting channel "<channel>"
      When the Notification dispatcher delivers "NOTIF-1"
      Then a delivery log entry is written with notification_id="NOTIF-1", channel="<channel>", status, and a server timestamp

      Examples:
        | channel |
        | in_app  |
        | email   |
