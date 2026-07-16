# Architecture

## Product flow

The regulatory pipeline is:

`scheduled poll → source adapter → acquire immutable original → extract text → AI interpretation → publish → notify eligible users`

The user path is:

`authenticated route → policy → controller → action / query service → transactional write → queued side effect → Inertia response`

Controllers validate and shape HTTP responses. Stateful domain transitions live in actions or jobs. External provider calls live behind AI agents, source adapters, or the billing gateway.

## Ownership and authorization

Product data is user-owned. Chats, messages, subscriptions, credit ledger rows, notifications, preferences, and views are reached through their owning user or protected by policies. SaaS admins receive operations metadata but cannot impersonate users, start chats, or read chat bodies.

The initializer's organization infrastructure remains installed but dormant for v1. No product route creates organizations or memberships. This implementation uses Laravel session authentication and application-enforced ownership rather than the specs' Supabase-token vocabulary. Optional passkeys and 2FA are retained as a baseline strengthening.

Regulatory documents, versions, interpretations, poll runs, and ingestion alerts are platform-owned. Browser users have no write routes for them. Only queued ingestion and interpretation code writes this corpus.

## Data invariants

- `(source, source_document_id)` identifies a regulatory document.
- Original bytes are stored once at a canonical path and identified by SHA-256.
- Each changed byte sequence creates a new immutable `document_version`; revisions link with `supersedes_id`.
- A document version has at most one interpretation row. Locale prose is generated independently with bounded retries; applicability, effective date, document type, and deadlines are stored once as locale-independent metadata.
- A chat is permanently bound to one user and one document version.
- Chat messages and credit-ledger entries are append-only. A user message and its one-credit debit happen atomically and idempotently.
- Razorpay events are signature-verified and deduplicated before state transitions.
- In-app/email delivery attempts are recorded independently.
- New in-app notifications broadcast after commit only on the owning user's authenticated Reverb private channel; Echo refreshes the open centre, updates the unread badge and shows an application-wide toast. Public socket coordinates are shared at request time so the immutable frontend image is environment-independent.

## AI boundary

`RegulatoryInterpretationAgent` returns structured output with a 150–300 word summary, 3–7 takeaways, optional glossary, deadlines, fixed applicability tags, effective date, and document type. `GenerateLocaleInterpretation` validates provider output before persistence.

`RegulatoryChatAgent` receives only the bound document version and that chat's immutable history. `ChatStreamController` streams Laravel AI SDK SSE output and persists the assistant response after completion. Context-limit checks and credit debits happen before the provider call. A disconnected or failed stream can resume with the same request ID without a second debit; per-chat row locks serialize context and completion writes.

## Billing boundary

Razorpay is authoritative for activation and renewal. Local checkout requests never grant access. Signed lifecycle webhooks activate tiers, reset monthly credits, apply prorated Tier 1→Tier 2 credits, and record failures. Downgrades are queued until the paid-period anniversary. A daily reconciliation job records drift and alerts ops.

## Runtime topology

One immutable image runs four roles with different commands:

- web: FrankenPHP/Inertia requests and streaming responses
- worker: ingestion, extraction, AI, mail, billing, and cleanup jobs
- scheduler: polls, digests, pending transitions, reconciliation, and purges
- reverb: WebSocket transport

PostgreSQL stores application state; Redis backs queues/cache. The frozen multi-arch runtime is `ghcr.io/nikitph/laravel-runtime:1.0.0`. Classic FrankenPHP mode is deliberate until concurrent request-state isolation is separately proven.
