# Trace:
# PRD Section: §2.5 RLS (ingestion writes bullet)
# Requirement ID: RLS-2.5-INGEST
# User Story: As the platform, I want ingestion tables writable only by the service role.
# Acceptance Criteria: No end user can write to poll_runs, documents, document_versions, or interpretations.

@auth @rls @ingestion
Feature: Row-Level Security for ingestion tables
  Ingestion records are produced by the platform's service-role ingestion workers. No end-user
  principal — including saas_admin — may insert, update, or delete rows in those tables.

  Rule: Service-role only writes to poll_runs, documents, document_versions, interpretations

    @negative @rbac
    Scenario Outline: A non-service principal cannot write ingestion rows
      Given a signed-in user with role "<role>"
      When the user attempts to <op> a row in "<table>"
      Then the write is denied with reason "forbidden"

      Examples:
        | role              | op     | table              |
        | individual_member | insert | poll_runs          |
        | individual_member | update | poll_runs          |
        | individual_member | delete | poll_runs          |
        | individual_member | insert | documents          |
        | individual_member | update | documents          |
        | individual_member | delete | documents          |
        | individual_member | insert | document_versions  |
        | individual_member | update | document_versions  |
        | individual_member | delete | document_versions  |
        | individual_member | insert | interpretations    |
        | individual_member | update | interpretations    |
        | individual_member | delete | interpretations    |
        | saas_admin        | insert | poll_runs          |
        | saas_admin        | update | poll_runs          |
        | saas_admin        | delete | poll_runs          |
        | saas_admin        | insert | documents          |
        | saas_admin        | update | documents          |
        | saas_admin        | delete | documents          |
        | saas_admin        | insert | document_versions  |
        | saas_admin        | update | document_versions  |
        | saas_admin        | delete | document_versions  |
        | saas_admin        | insert | interpretations    |
        | saas_admin        | update | interpretations    |
        | saas_admin        | delete | interpretations    |

  Rule: saas_admin may read ingestion tables for ops visibility

    @admin @happy
    Scenario Outline: saas_admin can read ingestion tables
      Given a signed-in user with role "saas_admin"
      When the user reads "<table>"
      Then the read succeeds

      Examples:
        | table              |
        | poll_runs          |
        | documents          |
        | document_versions  |
        | interpretations    |
