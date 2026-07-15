# Coverage Gaps

Areas where the PRD does not provide enough behaviour to scenarioise. These are distinct from
OPEN_QUESTIONS — those are answerable in conversation; these are entire surface areas the PRD
does not cover.

| Gap | Missing Information | Suggested Clarification |
|-----|---------------------|--------------------------|
| Anonymous visitor surfaces | The PRD's Tier matrix denies all gated capabilities to visitors, but does not enumerate which UI routes a visitor *can* see (e.g. marketing/landing pages). | Document the visitor-visible routes (landing, pricing, about) so we can write the matching scenarios. |
| Email content shape | Each Notification's email body is mentioned but no template is specified. Subject line? Body fields? CTA links? | Provide v1 email templates (in en/hi/gu/mr) before launch. |
| In-app notification copy | The exact copy for in-app notifications on (a) new Interpretation, (b) revision, (c) failed-renewal, (d) downgrade, is unspecified. | Lock copy with Product. |
| Account-deletion confirmation flow | The PRD says user-initiated deletion, but not the friction (typed confirmation? checkbox? second factor?). | Decide how strong the confirm should be. |
| Sign-out destination | After sign-out, where is the user taken? Landing page? Sign-in page? | Define so scenarios assert. |
| Razorpay checkout failure UX | The PRD says access activates on payment success. The user experience on payment failure (error page, retry CTA, ledger event) is unspecified. | Decide and add to §10.3. |
| Razorpay UI strings/branding rules | Razorpay's checkout opens in a hosted modal/redirect — what UI control or fallback do we render if Razorpay JS fails to load? | Out of v1 scope or wire a stub message. |
| Search relevance tuning | The PRD specifies the source columns and an English-over-vernacular bias, but not relevance weights (title vs. summary vs. takeaways). | Define weighting policy. |
| Performance SLAs | §12.4 explicitly says best-effort, no SLA. We've authored no latency assertions. This is intentional. | None — keep explicit. |
| Accessibility requirements | The PRD does not specify WCAG level or keyboard-navigation requirements. | If required, add an accessibility section to the PRD. |
| Browser support matrix | Not specified in the PRD. | Lock supported browsers/versions. |
| Mobile responsiveness expectations | Not specified beyond "no native mobile apps". | Define minimum responsive breakpoints. |
| Rate limiting | The PRD does not define rate limits on sign-in, password reset, search, or chat. | Decide rate-limit policy — at minimum for sign-in and reset, to prevent abuse. |
| Abuse / fair-use on Chat | The PRD enforces credits as the abuse mechanism for Chat. Are there caps beyond credits (e.g. concurrent chats, total chats per user)? | If yes, add to §8. |
| File-size limits on Document originals | Extraction may behave differently for very large PDFs. The PRD does not state a max file size at acquisition. | Define a soft cap so acquisition can reject. |
| Hosting and security boundaries | §12.1 and §12.2 describe hosting/security but produce no user-observable behaviour beyond what's already covered by RLS and webhook tests. Intentionally not scenarioised. | None — keep explicit. |
| Manual refund mechanics | §10.8 says "manual via support" but does not specify how support reflects a refund in the system (only the credit-ledger adjustment is implied). | Define the operational SOP — including whether it produces an in-app notification. |
| Issue report lifecycle SLA | §5.5 says "no SLA in v1". This means we cannot author latency/triage scenarios. | None — explicit; preserve as a constraint. |
| Org and TOTP UX when they launch | Out of v1 scope. | Plan separately. |
| Audit log retention | §12.3 says Chat Messages are immutable once written. The PRD does not specify how long ledger or notification logs are retained. | Define retention policy (especially given hard-delete in §9.2). |
| Document-version detail "Last updated" copy | Not specified for revisions. | Lock copy. |
| Failed-renewal email/in-app copy | §10.5 says the user is notified at each retry and at downgrade but does not lock copy. | Lock copy and language per Locale. |
| Edge case: A user whose preferred Locale is changed *after* a notification has been queued but *before* it has been delivered | Delivery Locale may already be locked, or recomputed. The PRD does not say. | Decide. |
