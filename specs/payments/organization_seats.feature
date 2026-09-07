# Trace:
# Product decision: Organization bulk seats (2026-09-07)
# Requirement ID: PAY-ORG-SEATS
# User Story: As an organization owner, I can buy discounted seats and administer access for my team.

@payments @org @rbac @state-transition
Feature: Organization subscriptions and seat administration

  Rule: Organizations buy one paid tier for 2–25 seats

    @happy @boundary
    Scenario Outline: A predefined bulk discount is applied to every seat
      Given an organization owner selects paid tier "tier_2"
      When the owner buys <seats> seats
      Then Razorpay receives subscription quantity <seats>
      And the configured <discount>% offer is applied per seat
      And the owner reserves one of the seats
      And access follows the configured local-approval or signed-provider mode

      Examples:
        | seats | discount |
        | 2     | 5        |
        | 6     | 10       |
        | 11    | 15       |
        | 16    | 20       |
        | 21    | 25       |
        | 25    | 25       |

    @negative @boundary
    Scenario Outline: Invalid seat quantities are rejected
      When an organization owner attempts to buy <seats> seats
      Then checkout is rejected

      Examples:
        | seats |
        | 1     |
        | 26    |

  Rule: Seat access follows the configured approval mode

    @webhook @idempotency
    Scenario: Activation and renewal apply per-seat entitlements once
      Given an organization has a pending Tier 2 subscription
      When Razorpay sends signed activation and renewal events
      Then active seat holders receive Tier 2 access
      And each active seat holder receives 200 monthly credits
      And redelivery of the same event does not grant credits twice

    @happy
    Scenario: Local approval bypasses provider checkout
      Given organization billing is enabled in local-approval mode
      When an owner confirms an organization purchase
      Then no Razorpay subscription is created
      And the organization subscription is active immediately
      And the owner seat and tier credits are activated immediately

  Rule: Owners and admins administer seats within purchased capacity

    @happy @rbac
    Scenario: A pending invitation reserves a seat
      Given an organization has one unassigned seat
      When an owner or admin invites a member
      Then the invitation reserves that seat
      And another invitation cannot exceed purchased capacity

    @happy @rbac
    Scenario: Accepting an invitation activates access
      Given a user signs in with the exact invited email
      When the user accepts an unexpired invitation
      Then membership and seat access become active
      And the user receives the organization's tier and per-seat credits

    @happy @rbac
    Scenario: Removing a member releases the seat
      When an owner or admin removes a non-owner member
      Then the membership and seat assignment are removed
      And organization-paid access is revoked

    @negative @rls
    Scenario: A manager cannot administer another organization
      When a manager targets a member or invitation from another organization
      Then the request is denied without revealing that tenant's data

  Rule: Individual subscription transitions are separate work

    @negative
    Scenario: An existing paid individual account cannot start or accept organization billing
      Given a user has a paid individual subscription
      When the user attempts to start or accept an organization subscription
      Then the request is rejected without changing either subscription
