# Trace:
# PRD Section: §2.1 Roles (RBAC)
# Requirement ID: ROLE-2.1-INDIV
# User Story: As an end user, I want to operate as an individual_member account that holds my own subscription, credits, and chats — not as a "personal org of one".
# Acceptance Criteria: individual_member is the default role on signup; an individual is never modelled as an Org member; there is no conversion path between individual and Org accounts.

@auth @individual
Feature: individual_member is the default end-user identity
  v1 distinguishes individual_member from org_member as distinct identities, never the same
  identity modelled differently. There is no UI path that converts one to the other.

  Rule: Signup creates an individual_member account

    @happy
    Scenario: A fresh signup yields role=individual_member
      Given no account exists for "newuser@example.test"
      When the user submits the signup form with email "newuser@example.test" and password "Str0ng-Pass!1"
      Then the account role is "individual_member"
      And the account is not a member of any Organisation

  Rule: An individual_member is not modelled as a member of any Organisation

    Scenario: A new individual_member has no Organisation membership row
      Given an individual user "alice@example.test"
      Then no row in the organisation-membership store has user_id=alice

  Rule: There is no conversion path between individual and Org accounts

    @negative
    Scenario Outline: A would-be "convert to organisation" route does not render
      Given a signed-in individual user "alice@example.test" on tier "tier_2"
      When the user navigates to "<route>"
      Then the route is not registered in the v1 UI
      And the user is shown the 404 surface

      Examples:
        | route                              |
        | /account/convert-to-organisation   |
        | /account/upgrade-to-org            |
        | /organisations/new                 |

  Rule: An individual_member owns their own subscription, credits, and chats

    Scenario: Subscription, credits, and chats all reference owner_type='user'
      Given an individual user "alice@example.test" on tier "tier_2"
      And alice has subscription "SUB-1", credit ledger entries, and chat "CHAT-001"
      Then alice's subscription row has owner_type "user" and owner_id alice
      And every alice credit-ledger row has owner_type "user" and owner_id alice
      And chat "CHAT-001" has owner_type "user" and owner_id alice
