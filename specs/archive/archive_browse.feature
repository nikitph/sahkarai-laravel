# Trace:
# PRD Section: §6.1 Browse
# Requirement ID: ARCH-6.1
# User Story: As a signed-in user, I want to browse the Archive with filters and sort, defaulting to the latest version of each Document.
# Acceptance Criteria: Paginated list of Document Versions (latest per Document by default); filters: Source, Document Type, Date range (date of issue), Applicability tag; sort: published date (default desc), title.

@archive @individual
Feature: Browse the Archive
  Browsing the Archive returns a paginated list of Document Versions. Latest version per
  Document is shown by default. Filters and sort are explicit.

  Rule: Browse returns the latest Document Version per Document by default

    @happy
    Scenario: Latest-version-only browse
      Given Documents and versions:
        | document            | version | published_date |
        | RBI-CIRC-2026-001   | DV-1    | 2026-05-10     |
        | RBI-CIRC-2026-001   | DV-2    | 2026-05-15     |
        | IT-NOTIF-2026-007   | DV-3    | 2026-04-09     |
      And a signed-in Free user "fred@example.test"
      When fred opens the Archive browse surface with no filters
      Then the list contains exactly the rows referencing ["DV-2", "DV-3"]

  Rule: Default sort is published date descending

    @happy
    Scenario: Default sort orders newest first
      Given Documents and latest versions sorted by published_date:
        | latest_version | published_date |
        | DV-2           | 2026-05-15     |
        | DV-3           | 2026-04-09     |
      When fred opens the Archive with no sort selected
      Then the rows appear in order ["DV-2", "DV-3"]

  Rule: Browse can be sorted by title

    Scenario: Sort by title ascending
      Given latest Document Versions with titles:
        | latest_version | title                           |
        | DV-A           | Liquidity Coverage Ratio review |
        | DV-B           | Capital Adequacy Update         |
      When fred opens the Archive sorted by title ascending
      Then the rows appear in order ["DV-B", "DV-A"]

  Rule: Browse supports filter by Source

    @happy
    Scenario Outline: Filter by Source restricts results
      Given latest Document Versions across sources:
        | latest_version | source |
        | DV-RBI         | RBI    |
        | DV-IT          | IT     |
        | DV-GST         | GST    |
      When fred opens the Archive filtered by source "<source>"
      Then only the rows for source "<source>" are returned

      Examples:
        | source |
        | RBI    |
        | IT     |
        | GST    |

  Rule: Browse supports filter by Document Type

    Scenario: Filter by document_type returns matching rows only
      Given latest Document Versions with document_types ["circular", "notification", "circular"]
      When fred filters by document_type "circular"
      Then only the two "circular" rows are returned

  Rule: Browse supports filter by published-date range

    @boundary
    Scenario: Date range filter is inclusive of bounds
      Given latest Document Versions with published_dates ["2026-05-09", "2026-05-10", "2026-05-15", "2026-05-16"]
      When fred filters by date range from "2026-05-10" to "2026-05-15"
      Then the returned rows are exactly those published on "2026-05-10" and "2026-05-15"

  Rule: Browse supports filter by applicability tag

    Scenario: Filter by applicability tag returns rows whose Interpretation carries that tag
      Given latest Document Versions:
        | latest_version | applicability_tags  |
        | DV-A           | ["pacs"]            |
        | DV-B           | ["ucb", "dccb"]     |
        | DV-C           | ["generic"]         |
      When fred filters by applicability tag "pacs"
      Then the returned rows are exactly ["DV-A"]

  Rule: Browse is paginated and pagination boundaries are stable

    @boundary
    Scenario: Empty Archive returns an empty page-1 result
      Given the Archive contains zero latest Document Versions
      When fred opens the Archive browse surface
      Then the page is empty with total_count=0
      And the empty-state message is rendered

    @boundary
    Scenario Outline: Pagination returns the requested page size
      Given the Archive contains 47 latest Document Versions
      When fred opens the Archive page <page> with page_size <size>
      Then the response contains <count> rows
      And total_count is 47

      Examples:
        | page | size | count |
        | 1    | 20   | 20    |
        | 2    | 20   | 20    |
        | 3    | 20   | 7     |
        | 4    | 20   | 0     |

  Rule: Anonymous visitors cannot browse the Archive

    @visitor @negative @rbac
    Scenario: Unauthenticated browse is redirected to sign-in
      Given an unauthenticated visitor
      When the visitor opens the Archive browse surface
      Then the visitor is redirected to the sign-in surface
