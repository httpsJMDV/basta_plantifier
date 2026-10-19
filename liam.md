# Liam — Admin Part 2: moderation, maps, AI and audit

Updated October 19, 2026. Presentation: October 24, 2026.

**Build order: King first, then Liam.** Read [king.md](king.md), especially its contracts and completion gate. Use the reviewed foundation he hands over. This file assigns your four admin areas; it does not mean those areas are already complete. Amaru's non-admin scope is in [amaru.md](amaru.md).

## 1. Your half and the dependency boundary

| Blueprint area | Your ownership |
| --- | --- |
| 03 Moderation | Report filters, narrowly scoped evidence, target-specific decisions and report status/reason history. |
| 04 Public-map review | Public/reported location review, sensitive places, visibility removal and generalization. |
| 05 AI management | Pl@ntNet/Groq/Claude readiness and usage/error visibility; original-result-preserving correction review. |
| 06 Audit/Security Center | Audit search/filter/pagination; safe failure/throttle/upload-event collection; backup and restore-verification status. |

King owns admin access/MFA, the shared layout and dashboard, user actions/warnings, and platform settings/announcements. You consume his audit writer, security guard, layout and existing `ai_paused` setting. Do not build competing versions or expand the one-Super-Admin rule into two accounts.

You can read the code and plan tests while King works. Wait for his reviewed handoff before changing dependent shared components, controllers, routes or schema. When the contracts change, update both handoffs and coordinate with the integration lead.

## 2. Existing code: extend it

- `backend/app/Http/Controllers/AdminController.php`: `evidence`, `resolve`, `correction`, section queries and the initial audit helper. King may extract shared functionality; use the paths/interfaces in his actual handoff.
- `frontend/src/pages/Admin.tsx`: initial reports, AI/corrections, audit tables and modals. Integrate your sections into King's shared admin layout rather than replacing the app shell.
- `backend/app/Http/Controllers/ReportController.php`: user report creation, supported target types and narrow access checks. Amaru owns its user-facing modal/validation integration.
- `backend/app/Http/Controllers/MapController.php`, `backend/app/Http/Requests/PinRequest.php`, `backend/app/Services/Sharing.php`, `backend/app/Http/Controllers/MediaController.php`: map visibility, controlled shares and media access.
- `backend/app/Services/PlantNetIdentifier.php`, `HybridBotanicalProvider.php`, `GroqProvider.php`, `ClaudeProvider.php`, `backend/config/botanical.php`: identification, generative text and quota fallback. Do not replace the hybrid workflow.
- `backend/app/Console/Commands/CheckProvider.php`: existing safe readiness-check command; a model-access check does not prove generation credits or quota availability.
- `backend/database/migrations/2026_10_19_000001_create_botanical_domain.php`: `reports`, `corrections`, `ai_usage`, `audit_events`, `system_settings` and other domain tables.
- `backend/tests/Feature/SocialSecurityTest.php` and `HybridIdentificationTest.php`: existing narrow-evidence, admin-protection and hybrid workflow regression coverage.
- `scripts/Backup-Source.ps1`: source-only ZIP backup. This is **not** a MySQL/private-media backup or evidence of a successful restore.

Important current gaps: the report queue has limited filtering; evidence is a small text projection; correction review updates status only; AI usage captures basic model/status/tokens; backup readiness is only dashboard copy. Inspect King's delivered code before assuming any gap still exists.

## 3. Rules for all your modules

- Keep exactly one Super Admin, the shared backend authorization/MFA guard, CSRF protection, throttles, validation and sensitive-action confirmation. Every new mutation must enforce these checks independently of the page UI.
- Preserve accounts, conversations, original identifications, saved records and audit history. Add migrations and upgrade existing data; do not reset the working database or seed fake activity into it.
- Do not add unrestricted admin access to private Messenger threads, notes, exact pins, or unrelated uploads. A report authorizes review of that item under the existing evidence workflow, not exploration of everything owned by the reported user.
- API keys, OAuth/mail/database passwords, raw tokens, TOTP seeds and recovery codes never appear in admin props, evidence, audit logs, error strings or exported status information.
- Keep the botanical theme and shared fonts. Make tables/filters/modals accessible and readable on desktop and mobile; no decorative analytics, fake percentages or success claims without evidence.
- Amaru owns the public app and user-facing reporting. King owns the admin foundation. Coordinate shared files, route names, field names, statuses and event contracts; do not duplicate their work.
- No commit, push, merge, deployment, notification to external parties or production credential creation is requested by this handoff.

## 4. L1 — Moderation queue and report decisions

- [ ] Reuse the existing pending reports created by `POST /reports`. The supported targets are `post`, `comment`, `story`, `pin`, `user`, `message`. Identification requests use the post target and existing post kind; do not create a conflicting report type for the same item.
- [ ] Add server-side search/filter/pagination by supported type, status and date, with total/filtered counts labeled. Preserve selection/query state through review and show appropriate empty/loading/error states.
- [ ] Provide a clear report review with reporter/target identifiers where permitted, reason, timestamps, current status, accessible evidence and previous decisions. Show missing/deleted/expired content honestly.
- [ ] Evidence opening must remain report-scoped and audited, with allowlisted fields. Reported-message review never returns the rest of its thread; pin evidence never returns exact private coordinates; account evidence never includes credentials or private collections.
- [ ] If image evidence is necessary, implement a narrowly authorized, report-scoped media path. Do not make all private uploads public or bypass `MediaController` for convenience. Keep evidence access and retention bounded and reviewable.
- [ ] Agree report reason categories/new fields with Amaru. His modal sends reports; your queue consumes them. Do not create a duplicate reports table or a second inbox that misses existing rows.
- [ ] Support `pending`, `reviewed`, `resolved`, `dismissed`, with a valid transition policy, recorded explanation and actual target-specific action. “Reviewed” does not falsely imply content was removed.
- [ ] Reuse King's sensitive-action confirmation. Perform content change, decision and audit consistently; reject invalid action/target combinations and handle stale/double submissions without duplicate or contradictory decisions.
- [ ] Moderate posts, comments, stories and identification requests through their actual visibility/lifecycle rules. Coordinate comment/reply deletion or hiding with Amaru so restricted parent content does not reappear through previews or replies.
- [ ] User-account enforcement goes through King's warning/suspend/restore workflow. Do not invent a second account-action implementation in a report modal.
- [ ] For a reported direct message, keep review/decision limited to that item. Do not silently add unrestricted message deletion, thread browsing, or impersonation. Any new enforcement action needs an agreed, narrow contract.

**Acceptance:** submit each supported report type through the user flow, find/filter it, open only its evidence, make a valid decision and see the correct resulting visibility/status and audit event. Test unrelated private messages/media, deleted targets, expired stories, wrong confirmation, duplicate decisions and unauthorized API calls.

## 5. L2 — Public-map review

- [ ] Add a public-map review section integrated with King's layout. If using query routing, add allowlisted `section=maps` and a working navigation entry; do not leave a dead link.
- [ ] List/search/filter public discoveries and reported pins by supported name/area/status/visibility/sensitivity fields. Keep results bounded and paginate or cluster as needed. Do not include private pins in global counts, map bounds or autocomplete.
- [ ] Admin review of a reported private pin, if allowed by the report workflow, is a separate limited evidence view; it is not a general private-map browser and must omit exact private coordinates.
- [ ] Reuse the app's existing coordinate-generalization rules for approximate/sensitive public locations in maps, tables, previews and any evidence responses. Do not show raw coordinates in hidden props or tooltips just because the rendered marker is approximate.
- [ ] Support a reasoned, reauthenticated decision to remove a public pin from public visibility or generalize a public pin. Preserve owner access, private notes and the stored personal record; do not hard-delete location history to hide it publicly.
- [ ] A sensitive pin cannot be made publicly exact through the admin UI or API. Generalizing it must take effect in public map/search/share payloads and other relevant cached views.
- [ ] Surface reports of misleading/duplicate locations without declaring the admin's visual guess a verified botanical identification. Link the review/decision history to the report and audit trail.

**Acceptance:** exact public, approximate public, sensitive and private fixture pins produce the permitted views. Hide/generalize a reported public pin and verify public maps, cards and shares stop exposing the original precise location; an unrelated user's private coordinates never reach admin/public payloads.

## 6. L3 — AI management and correction review

- [ ] Display safe readiness/configuration summaries for **Pl@ntNet identification**, **Groq text generation** and **optional Claude quota fallback** separately. No raw keys or editable `.env` content.
- [ ] Preserve the current architecture: Pl@ntNet owns species/family/candidates/scores; Groq writes explanations based on that context; Claude is an optional quota fallback, not a second visual species guesser.
- [ ] Improve usage/error views with bounded date/provider/model/status filters and pagination/aggregates. Show real request outcomes and returned token counts with explicit time windows. If adding fields, migrate older rows safely and label unknown historical provider values.
- [ ] Pl@ntNet usage is not a generative token count. Do not invent token-to-credit conversions, monetary cost, remaining quota or model availability. Show quota/credit information only when supported by a current verified response, with a timestamp and unavailable state.
- [ ] Capture safe diagnostics for quota, timeout, connection, authentication and malformed-response failures without logging credential-bearing URLs, prompts, private photos or whole private chats. Coordinate any instrumentation of Amaru's note-assistance endpoint.
- [ ] Consume King's existing `ai_paused` setting and its confirmed settings action. Do not create another maintenance switch or let notes bypass it. Provider model/key configuration remains server environment configuration.
- [ ] Keep readiness checks distinct from generation checks. A listed model does not prove this account has credits. Actual provider calls can consume quota/cost; use mocks in automated tests and agree any live checks with the integration lead.
- [ ] Provide a paginated correction queue showing the saved original suggestion, user's proposed name/reason, timestamps and status. Add review notes/actor/time via additive fields if needed; validate and audit decisions.
- [ ] Never rewrite original AI messages, cached Pl@ntNet results, identification provenance or users' saved plant names when closing a correction. A resolved review is not automatic expert certification. Coordinate user-visible feedback/status with Amaru.

**Acceptance:** test successful generation, ambiguous/no-match identification, provider failure and quota fallback with isolated HTTP fakes. Confirm the actual explanation provider is retained, historical data remains readable, pauses work, secrets stay out of responses/logs, and correction review leaves the original result unchanged.

## 7. L4 — Audit/Security Center and backup status

- [ ] Use King's shared audit writer and agreed event names. Add a readable, paginated audit view with safe actor/action/target/date filters, clear timestamps and bounded metadata.
- [ ] Record admin decisions, reported-item access and sensitive user/settings operations consistently. Keep existing audit records; do not add a normal “clear audit history” button.
- [ ] Add missing security-event collection for failed logins, important throttling events and rejected uploads through appropriate shared hooks. Coordinate normal-user authentication/upload middleware changes with King and Amaru.
- [ ] Use a safe metadata allowlist: event type, target identifiers where permitted, response/category codes and timestamps. Never store passwords, reset tokens, TOTP/recovery material, API keys, complete notes/chats, rejected-file contents or exact private GPS coordinates. Avoid duplicate events across middleware/controllers.
- [ ] Represent actors honestly: an unauthenticated failure may have no user ID. Do not create a dummy actor or attach a failed-login email to an unrelated account. Bound any network/account identifiers needed for investigation and agree retention.
- [ ] Implement a truthful backup-status source and connect it to King's dashboard slot. Distinguish **source ZIP**, **database**, **private media**, and **restore verification**; “source ZIP created” cannot mean the whole system can be restored.
- [ ] Record when a backup/rehearsal was attempted, whether it completed, and when a restore was successfully verified. Unknown, stale, failure and not-yet-tested states must be visible. Do not fabricate status from an arbitrary timestamp or file name.
- [ ] Plan a protected backup/restore procedure with the integration lead. Use an isolated destination for restore rehearsal and verify ownership/media/chat relationships. Do not restore over the working database, run destructive web-triggered restores, or place private dumps/uploads inside the Git repository.
- [ ] Prefer a safe, read-only admin status surface and a controlled local operation/status update over a web shell. Never construct arbitrary commands, expose backup contents for casual download, reveal credentials or server paths, or back up `.env` into a shared source ZIP.
- [ ] When adding durable warning/security/backup records, coordinate schemas with King's warning delivery and audit foundation. They serve different purposes; an audit row alone is not a user notification or proof of a usable backup.

**Acceptance:** verify an admin action, evidence view, failed login, throttled request and invalid upload produce the intended safe records exactly once. Check filters, missing actors, no secrets, and denial for normal users. A backup is not marked restore-verified until an isolated restore and data/media checks actually pass.

## 8. Handoff contracts and file ownership

Before implementation, receive King's actual changed-file list and confirm:

1. The shared admin layout/components and supported query routes/props.
2. The singleton/MFA/reauthentication rules and helper interface for sensitive actions.
3. The audit writer, field/redaction contract and event naming conventions.
4. User warning/suspend/restore and `announcement`/`ai_paused` behavior.
5. The report contract shared with Amaru: target types, reason, statuses, permission checks and any agreed added fields.
6. Migration order and current test fixtures. Your later migration must not collide with King's or Amaru's pending migrations.
7. The dashboard integration points for moderation counts, AI summaries and backup status.

Prefer focused section controllers/components over simultaneous edits to one giant `AdminController.php`/`Admin.tsx`, if King's refactor provides that structure. Do not move or rename shared files again without coordination. Add your routes inside the existing protected group and keep current URLs compatible unless an agreed migration says otherwise.

Admin notifications about incoming reports, if added, are your integration surface. Reuse the existing report storage and authorized channels; do not touch Amaru's private thread events to broadcast report details. Never send emails/messages to real people as a test without explicit authorization.

## 9. Verification and final integration gate

- [ ] L1–L4 meet the acceptance criteria above, with owner/non-owner and ordinary-user denial tests.
- [ ] Reports submitted through Amaru's modal reach your queue and a permitted admin decision is visible on the correct target. Neither side bypasses ownership/privacy to make the demo work.
- [ ] King's dashboard/users/settings/MFA still work; his delivered warning flow is not replaced by audit-only behavior.
- [ ] Correction and AI monitoring changes preserve existing hybrid identification, note assistance, quota handling and saved chat history.
- [ ] Sensitive map/private-media boundaries are verified in actual response payloads, not only screenshots.
- [ ] Audit/security collection is useful without capturing secrets; backup states reflect real operations and isolated restore evidence.
- [ ] Shared layout, filters and dialogs work on desktop, tablet, mobile, keyboard and reduced-motion settings. Keep readable fonts; avoid clipped action buttons or unreachable dialog controls.
- [ ] Provide changed files, additive migrations, safe configuration names, focused/full regression results and reproduction steps. Disclose missing SMTP, Reverb, provider credits or backup verification rather than marking them complete.
- [ ] Integration lead reviews both halves together. Record remaining defects clearly; do not mark unchecked tasks complete because the presentation date is close.

Useful existing checks, run from the indicated folders:

```powershell
# backend/ — focused existing regressions, plus your new relevant tests.
php -d extension=gd vendor/phpunit/phpunit/phpunit tests/Feature/SocialSecurityTest.php
php -d extension=gd vendor/phpunit/phpunit/phpunit tests/Feature/HybridIdentificationTest.php

# frontend/
npm.cmd run build
```

Use isolated test databases/accounts; configure browser tests for their QA server, not the working database. Include appropriate MySQL-specific checks after schema changes. Preserve backend repository instructions and run required formatting/checks. Keep real credentials, private evidence and database/media backups out of handoff files and screenshots.

This is the second half of the **same Admin System**. Finishing your modules does not introduce a second Super Admin account or transfer authority to browse unrestricted private data.
