# Trace:
# PRD Section: §6.2 Search
# Requirement ID: ARCH-6.2
# User Story: As a signed-in user, I want full-text search across Documents and their English Interpretation content, with phrase matching and a result snippet.
# Acceptance Criteria: Postgres full-text search over title, extracted text, English plain-language summary, English key takeaways; vernacular indexed but ranked behind English; keyword + phrase query syntax with quotes; no boolean operators in v1 UI; results return Document Version stubs with title, source, date, document type, applicability tags, snippet with highlight.

@archive @individual
Feature: Search the Archive
  Search runs over title, extracted text, and English Interpretation summary/takeaways.
  Vernacular content is indexed but ranked behind English in v1.

  Background:
    Given a signed-in individual user "fred@example.test" on tier "free"

  Rule: Keyword search returns Document Version stubs with the contract fields

    @happy
    Scenario: A keyword match returns a stub with required fields
      Given a Document "RBI-CIRC-2026-001" with title "Capital Adequacy Update" and applicability tags ["ucb"]
      When fred searches for "capital"
      Then one result is returned
      And the stub contains: title, source, published_date, document_type, applicability_tags, matched-field snippet
      And the snippet highlights "capital"

  Rule: Phrase search via double quotes matches the exact phrase

    Scenario: Quoted phrase matches when adjacent
      Given a Document "RBI-CIRC-2026-002" with extracted text including "...the capital adequacy ratio requirement..."
      When fred searches for "\"capital adequacy\""
      Then "RBI-CIRC-2026-002" is in the results

    Scenario: Quoted phrase does not match when words are not adjacent
      Given a Document "RBI-CIRC-2026-003" with extracted text including "...capital requirements and adequacy ratios..."
      When fred searches for "\"capital adequacy\""
      Then "RBI-CIRC-2026-003" is not in the results

  Rule: English content is ranked above vernacular content for matches in either

    Scenario: An English-summary match outranks a Hindi-summary match
      Given a Document "RBI-CIRC-2026-A" with the term "CRR" present only in its English summary
      And a Document "RBI-CIRC-2026-B" with the term "CRR" present only in its Hindi summary
      When fred searches for "CRR"
      Then "RBI-CIRC-2026-A" appears before "RBI-CIRC-2026-B" in the results

  Rule: Boolean operators are not part of the v1 query syntax

    @negative @boundary
    Scenario: AND/OR are treated as literal terms, not operators
      Given a Document "RBI-CIRC-2026-X" with extracted text containing "alpha" and "beta" but not "AND"
      When fred searches for "alpha AND beta"
      Then the results contain only Documents whose indexed text contains all of ["alpha", "and", "beta"] as terms
      And no special boolean parsing occurs

  Rule: Search returns an empty result set when nothing matches

    @boundary @happy
    Scenario: No-match query returns an empty result set
      Given no indexed Document contains "xyznotmatched"
      When fred searches for "xyznotmatched"
      Then the results list is empty
      And the empty-state message is rendered

  Rule: Search results return the latest Document Version per matching Document

    Scenario: Newer revision is returned, older is not
      Given Document "RBI-CIRC-2026-001" has DV-1 (published 2026-05-10) and DV-2 (published 2026-05-15) both containing "CRR"
      When fred searches for "CRR"
      Then the result row references "DV-2"
      And "DV-1" does not appear in the result list

  Rule: A Free user can run search; visitors cannot

    @visitor @negative @rbac
    Scenario: Unauthenticated search is redirected to sign-in
      Given an unauthenticated visitor
      When the visitor submits a search query
      Then the visitor is redirected to the sign-in surface
