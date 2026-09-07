# Architecture

## Product flow

The platform regulatory pipeline is:

`scheduled poll / manifest import → source adapter → acquire immutable original → native extraction → Kimi fallback when needed → AI interpretation → publish English → retry remaining locales → notify eligible users`

Native and Kimi extraction attempts are auditable rows. Terminal failures retain their immutable originals in an operator-only `needs_review` state. English is the publication threshold; missing supported translations remain a visible `partial` interpretation with English fallback and three total attempts per locale. A failed newer revision cannot displace the latest previously published revision.

When the explainer-video feature flag is enabled, published archive interpretations also enter the explainer pipeline:

`published interpretation → validated Lesson IR → video queue → narration-first HyperFrames render → immutable MP4 + provenance manifest`

The private upload pipeline reuses the same downstream jobs:

`Tier 2 / Tier 3 / admin upload → Laravel content-based PDF validation → PDF parser preflight → store private immutable original → extract text → AI interpretation → publish to owner`

The user path is:

`authenticated route → policy → controller → action / query service → transactional write → queued side effect → Inertia response`

Controllers validate and shape HTTP responses. Stateful domain transitions live in actions or jobs. External provider calls live behind AI agents, source adapters, or the billing gateway.

## Ownership and authorization

Product data is user-owned. Chats, messages, individual subscriptions, credit ledger rows, notifications, preferences, and views are reached through their owning user or protected by policies. SaaS admins receive operations metadata but cannot impersonate users, start chats, or read chat bodies.

Organization subscriptions activate the initializer's tenant foundation. An organization selects one paid tier for 2–25 seats; the owner consumes the first seat, and pending invitations reserve capacity. Owners manage billing, while owners and admins may invite, change roles, cancel invitations, and remove non-owner members. `TenantContext`, tenant model scopes, policies, and cross-tenant denial tests protect organization seat data. Chats and private documents remain owned by each user rather than becoming visible to organization administrators. Individual-to-organization subscription transitions are deliberately not implemented.

Polled regulatory documents, their versions, poll runs, and ingestion alerts are platform-owned. Tier 2, Tier 3, and SaaS admin users may also create private, user-owned documents from readable PDFs up to 5 MB. Private documents, extracted text, interpretations, downloads, exports, and document-grounded chats are visible only to the uploader. Owner deletion cascades database records and removes stored original/extracted artifacts; permanent account purge does the same. Private publications never enter regulatory notification fan-out.

Tier 2 and Tier 3 users can view explainer videos. Public archive versions queue them automatically after a published or partial interpretation. A private upload requires an explicit owner request and one idempotent ten-credit debit; terminal rendering failure refunds that debit. Video readiness is orthogonal to interpretation and chat readiness.

## Data invariants

- `(source, source_document_id)` identifies a regulatory document.
- `uploaded_by_user_id = null` identifies the public platform corpus; a non-null owner makes the entire document aggregate private.
- Original bytes are stored once at a canonical path and identified by SHA-256.
- Each changed byte sequence creates a new immutable `document_version`; revisions link with `supersedes_id`.
- A document version has at most one interpretation row. Locale prose is generated independently with three attempts per locale; validated English makes the version publishable while other locales may remain partial. Applicability, effective date, document type, and deadlines are stored once as locale-independent metadata.
- Every version begins with native extraction. Only platform-owned PDFs fall back to Kimi; private uploads never cross that provider boundary. Every attempt records method, provider, outcome, timestamps, and safe provider metadata.
- A document version has at most one canonical explainer video. Its MP4 and build manifest share one content-addressed storage prefix, and rendering state never changes chat availability.
- A chat is permanently bound to one user and one document version.
- Chat messages and credit-ledger entries are append-only. A user message and its one-credit debit happen atomically and idempotently.
- Razorpay events are signature-verified and deduplicated before state transitions.
- In-app/email delivery attempts are recorded independently.
- New in-app notifications broadcast after commit only on the owning user's authenticated Reverb private channel; Echo refreshes the open centre, updates the unread badge and shows an application-wide toast. Public socket coordinates are shared at request time so the immutable frontend image is environment-independent.

## AI boundary

`RegulatoryInterpretationAgent` returns structured output with a 150–300 word summary, 3–7 takeaways, optional glossary, deadlines, fixed applicability tags, effective date, and document type. `GenerateLocaleInterpretation` validates provider output before persistence.

`RegulatoryChatAgent` receives only the bound document version and that chat's immutable history. `ChatStreamController` streams Laravel AI SDK SSE output and persists the assistant response after completion. Context-limit checks and credit debits happen before the provider call. A disconnected or failed stream can resume with the same request ID without a second debit; per-chat row locks serialize context and completion writes.

## Billing boundary

Razorpay is authoritative for activation and renewal. Local checkout requests never grant access. Signed lifecycle webhooks activate tiers, reset monthly chat credits, apply prorated upgrades into a chat-enabled tier, and record failures. Tier 3 inherits chat and adds a distinct personalized-chat entitlement; the personalization settings contract is intentionally left to its own product specification. Downgrades are queued until the paid-period anniversary. A daily reconciliation job records drift and alerts ops.

Organization billing uses the same paid plan IDs with Razorpay subscription `quantity` and a configured offer for the selected discount band: 2–5 seats receive 5%, 6–10 receive 10%, 11–15 receive 15%, 16–20 receive 20%, and 21–25 receive 25%. Prices and discounts are snapshotted locally in integer paise/basis points, but signed provider events remain authoritative for access. Tier 2 and Tier 3 cycle credits are granted independently to every active seat holder with per-user idempotency keys. The feature is disabled unless `ORGANIZATION_BILLING_ENABLED` and all provider offer IDs needed by checkout are configured.

## Runtime topology

The lean immutable application image runs four roles with different commands:

- web: FrankenPHP/Inertia requests and streaming responses
- worker: ingestion, extraction, AI, mail, billing, and cleanup jobs
- scheduler: polls, digests, pending transitions, reconciliation, and purges
- reverb: WebSocket transport

A separate immutable video-worker image runs Laravel's `video` queue with Node 22, Chromium, FFmpeg and the pinned HyperFrames runtime. Laravel remains the control plane and invokes the renderer through `ExplainerVideoGenerator`; the normal web and queue images intentionally contain no media toolchain. The video storage disk and prefix are independently configurable. `EXPLAINER_VIDEO_ENABLED` gates automatic queueing, private generation requests, playback, and archive UI exposure; it is off while the production renderer is suspended.

PostgreSQL stores application state; Redis backs queues/cache. The frozen multi-arch runtime is `ghcr.io/nikitph/laravel-runtime:1.0.0`. Classic FrankenPHP mode is deliberate until concurrent request-state isolation is separately proven.

## Deployment boundary

Production infrastructure is declared with OpenTofu, host configuration is
converged with Ansible, and Kamal deploys the immutable runtime roles. A
verified merge to `main` is the application deployment trigger.
Infrastructure changes pass through a separately protected GitHub Environment
before apply. `/up` is process liveness; `/ready` verifies PostgreSQL,
Redis/cache, and the configured regulatory storage disk. See
`docs/DEPLOYMENT-AUTOMATION.md` for provisioning, recovery, required secrets,
and Laravel repository onboarding.
