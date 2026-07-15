# Trace:
# PRD Section: §2.5 RLS (subscription / credit ledger bullet)
# Requirement ID: RLS-2.5-SUB
# User Story: As an end user, I want my subscription record and credit ledger to be private to me.
# Acceptance Criteria: A user can read their own subscription and credit ledger; nothing else.

@auth @rls @payments
Feature: Row-Level Security for subscriptions and credit ledger
  Subscription rows and credit-ledger rows are private to their owner. No user can read another
  user's subscription or ledger. saas_admin can read aggregated metadata but cannot read another
  user's full ledger detail unless §2.4 ops needs require it (PRD does not state staff read access
  to private ledger rows; this is captured as OQ-RLS-001).

  Background:
    Given an individual user "alice@example.test" on tier "tier_2"
    And an individual user "bob@example.test" on tier "tier_2"

  Rule: A user reads only their own subscription

    @happy
    Scenario: Alice reads her own subscription
      When alice requests her subscription
      Then the response contains alice's subscription record

    @negative @rbac
    Scenario: Alice cannot read Bob's subscription
      When alice requests bob's subscription
      Then the request is denied with reason "forbidden"

  Rule: A user reads only their own credit ledger

    @happy
    Scenario: Alice reads her own credit ledger
      When alice requests her credit ledger
      Then the response contains only ledger entries with owner_id=alice

    @negative @rbac
    Scenario: Alice cannot read Bob's credit ledger
      When alice requests bob's credit ledger
      Then the request is denied with reason "forbidden"

  Rule: No user can write to subscriptions or credit_ledger directly

    @negative @rbac
    Scenario Outline: Direct writes to billing tables are denied
      Given a signed-in user with role "<role>"
      When the user attempts to <op> a row in "<table>"
      Then the write is denied with reason "forbidden"

      Examples:
        | role              | op     | table          |
        | individual_member | insert | subscriptions  |
        | individual_member | update | subscriptions  |
        | individual_member | delete | subscriptions  |
        | individual_member | insert | credit_ledger  |
        | individual_member | update | credit_ledger  |
        | individual_member | delete | credit_ledger  |
        | saas_admin        | update | subscriptions  |
        | saas_admin        | update | credit_ledger  |
