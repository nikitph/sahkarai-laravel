# Trace:
# PRD Section: §9.2 Account deletion
# Requirement ID: ACCT-9.2
# User Story: As an end user, I want to delete my account with a 30-day grace window during which I can change my mind.
# Acceptance Criteria: User-initiated deletion produces a 30-day soft-delete window; signing back in within the window restores the account, all Chats and exports intact; at soft-delete time the Razorpay subscription is cancelled immediately and the user is not billed during the window; hard-delete (after 30 days) removes/anonymises personal data; issue reports the user filed are retained with anonymised user_id.

@account @individual @state-transition
Feature: Account deletion with soft-delete window

  Background:
    Given a signed-in individual user "alice@example.test" on tier "tier_2"
    And alice has a Chat "CHAT-001" with messages
    And alice has filed an issue report "ISSUE-A1"

  Rule: User-initiated deletion enters a 30-day soft-delete window

    @state-transition
    Scenario: Soft-delete is recorded with a 30-day window
      When alice deletes her account at "2026-05-15T10:00:00Z"
      Then alice's account is marked soft-deleted with deleted_at="2026-05-15T10:00:00Z"
      And the hard-delete deadline is recorded as "2026-06-14T10:00:00Z"

  Rule: Soft-delete cancels the Razorpay subscription immediately and stops billing during the window

    @payments
    Scenario: Razorpay subscription is cancelled on soft-delete
      Given alice has Razorpay subscription "rzp_sub_abc"
      When alice deletes her account at "2026-05-15T10:00:00Z"
      Then a Razorpay subscription-cancel request is issued for "rzp_sub_abc"
      And no Razorpay renewal charge is initiated for alice during the window 2026-05-15..2026-06-14

  Rule: Signing back in within the window restores the account with data intact

    @state-transition
    Scenario: Restore via sign-in within the window
      Given alice's account is soft-deleted at "2026-05-15T10:00:00Z"
      And the current time is "2026-05-30T10:00:00Z"
      When alice initiates account restoration through the restore flow with correct credentials
      Then alice's account is restored to active state
      And "CHAT-001" and its messages are intact
      And any exports alice produced before deletion remain intact
      # The exact surface that performs restoration vs. the standard sign-in surface is an OPEN QUESTION (OQ-ACCT-001).

  Rule: After 30 days the account is hard-deleted; personal data is removed or anonymised

    @state-transition @audit
    Scenario: Hard-delete removes or anonymises personal data
      Given alice's account is soft-deleted at "2026-05-15T10:00:00Z"
      And the current time is "2026-06-15T10:00:00Z"
      When the hard-delete job runs
      Then the user row is removed or anonymised
      And alice's Chats and Chat Messages are removed
      And alice's exports are removed
      And alice's Notification history is removed
      And alice's credit ledger entries are removed
      And the issue report "ISSUE-A1" is retained with the user_id anonymised

  Rule: A soft-deleted account cannot be billed or send Chat Messages

    @negative @payments
    Scenario: No renewal charge is initiated for a soft-deleted account
      Given alice's account is soft-deleted at "2026-05-15T10:00:00Z"
      When the Razorpay renewal scheduler considers alice's subscription
      Then no renewal charge is initiated

    @negative @chat
    Scenario: A soft-deleted account cannot send Chat Messages
      Given alice's account is soft-deleted
      When any request from alice attempts to send a Chat Message
      Then the request is denied with reason "account_pending_deletion"
