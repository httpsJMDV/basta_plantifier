# King — Admin Part 1: foundation and account operations

Updated October 19, 2026. Presentation: October 24, 2026.

**Build order: King first, then Liam.** This is an implementation assignment, not completed work. Build your four areas below, validate them, and hand the shared foundation to Liam using the completion gate in this file. Liam's remaining four areas are in [liam.md](liam.md). Amaru owns the non-admin application in [amaru.md](amaru.md).

## 1. Division of the eight admin areas

| Blueprint area | Owner | Scope |
| --- | --- | --- |
| 08 Admin access, MFA and protected operations | **King — first** | Singleton protection, setup/recovery, TOTP/recovery codes, session freshness and the common reauthentication contract. |
| 01 Dashboard | **King — first** | Shared admin layout/navigation, real summary counts, recent events, usable loading/empty/error states. |
| 02 User management | **King — first** | Search, account review, warnings delivered to the user, suspend/restore, protected-account safeguards. |
| 07 Platform settings and announcements | **King — first** | Allowlisted settings, announcement publishing and the existing AI maintenance switch. |
| 03 Moderation | **Liam — second** | Report queue, evidence and decisions for permitted content and specific reported messages. |
| 04 Public-map review | **Liam — second** | Reported/public pins, sensitive places, hide/generalize operations. |
| 05 AI management | **Liam — second** | Provider/usage/error visibility and correction review, preserving the hybrid identification contract. |
| 06 Audit/Security Center | **Liam — second** | Audit views, failure/throttling/upload events and truthful backup/restore status. |

This is four blueprint areas each; it is not a promise of identical hours. King provides shared security/layout primitives, while Liam completes the review and monitoring workflows. Do not build a second admin application or assign another admin role to make the split easier.

## 2. Existing code to preserve

The admin area already has a working skeleton. Inspect it before editing:

| File | Existing behavior |
| --- | --- |
| `backend/app/Http/Controllers/AdminController.php` | Dashboard counts, user actions, report evidence/decisions, settings, corrections and MFA. |
| `frontend/src/pages/Admin.tsx` | Six query-selected sections, tables and action/evidence modals. It currently mixes all admin sections in one file. |
| `frontend/src/pages/AdminMfa.tsx` | Authenticator enrollment/verification and one-time display of recovery codes. |
| `backend/app/Http/Middleware/SuperAdmin.php` | Requires the singleton admin and fresh MFA before protected admin routes. |
| `backend/app/Console/Commands/CreateSuperAdmin.php` | Interactive `plantifier:admin` creation and same-account recovery. |
| `backend/routes/web.php` | Existing `/admin/mfa`, `/admin`, user/report/settings/correction routes. |
| `backend/app/Models/User.php` | Singleton lookup, protected/encrypted secret fields and hidden credentials. |
| `backend/database/migrations/2026_10_19_000001_create_botanical_domain.php` | `super_admin`, `reports`, `corrections`, `ai_usage`, `audit_events`, `system_settings`. |
| `backend/database/migrations/2026_10_19_000002_harden_admin_and_friendships.php` | Existing database hardening; inspect its MySQL and test-database behavior. |
| `backend/tests/Feature/SocialSecurityTest.php` | Existing admin/MFA/evidence/protected-account regression coverage. |

Existing code is not proof of full acceptance. For example, warning currently records an audit event without delivering an account warning, and dashboard backup text references `LOCAL_SETUP.md`, which is not a reliable status source.

## 3. Rules shared by both admin developers

- Exactly **one Super Admin**. King and Liam are developer assignments, not two production administrator accounts. The owner chooses the real admin identity; never create it silently or publish its password/recovery codes.
- Preserve the current Laravel/Inertia/MySQL architecture and all application data. Use additive migrations, not database resets. Keep backend keys and private runtime files out of shared source.
- Administration does not grant blanket access to Messenger threads, private notebooks, private collections, exact private pins or unrelated photos. Aggregate dashboard counts are not a license to return the underlying private records.
- Every admin endpoint requires backend authorization. A hidden navigation item is not authorization. Preserve account suspension checks, MFA requirements, CSRF, throttling, validation and fresh-password confirmation for sensitive changes.
- Preserve the editorial botanical theme: existing Fraunces/Inter fonts, forest/ivory palette, readable tables, restrained borders, accessible modals and mobile navigation. Do not redesign the restored public landing page or ordinary user workspace.
- No fake dashboard numbers, fabricated AI quotas, simulated notifications or unverified backup-success badges. Expose unavailable states clearly.
- Coordinate changes to common routes, shared types/components, authentication, warnings and report fields with the integration lead and Amaru. Do not edit Liam's modules while he is implementing them.
- No automatic commits, pushes, merges, account switching or deployment are requested by this handoff. Preserve truthful contribution records and teammates' uncommitted work.

## 4. K1 — Admin access, MFA and common security

- [ ] Verify normal users and guests cannot enter or call the admin area; test JSON mutations as well as page requests.
- [ ] Preserve one-admin creation and protected same-account recovery. Check database constraints/triggers, concurrent creation attempts, protected deletion and attempts to suspend the singleton account. Do not change the singleton to a general role system.
- [ ] Complete enrollment, password + TOTP verification, one-use recovery codes, expired admin sessions and reauthentication. Keep the current encrypted TOTP secret, hashed recovery codes and anti-replay counter protections.
- [ ] Keep `/admin/mfa` reachable to the authenticated singleton before MFA completion, without allowing ordinary users to enroll as administrators. Do not accidentally protect initial enrollment with a middleware that requires enrollment first.
- [ ] Clear pending secrets at the proper lifecycle points. Show plaintext setup/recovery material only in the intended enrollment/recovery flow, never in ordinary shared props, logs or screenshots.
- [ ] Establish a shared server-side helper/service for sensitive-action reauthentication. Liam must reuse it; do not duplicate weaker password checks in each later module. Keep existing password policy/session behavior compatible with Amaru's account-password changes.
- [ ] Define safe responses for stale MFA, invalid password, used code, unauthorized action and rate limiting. Expiry must not silently discard an unsaved admin decision.
- [ ] Extract or formalize the current audit-writing helper for both developers. Retain the existing `audit_events` fields and safe metadata contract below; provide a tested interface Liam can use for decisions/security events.

**Acceptance:** test guests, ordinary accounts, the singleton before/after MFA, expiry, TOTP replay, recovery-code reuse, failed confirmation, concurrent singleton creation and protected self-actions. Run MySQL-specific constraint acceptance on an isolated MySQL test database, not only SQLite.

## 5. K2 — Shared admin layout and dashboard

- [ ] Build one reusable admin layout and section navigation that Liam can extend. Reuse accessible shared primitives. Consider separating the six mixed sections in `Admin.tsx` into focused components within existing project folders, without rewriting working functionality.
- [ ] Keep `/admin` and the existing `section` query routes working. Foundation sections are `dashboard`, `users`, `settings`; preserve links to existing sections during refactoring. Reserve `reports`, `maps`, `ai`, `audit` for Liam, with truthful availability while unfinished.
- [ ] Create clear shared section headings, table containers, pagination, filter controls, confirmation/dialog structure and loading/empty/error states. Document their props for Liam.
- [ ] Show real user/plant counts, pending reports, bounded AI-error summaries and recent administrative events. Label each time window and distinguish total from filtered counts.
- [ ] Keep overview queries bounded and authorized. Do not dump private record bodies or raw user secrets into overview props merely to display counts.
- [ ] Replace broken backup-document references with an explicit status integration point for Liam. Until verified records exist, show **Not recorded / Not yet verified**, not a completed backup claim.
- [ ] Verify at 1440/1920 desktop, tablet and around 390px mobile, including wide tables, long names and short-height dialogs. Never solve overflow by making the fonts unreadably small.

**Acceptance:** dashboard counts change with isolated fixture data, navigation/pagination work, empty tables are readable, stale or unavailable data is labeled, and unauthorized users cannot fetch dashboard props.

## 6. K3 — User management

- [ ] Extend name-only search to useful, bounded account search by name, username and email; add active/suspended filters and pagination with preserved query state.
- [ ] Provide a limited account review showing permitted identity/status/join data and relevant warning/action history. Do not add a private-message, private-note or private-pin explorer.
- [ ] Deliver warnings to the intended user through a persisted authenticated notification/banner or an equivalent agreed mechanism. The current `warn` action only writes an audit event. Coordinate Amaru's app shell changes before adding shared UI.
- [ ] Require a reason and fresh confirmation for warning/suspend/restore. Record actor, target, action, time and reason without retaining plaintext confirmation passwords.
- [ ] Suspension must invalidate applicable sessions and prevent protected actions. Coordinate Reverb/channel behavior with Amaru so an already-open messaging session does not bypass suspension. Restoring access does not erase the action history.
- [ ] Preserve the protection against acting on the singleton admin. Do not silently create account deletion, impersonation, new admin roles or unrestricted password changes for other users.
- [ ] Make repeated actions and stale account state predictable; avoid recording duplicate success when a mutation failed. Ensure the state change, audit record and warning notification have consistent transactional behavior.

**Acceptance:** one user receives only their own warning; unrelated accounts cannot read it. Test search/filter pagination, wrong password, rejected self-suspension, suspended-session access and restore. Never use a teammate's live account as a suspension test fixture.

## 7. K4 — Platform settings and announcements

- [ ] Retain the current `announcement` and `ai_paused` keys with explicit validation, bounds, confirmation and audited updates. Do not expose arbitrary environment editing through a settings form.
- [ ] Implement announcement preview/publish/update/clear with plain-text safe rendering and a truthful persisted success state. Coordinate how ordinary users see it with Amaru.
- [ ] Keep the AI pause switch functional across identification, generative follow-ups and new note-assistance endpoints. Amaru must connect note assistance to the same pause policy rather than bypassing it. Liam will display state/usage; he does not create a competing pause setting.
- [ ] Existing conversations, photos and records remain saved/readable when AI is paused. Error/maintenance messages must explain what can be retried without discarding a draft.
- [ ] Treat provider names/readiness as safe metadata, not secret settings. API keys, OAuth secrets, database/mail passwords and private Reverb credentials remain backend environment configuration.
- [ ] Check reauthentication, MFA expiry, failed save, clearing an announcement and the appearance of published text on both desktop and mobile. Rendering user input as raw HTML is not needed.

## 8. Contracts King must hand to Liam

Retain these working routes unless the integration lead approves a coordinated replacement. Proposed file/component names are design choices, not a requirement to create a new framework.

| Contract | Existing or agreed shape | Owner after handoff |
| --- | --- | --- |
| Entry/navigation | `/admin?section=dashboard|users|reports|ai|audit|settings`; Liam adds `maps` if using that section. Backend allowlists must match links. | King defines layout; Liam plugs in his sections. |
| MFA | `GET/POST /admin/mfa`; protected routes use the singleton/MFA guard. | King. |
| User action | `POST /admin/users/{user}`; `action=warn|suspend|restore`, reason and confirmation. | King. |
| Settings | `POST /admin/settings`; `announcement`, `ai_paused`, confirmation. | King; Liam consumes safe state. |
| Audit writer | Existing fields: `user_id`, `action`, `subject`, JSON `metadata`, timestamps. Agree event names and an input-redaction allowlist. Never put passwords, TOTP, tokens, API keys or private thread bodies in metadata. | King provides primitive; Liam builds the viewer and additional event collection. |
| User report ingestion | `POST /reports`; types `post,comment,story,pin,user,message`, target ID, reason, initial `pending`. Identification requests remain posts with their existing kind. | Amaru owns user UI; Liam owns review. King preserves the contract. |
| Report review | `GET /admin/reports/{report}/evidence`, `POST /admin/reports/{report}`; existing statuses `pending,reviewed,resolved,dismissed`; record reason and validated target-specific action. | Liam. |
| Corrections | `POST /admin/corrections/{correction}` and existing saved original result. | Liam. |
| Backup status | No trusted “latest successful backup” store currently exists in the admin controller. Agree a minimal status source with unknown/success/failure/restore-verification distinctions. | Liam defines implementation; King provides the dashboard slot. |

Keep action routes behind the same guard even when splitting controllers. Agree migration names/order and new warning/status schemas; do not rewrite already-applied migrations. The `reports` schema is shared with Amaru, so changes cannot be decided by admin frontend code alone.

## 9. King → Liam completion gate

Liam may inspect/design tests beforehand, but starts dependent implementation **after King hands over a reviewed working foundation**. Do not use calendar time alone as the handoff signal.

- [ ] K1–K4 are implemented, or each remaining defect is explicitly disclosed and accepted by the integration lead.
- [ ] Admin authorization, MFA/recovery, sensitive-action confirmation and the common audit writer have focused regression tests.
- [ ] Dashboard, users and settings work end to end, including delivered warnings and announced maintenance state.
- [ ] Existing reports/AI/audit behavior still works during any refactor, or a planned migration path is documented and accepted. Do not hand over an application with erased existing functionality.
- [ ] Provide the actual changed-file list, new component/service interfaces, routes/props, schema/migration order and audit-event names.
- [ ] Give Liam the report/settings/backup integration contracts, safe test-fixture instructions, test commands/results and known issues. No real admin password, TOTP seed, recovery codes or `.env` values in this file or shared messages.
- [ ] Run the build and affected tests. Check ordinary login/workspace routes still work and the restored public landing page was not altered.
- [ ] Integration lead confirms this gate; Liam owns subsequent changes to his modules, coordinating edits to King's foundation rather than replacing it.

## 10. Verification and handback

From `backend/`, run appropriate focused tests first; then the relevant regression suite:

```powershell
php -d extension=gd vendor/phpunit/phpunit/phpunit tests/Feature/SocialSecurityTest.php
```

From `frontend/`:

```powershell
npm.cmd run build
```

Add meaningful coverage for new admin behavior, including browser checks for MFA/access, user-action dialogs and announcements. Use isolated QA databases/accounts; do not run destructive fixtures against the owner's MySQL database. Inspect repository instructions before implementation and run the relevant formatting/checks.

Update only genuinely completed checkboxes. Deliver reproducible acceptance steps and the completion-gate checklist to the owner and Liam. This task split assigns implementation ownership; it does not authorize sending external messages or creating production admin credentials on someone's behalf.
