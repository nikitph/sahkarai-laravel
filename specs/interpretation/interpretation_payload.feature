# Trace:
# PRD Section: §5.1 Per-Locale content blocks; §5.2 Structured metadata
# Requirement ID: INTERP-5.1, INTERP-5.2
# User Story: As a Tier 1/Tier 2 user, I want an Interpretation that gives me a plain-language summary, key takeaways, optional glossary, and structured metadata for each Document Version.
# Acceptance Criteria: Per Locale: 150–300 word summary, 3–7 key takeaways, optional glossary; Locale-independent metadata: applicability tags from a fixed enum, optional effective date, zero or more compliance deadlines, document_type from a fixed enum.

@interpretation @tier1 @tier2
Feature: Interpretation payload shape
  Each Interpretation is bound 1:1 to a Document Version. Its payload has per-Locale blocks
  (en/hi/gu/mr) and a Locale-independent structured-metadata block.

  Rule: Each Locale block has a 150–300 word plain-language summary

    @boundary
    Scenario Outline: Plain-language summary length is between 150 and 300 words
      Given a successfully generated Interpretation for Document Version "DV-1" in Locale "<locale>"
      When the Interpretation payload is read
      Then the "<locale>" plain-language summary word count is between 150 and 300 inclusive

      Examples:
        | locale |
        | en     |
        | hi     |
        | gu     |
        | mr     |

  Rule: Each Locale block has 3 to 7 key takeaways

    @boundary
    Scenario Outline: Key takeaways count is between 3 and 7
      Given a successfully generated Interpretation for Document Version "DV-1" in Locale "<locale>"
      When the Interpretation payload is read
      Then the "<locale>" key takeaways count is between 3 and 7 inclusive

      Examples:
        | locale |
        | en     |
        | hi     |
        | gu     |
        | mr     |

  Rule: Glossary is optional per Locale

    Scenario: Glossary may be present
      Given an Interpretation for Document Version "DV-1" where the English block has a glossary with two entries
      When the Interpretation payload is read
      Then the "en" glossary contains two entries each with non-empty term and definition

    Scenario: Glossary may be absent
      Given an Interpretation for Document Version "DV-1" where the English block has no glossary
      When the Interpretation payload is read
      Then the "en" glossary is absent or empty

  Rule: Applicability tags are drawn from a fixed enum

    @boundary
    Scenario Outline: Each applicability tag is one of the fixed values
      Given an Interpretation for Document Version "DV-1" with applicability_tags=[<tag>]
      Then "<tag>" is one of [pacs, ucb, dccb, stcb, apex, generic]

      Examples:
        | tag     |
        | pacs    |
        | ucb     |
        | dccb    |
        | stcb    |
        | apex    |
        | generic |

    Scenario: An Interpretation may have zero applicability tags
      Given an Interpretation for Document Version "DV-1" with applicability_tags=[]
      Then the Interpretation is still valid

  Rule: Effective date is optional and nullable

    @boundary
    Scenario: Effective date is present when the document provides one
      Given an Interpretation for Document Version "DV-1" with effective_date="2026-07-01"
      Then the Interpretation effective_date is "2026-07-01"

    Scenario: Effective date is null when not extractable
      Given an Interpretation for Document Version "DV-1" with no extractable effective date
      Then the Interpretation effective_date is null

  Rule: Compliance deadlines are zero or more {description, due_date} pairs

    @boundary
    Scenario: Zero compliance deadlines
      Given an Interpretation for Document Version "DV-1" with no compliance deadlines
      Then the Interpretation compliance_deadlines is []

    Scenario: Multiple compliance deadlines
      Given an Interpretation for Document Version "DV-1" with compliance_deadlines=[{"description":"File X","due_date":"2026-09-30"},{"description":"Submit Y","due_date":"2026-12-31"}]
      Then each compliance deadline has a non-empty description and a valid due_date

  Rule: document_type classification is one of the fixed values

    @boundary
    Scenario Outline: document_type is one of the fixed values
      Given an Interpretation for Document Version "DV-1" with document_type="<type>"
      Then "<type>" is one of [master_direction, circular, notification, press_release, faq, other]

      Examples:
        | type             |
        | master_direction |
        | circular         |
        | notification     |
        | press_release    |
        | faq              |
        | other            |
