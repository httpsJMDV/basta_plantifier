# Liam — Admin Part 2: moderation, maps, AI and audit

Updated October 24, 2026. Presentation: October 24, 2026.

## Implementation status — October 24, 2026

The owner took over both admin assignments and requested implementation in this checkout. Codex implemented both halves together; this is not a claim that King or Liam authored these changes. The original division below remains the scope reference. There is no remaining dependency on a classmate handoff.

Implemented: a shared responsive admin layout; dashboard; searchable account directory and limited review; delivered, owner-only warning banners with acknowledgement; suspend/restore; report filters, scoped text/image evidence and decision history; public-location review; provider diagnostics and correction review; audit/security events; announcement preview/publish/clear; and the common AI maintenance switch. Exactly one protected Super Admin and MFA remain enforced. No production admin credentials were created.

Shared implementation contracts:

- UI: `frontend/src/components/AdminLayout.tsx`, `AdminUI.tsx`, `AdminReviews.tsx`, `AdminScreens.tsx`, and `frontend/src/adminTypes.ts`. Existing `/admin?section=...` routes remain, with `maps` added.
- Security: `AdminSecurity::confirm(Request, password)`; `SecurityAudit::write(actorId, action, subject, metadata)`. Audit metadata accepts bounded allowlisted fields and excludes confirmation credentials and private request bodies.
- New protected routes: `GET /admin/users/{user}`, `POST /admin/pins/{pin}`, and `GET /admin/reports/{report}/media`. Report images are available only through their reported item, never a general private-media browser.
- Normal users acknowledge their own notices through `POST /settings/warnings/{warning}/acknowledge`. Shared props return only that account's unread notices. Correction review feedback appears beside its owner's original Workspace result.
- Additive migrations: `2026_10_23_091509_complete_admin_operations` (warnings, decisions, comment visibility, correction review fields, usage diagnostics, backup records) and `2026_10_23_204147_ensure_avatar_media_foreign_key`. Both were applied without resetting the working database. A guard in the older avatar migration fixes its fresh-install reference to a not-yet-created media table; the additive repair supplies the deferred constraint. Existing records are preserved.
- Security events include `auth.failed`, `admin.mfa_failed`, `admin.confirmation_failed`, `security.throttled`, and `security.upload_rejected`. Admin decisions retain the existing `admin.user.*`, `admin.report.*`, `admin.correction.*`, and `admin.settings.updated` names; location actions use `admin.pin.hide/generalize`.

Backup operations are deliberately local and read-only in the admin UI. `plantifier:backup-status source|database|media --file=...` records an existing artifact outside the project, size and SHA-256; it does **not** claim a verified restore. Source ZIPs are inspected to reject environment/runtime/database content. `--failed` records a failed attempt.

`plantifier:verify-restore restore_check --manifest=... --media-root=...` reads a separate `plantifier_restore_*` database and isolated restored media. Configure `RESTORE_DB_DATABASE` and, if different, `RESTORE_DB_HOST/PORT/USERNAME/PASSWORD` locally. Its JSON manifest contains `counts` for `users, conversations, chat_messages, plant_records, media` and `media_sha256` keyed by media ID. Expected values must come from the backup snapshot. Verification checks those counts, ownership relationships and restored media hashes; it never imports over the working database. This is a bounded rehearsal check, not a claim that every application workflow has been restored successfully.

Verified in this checkout: production frontend build passed; the full backend suite passed **122 tests / 1,096 assertions** with the opt-in MySQL case skipped in that run. The separate isolated MySQL acceptance passed **1 test / 8 assertions**, including overlapping singleton inserts, protected membership/deletion and rejected self-suspension. **Three admin browser workflows passed**, covering all seven sections at desktop/tablet/mobile widths, actual MFA and one-use recovery re-verification, delivered notices, report-scoped evidence, decisions, public-pin generalization, correction feedback and announcement publishing/clearing. Provider calls in tests were faked; no paid requests were made. Backup/restore commands were exercised with temporary isolated fixtures, not the owner's production data.

Reproduce from `backend/`: `php vendor/phpunit/phpunit/phpunit`; opt in to the isolated MySQL check with `$env:ADMIN_MYSQL_ACCEPTANCE='1'` and run `tests/Feature/MySqlAdminConstraintsTest.php`. Browser verification uses `AdminQaSeeder --env=amaru-qa` and port 8025 only. Never run that seeder against the working database.

**Owner operations still pending:** choosing/creating or signing in to the real Super Admin, completing its authenticator enrollment, reviewing the UI, and performing a production backup/restore rehearsal. No live provider credit/quota checks or external notifications were sent. Provider balances remain unavailable; unrecorded backup status remains unrecorded. QA admin accounts, media and credentials are restricted to the isolated QA database.


**Original build order: King, then Liam.** The owner subsequently took over both scopes. The implementation above supersedes the classmate handoff schedule; the original requirements below remain the acceptance reference.

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

- [x] Reuse the existing pending reports created by `POST /reports`. The supported targets are `post`, `comment`, `story`, `pin`, `user`, `message`. Identification requests use the post target and existing post kind; do not create a conflicting report type for the same item.
- [x] Add server-side search/filter/pagination by supported type, status and date, with total/filtered counts labeled. Preserve selection/query state through review and show appropriate empty/loading/error states.
- [x] Provide a clear report review with reporter/target identifiers where permitted, reason, timestamps, current status, accessible evidence and previous decisions. Show missing/deleted/expired content honestly.
- [x] Evidence opening must remain report-scoped and audited, with allowlisted fields. Reported-message review never returns the rest of its thread; pin evidence never returns exact private coordinates; account evidence never includes credentials or private collections.
- [x] If image evidence is necessary, implement a narrowly authorized, report-scoped media path. Do not make all private uploads public or bypass `MediaController` for convenience. Keep evidence access and retention bounded and reviewable.
- [x] Agree report reason categories/new fields with Amaru. His modal sends reports; your queue consumes them. Do not create a duplicate reports table or a second inbox that misses existing rows.
- [x] Support `pending`, `reviewed`, `resolved`, `dismissed`, with a valid transition policy, recorded explanation and actual target-specific action. “Reviewed” does not falsely imply content was removed.
- [x] Reuse King's sensitive-action confirmation. Perform content change, decision and audit consistently; reject invalid action/target combinations and handle stale/double submissions without duplicate or contradictory decisions.
- [x] Moderate posts, comments, stories and identification requests through their actual visibility/lifecycle rules. Coordinate comment/reply deletion or hiding with Amaru so restricted parent content does not reappear through previews or replies.
- [x] User-account enforcement goes through King's warning/suspend/restore workflow. Do not invent a second account-action implementation in a report modal.
- [x] For a reported direct message, keep review/decision limited to that item. Do not silently add unrestricted message deletion, thread browsing, or impersonation. Any new enforcement action needs an agreed, narrow contract.

**Acceptance:** submit each supported report type through the user flow, find/filter it, open only its evidence, make a valid decision and see the correct resulting visibility/status and audit event. Test unrelated private messages/media, deleted targets, expired stories, wrong confirmation, duplicate decisions and unauthorized API calls.

## 5. L2 — Public-map review

- [x] Add a public-map review section integrated with King's layout. If using query routing, add allowlisted `section=maps` and a working navigation entry; do not leave a dead link.
- [x] List/search/filter public discoveries and reported pins by supported name/area/status/visibility/sensitivity fields. Keep results bounded and paginate or cluster as needed. Do not include private pins in global counts, map bounds or autocomplete.
- [x] Admin review of a reported private pin, if allowed by the report workflow, is a separate limited evidence view; it is not a general private-map browser and must omit exact private coordinates.
- [x] Reuse the app's existing coordinate-generalization rules for approximate/sensitive public locations in maps, tables, previews and any evidence responses. Do not show raw coordinates in hidden props or tooltips just because the rendered marker is approximate.
- [x] Support a reasoned, reauthenticated decision to remove a public pin from public visibility or generalize a public pin. Preserve owner access, private notes and the stored personal record; do not hard-delete location history to hide it publicly.
- [x] A sensitive pin cannot be made publicly exact through the admin UI or API. Generalizing it must take effect in public map/search/share payloads and other relevant cached views.
- [x] Surface reports of misleading/duplicate locations without declaring the admin's visual guess a verified botanical identification. Link the review/decision history to the report and audit trail.

**Acceptance:** exact public, approximate public, sensitive and private fixture pins produce the permitted views. Hide/generalize a reported public pin and verify public maps, cards and shares stop exposing the original precise location; an unrelated user's private coordinates never reach admin/public payloads.

## 6. L3 — AI management and correction review

- [x] Display safe readiness/configuration summaries for **Pl@ntNet identification**, **Groq text generation** and **optional Claude quota fallback** separately. No raw keys or editable `.env` content.
- [x] Preserve the current architecture: Pl@ntNet owns species/family/candidates/scores; Groq writes explanations based on that context; Claude is an optional quota fallback, not a second visual species guesser.
- [x] Improve usage/error views with bounded date/provider/model/status filters and pagination/aggregates. Show real request outcomes and returned token counts with explicit time windows. If adding fields, migrate older rows safely and label unknown historical provider values.
- [x] Pl@ntNet usage is not a generative token count. Do not invent token-to-credit conversions, monetary cost, remaining quota or model availability. Show quota/credit information only when supported by a current verified response, with a timestamp and unavailable state.
- [x] Capture safe diagnostics for quota, timeout, connection, authentication and malformed-response failures without logging credential-bearing URLs, prompts, private photos or whole private chats. Coordinate any instrumentation of Amaru's note-assistance endpoint.
- [x] Consume King's existing `ai_paused` setting and its confirmed settings action. Do not create another maintenance switch or let notes bypass it. Provider model/key configuration remains server environment configuration.
- [x] Keep readiness checks distinct from generation checks. A listed model does not prove this account has credits. Actual provider calls can consume quota/cost; use mocks in automated tests and agree any live checks with the integration lead.
- [x] Provide a paginated correction queue showing the saved original suggestion, user's proposed name/reason, timestamps and status. Add review notes/actor/time via additive fields if needed; validate and audit decisions.
- [x] Never rewrite original AI messages, cached Pl@ntNet results, identification provenance or users' saved plant names when closing a correction. A resolved review is not automatic expert certification. Coordinate user-visible feedback/status with Amaru.

**Acceptance:** test successful generation, ambiguous/no-match identification, provider failure and quota fallback with isolated HTTP fakes. Confirm the actual explanation provider is retained, historical data remains readable, pauses work, secrets stay out of responses/logs, and correction review leaves the original result unchanged.

## 7. L4 — Audit/Security Center and backup status

- [x] Use King's shared audit writer and agreed event names. Add a readable, paginated audit view with safe actor/action/target/date filters, clear timestamps and bounded metadata.
- [x] Record admin decisions, reported-item access and sensitive user/settings operations consistently. Keep existing audit records; do not add a normal “clear audit history” button.
- [x] Add missing security-event collection for failed logins, important throttling events and rejected uploads through appropriate shared hooks. Coordinate normal-user authentication/upload middleware changes with King and Amaru.
- [x] Use a safe metadata allowlist: event type, target identifiers where permitted, response/category codes and timestamps. Never store passwords, reset tokens, TOTP/recovery material, API keys, complete notes/chats, rejected-file contents or exact private GPS coordinates. Avoid duplicate events across middleware/controllers.
- [x] Represent actors honestly: an unauthenticated failure may have no user ID. Do not create a dummy actor or attach a failed-login email to an unrelated account. Bound any network/account identifiers needed for investigation and agree retention.
- [x] Implement a truthful backup-status source and connect it to King's dashboard slot. Distinguish **source ZIP**, **database**, **private media**, and **restore verification**; “source ZIP created” cannot mean the whole system can be restored.
- [x] Record when a backup/rehearsal was attempted, whether it completed, and when a restore was successfully verified. Unknown, stale, failure and not-yet-tested states must be visible. Do not fabricate status from an arbitrary timestamp or file name.
- [ ] Plan a protected backup/restore procedure with the integration lead. Use an isolated destination for restore rehearsal and verify ownership/media/chat relationships. Do not restore over the working database, run destructive web-triggered restores, or place private dumps/uploads inside the Git repository.
- [x] Prefer a safe, read-only admin status surface and a controlled local operation/status update over a web shell. Never construct arbitrary commands, expose backup contents for casual download, reveal credentials or server paths, or back up `.env` into a shared source ZIP.
- [x] When adding durable warning/security/backup records, coordinate schemas with King's warning delivery and audit foundation. They serve different purposes; an audit row alone is not a user notification or proof of a usable backup.

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
- [x] Reports submitted through Amaru's modal reach your queue and a permitted admin decision is visible on the correct target. Neither side bypasses ownership/privacy to make the demo work.
- [x] King's dashboard/users/settings/MFA still work; his delivered warning flow is not replaced by audit-only behavior.
- [x] Correction and AI monitoring changes preserve existing hybrid identification, note assistance, quota handling and saved chat history.
- [x] Sensitive map/private-media boundaries are verified in actual response payloads, not only screenshots.
- [x] Audit/security collection is useful without capturing secrets; backup states reflect real operations and isolated restore evidence.
- [x] Shared layout, filters and dialogs work on desktop, tablet, mobile, keyboard and reduced-motion settings. Keep readable fonts; avoid clipped action buttons or unreachable dialog controls.
- [x] Provide changed files, additive migrations, safe configuration names, focused/full regression results and reproduction steps. Disclose missing SMTP, Reverb, provider credits or backup verification rather than marking them complete.
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
