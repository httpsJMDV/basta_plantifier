# Amaru implementation checkpoint

Authoritative scope: `amaru.md`. Started 2026-10-20 on branch `maru`.
The working MySQL database and private uploads must be preserved. No deployment,
push, database reset, or admin implementation is included in this work.

## Checklist (only verified work is checked)

- [x] 1. Complete conversation history, shared filters and archive/restore.
- [x] 2. Optional municipality lookup, provider policy and privacy handling.
- [x] 3. Unicode display names, common normalization and 20-character limit.
- [x] 4. Editable preferences and observable workspace/AI effects.
- [x] 5. Messenger inbox events, read updates, reconnect and two-browser acceptance.
- [x] 6. Local-password capability, settings password flow and Google status.
- [x] 7. Shared avatar alignment and responsive community feed.
- [x] 8. Post editor, persistent reactions and paginated comments/replies.
- [x] 9. Messenger send control, loading, Enter/IME and empty/duplicate prevention.
- [x] 10. Report modal using existing report storage and evidence boundaries.
- [x] 11. Note AI previews/undo and five private images with backend validation.
- [x] 12. Notes editor/list layout and unsaved-draft protection.
- [x] 13. Responsive shared-record dialog with fresh authorization.
- [x] 14. Workspace Help and independently scrolling timeline.
- [ ] 15. Remaining blueprint connections (track individually below).
- [x] 16. Contracts and integration sequencing recorded and followed.
- [ ] 17. Focused security tests, isolated browser journey, device/layout checks.

## Shared contracts and file ownership

This task owns non-admin changes in the existing working repository. Admin
controllers, screens, MFA and audit UI remain owned by King/Liam. There are no
other active coding agents in this session. Preserve concurrent changes if any
appear. Existing reports remain `subject_type`, `subject_id`, `reason`, pending
status; do not add an incompatible moderation workflow.

Messenger contract: private `inbox.{userId}` events carry only thread ID and
change kind, never message text, media, coordinates or email. Authorize only the
matching unsuspended user. Keep membership/block authorization on thread reads,
writes and `thread.{id}`. Read notifications must not cause read/reload loops.

Account contract: add a nullable `local_password_set_at` capability marker;
legacy Google-linked accounts require an explicit password-broker recovery or
successful current-password proof rather than guessing from their random hash.
Never remove an existing hash or unlink Google during migration.

Selected-card contract: retain `shares.plant_record_id` for existing foreign-key
and admin evidence compatibility. Optional `observation_id` or `map_pin_id`
narrows a share to that owned object, never both. Observation cards omit private
pin links and unrelated plant fields. Location cards require a currently public
pin and always obey its current precision/sensitive-location setting; making the
pin private immediately revokes previews and photo access. Existing report target
types and pending report fields remain unchanged. Only one selected card may be
attached per submission, with explicit sharing confirmation.

## External acceptance dependencies

- Contributor names: approved by the owner and added in the supplied order and Last name, First name format; no leadership label.
- Real SMTP: configured locally with owner-supplied credentials. One real Laravel
  broker reset was accepted by Gmail (`passwords.sent`); the owner confirmed email receipt from the updated Settings dialog. Credentials and recipient details must not enter this file or Git.
- Physical camera/location permission checks require the intended device.
- Existing eight missing imported image files cannot be restored from SQL.

## Resume point

Implementation is present for sections 1–14 and the required field connections
in section 15. Checked sections mean code and the recorded automated checks are
complete; they do not assert unperformed physical-device or external-team checks.

Latest backend rerun (October 20, 2026): **102 tests, 754 assertions, zero
failures/errors**. TypeScript/Vite build and Pint passed. The full isolated
Playwright rerun passed **11/11 tests in 4.2 minutes**, with zero failures.
The current and prior completed browser runs include real Reverb
and extended mobile delivery rechecks. Tests use in-memory SQLite or the guarded
QA SQLite server, never the working MySQL database.
Desktop 1440/1920, tablet 900 and mobile 390 screenshots were reviewed. The social
screenshots now wait for sidebar closure after a viewport resize, rather than
capturing an in-progress transition. Source diff whitespace checks pass; a scan
for the configured sensitive values found none in candidate source files.

Seven additive migrations have been applied to working MySQL and isolated QA:
`2026_10_20_082401` account capabilities; `083219` chat archives; `084430`
reactions/replies; `084840` note images/private links; `085332` visibility/field
links; `085832` message idempotency; `090237` selected observation/location cards.

QA uses `.env.amaru-qa`, `backend/database/amaru-qa.sqlite`, private uploads under
`backend/storage/app/amaru-qa-private`, HTTP 8025 and Reverb 8085. Its config is
ignored, contains no live API credentials, and uses log mail. A global browser
setup requires the `X-Plantifier-QA: amaru-sqlite` HTTP marker before any mutations.
`--no-reload` is required so Laravel's HTTP child inherits the QA environment.
The first startup failed this isolation assumption; the two exact generated
fixture accounts were cleaned from the working database before proceeding.
No pre-existing accounts/records were deleted.

PHP Intl is now enabled for Unicode NFC normalization. Local `scripts/php/gd.ini`
disables OPcache to avoid stale extension-function mappings in persistent Windows
HTTP workers after changing loaded extensions. It also allows 32 MB request
bodies while note files remain 5 MB each and chat files remain 4 MB each.

Reproduce QA:

```powershell
# Project root; use separate terminals for these two processes.
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\Start-AmaruQA.ps1
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\Start-AmaruQA.ps1 -Reverb
# backend/: seed only the guarded QA database, never the working database.
php artisan db:seed --class=AmaruQaSeeder --env=amaru-qa --no-interaction
# frontend/:
npx.cmd playwright test
```

The project-referenced `testing-best-practices` and `inertia-react-development`
skills and `search-docs` tool are unavailable in this installation. Existing
patterns and the installed Laravel/Inertia APIs were used, with the official
[Inertia v3](https://inertiajs.com/docs/v3/the-basics/manual-visits) and
[Laravel broadcasting](https://laravel.com/docs/13.x/broadcasting) references.
Municipality lookup follows the official
[Nominatim policy](https://operations.osmfoundation.org/policies/nominatim/):
explicit user action/consent, identifying User-Agent, aggregate one-request
cooldown, hashed coarse-location cache, configurable endpoint and attribution.
No coordinates are written to a profile. No deployment, push or admin UI change.

## Changed areas for integration

| Area | Main changed/new files |
| --- | --- |
| History/filter/archive | `Services/ConversationHistory.php`, `HistoryController.php`, `History.tsx`, `ConversationFilters.tsx`, `Shell.tsx` |
| Accounts/names/preferences | `AuthController.php`, `RegisterRequest.php`, `Rules/DisplayName.php`, `User.php`, `DisplayNameField.tsx`, `Onboarding.tsx`, `Settings.tsx`, `AccountPasswordReset.tsx`, `GroqProvider.php` |
| Broad location | `LocationController.php`, `MunicipalityField.tsx`, `config/services.php` |
| Realtime/send | `Events/InboxChanged.php`, `MessageController.php`, `routes/channels.php`, `Messages.tsx`, shared `lib.ts` |
| Community/reports | `CommunityController.php`, `DiscussionController.php`, `ReportController.php`, `Community.tsx`, `CommunityDiscussion.tsx`, `ReactionPicker.tsx`, shared `UI.tsx` |
| Notes | `Note.php`, note methods in `PlantController.php`, `NoteAssistController.php`, `NoteAssistant.php`, `ClaudeProvider.php`, `Notes.tsx` |
| Controlled cards/media | `Sharing.php`, `MediaController.php`, `ShareCard.tsx`, `ExportController.php`, `ExportDialog.tsx`, `resources/views/exports/plants.blade.php` |
| Field/social connections | `MapController.php`, `PinRequest.php`, `MapPin.php`, `Observation.php`, `PeopleController.php`, `SocialGraph.php`, `Map.tsx`, `People.tsx`, `Profile.tsx`, `PlantDetail.tsx` |
| Help/layout/contracts | `WorkspaceHelp.tsx`, `Workspace.tsx`, `styles/app.css`, `types.ts`, `HandleInertiaRequests.php`, `routes/web.php` |
| Local startup/isolated QA | `scripts/Start-Local.ps1`, `scripts/Start-AmaruQA.ps1`, `scripts/php/gd.ini`, `AmaruQaSeeder.php`, browser global setup and QA throttles helper |

Backend files without a prefix above are under the existing `backend/app`
controllers/models/services folders; frontend files are under `frontend/src`.
`git show --stat` lists the committed files; `git status --short` shows any subsequent edits. No new dependency or
admin implementation was introduced. Seven additive migrations are listed above.

Configuration names (never copy actual values into handoff/Git):

- Existing `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`,
  `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`: local Gmail SMTP configured and receipt
  confirmed. Local `.env` remains ignored.
- `APP_CREATORS`: optional override separated by `|`; approved contributor names now ship as the default in `config/botanical.php`.
- `GEOCODING_URL`, `GEOCODING_USER_AGENT`: optional reverse-geocoder overrides;
  documented public Nominatim defaults, attributed and cached.
- `PRIVATE_STORAGE_PATH`: optional private upload root; isolated QA overrides it.
- Existing Google/Pl@ntNet/Groq/Reverb keys and host/port settings remain local.
  QA has no live AI credentials and uses its own Reverb ports/credentials.

History defaults: active, all time, grouped by local date, sorted by last
activity. Filters persist in the session; the browser sends its IANA timezone.
The full page searches all owned titles and paginates 20; sidebar preview is 30.

Blueprint acceptance detail: selected observation/location sharing and media
revocation have dedicated boundary tests; journal/private-pin and note links are
owned and same-plant validated. Public scientific-name/date filters work without
invented categories. Public map markers remain bounded to a paginated 100 public
results: clustering was evaluated and deferred for the current bounded result
set. Re-evaluate viewport-aware clusters if a dense production dataset makes
that presentation difficult. Suggestions rank all eligible candidates before
pagination; the regression includes more than 100 candidates. This is correctness
coverage, not a large-dataset performance benchmark.

## Exact remaining acceptance and continuation

1. **Section 14, contributor names: complete.** Owner approved Del Valle, Jay Mark; Rubiales, Jef Liam; Ordona, King Anthony; Balmes, Amaru Jay. Added to Help in that order, preserving surname-first format and omitting leadership labels. An optional `APP_CREATORS` override remains available.
2. **Sections 2, 04 and 17, physical device:** on the intended phone/browser, test
   camera permission allow/deny, gallery uploads, geolocation allow/deny/timeout,
   and the actual broad place returned by the provider. Browser geolocation was
   mocked for deterministic UI tests; backend provider failures/coarse payloads
   are tested. Do not describe that as a physical-device/provider field test.
   Google sign-in and real plant chat worked in the owner's earlier confirmation;
   they were not retested with new private uploads during this implementation.
3. **Sections 10/16, cross-team report receipt:** pending reports preserve the
   existing schema and are verified persisted. King/Liam still need to confirm
   consumption in their admin workflow. Their screens/MFA/audit remain untouched.
4. **Section 17, final presentation rehearsal:** run the specified journey on the
   intended device using authorized real plant examples. The isolated automated
   journey covers persistence, saved records, notes/observations, optional shares,
   live two-account Messenger and reporting; it intentionally disables live AI
   calls. No permission to reuse private photos externally is assumed.

The three required reactions are implemented. Helpful/Curious remain optional
owner choices, not missing mandatory code. Custom collections, care reminders,
metrics, production deployment and admin modules remain outside this assignment.
The eight absent imported image files require the original upload files; SQL
contains references rather than the image bytes.

To resume: read this checkpoint and the implementation checklist in
`amaru.md`, obtain the external acceptance above, and record the results. Do not
reset/reseed MySQL or rerun the entire implementation. No token-limited code
section is abandoned; remaining items are explicitly identified acceptance or
owner/team inputs. The owner has now requested a local commit of these updates on `maru`. No push, merge or deployment is included.
## Requested functional test rerun — October 20, 2026

The first backend run exposed a test-fixture defect: `ProfilePhotoTest` submitted
a randomly generated legacy-length name while checking avatar mass assignment.
Names longer than 20 were correctly rejected, preventing the intended avatar
assertion. The test now supplies the valid name `Profile Explorer`; the avatar
security assertion is unchanged. Its five tests and the complete suite passed.
No application validation was weakened.

Added two meaningful field-connection regression cases in the existing test class:
linked story cards require explicit consent and an owned plant; friends-only
access, plant-photo access, blocking and 24-hour expiry are enforced. Reopening
an identification request rejects non-owners, preserves closed state on rejection,
and allows new discussion after the owner reopens it. Both cases passed.

| Backend test group | Cases | Result and important coverage |
| --- | ---: | --- |
| Account Settings | 6 | Pass: current-password proof, confirmation, Google capability, broker reset, expired/reused tokens |
| Display names | 5 | Pass: NFC/spacing, exact limit, numbers/symbols rejected, existing login preserved |
| History | 3 | Pass: old titles beyond 30, ownership, archive/restore, persisted filters, local date boundaries |
| Municipality | 2 | Pass: coarse payload, cache/rate limit, no profile coordinate/discovery mutation, incomplete provider result |
| Messenger inbox | 2 | Pass: private channel authorization, minimal event payload, submission idempotency, read-loop prevention |
| Community discussion | 4 | Pass: reactions, cross-post/depth restrictions, newest preview beyond 50, editing/report authorization |
| Note assistance/images | 4 | Pass: five images, size/count/ownership, attachment retention, selected-text-only AI, safe provider failure |
| Field connections | 7 | Pass: pin photos/search/precision, journal links, profile privacy, candidate ranking, exports, stories, reopening |
| Selected sharing | 2 | Pass: narrow observation/location cards, revocation, foreign/multiple targets rejected |
| Google authentication | 6 | Pass: mocked OAuth flows and account/state boundaries |
| Hybrid identification | 20 | Pass: controlled provider responses, validation, explanation context and failure/retry behavior |
| Profile photos | 5 | Pass: image processing, replacement/removal, privacy, invalid uploads, mass assignment |
| Existing botanical/social/landing regression groups | 36 | Pass; 102 total cases and 754 assertions across all groups |

Raw passing PHPUnit JUnit output: `backend/storage/logs/amaru-test-results.xml`
(local ignored artifact). Browser screenshots/traces remain under ignored
`frontend/test-results`. These contain synthetic QA data, not user uploads.

Limits of this run: AI/OAuth/geocoding/mail provider responses are controlled in
automated tests. Real Reverb traffic is used for browser messaging. Provider
mocking proves application behavior for those responses; it does not prove live
API availability or identification accuracy. The owner's prior live Google/chat
and reset-email confirmations remain separate evidence. Physical camera/GPS and
admin-team report receipt remain the explicit acceptance items above.
Final full browser rerun: **11 passed in 4.2 minutes**, no failed or skipped cases.
It covers Settings/password error handling and city review, persistent preferences,
full history/archive/restore, note image retention and AI preview/cancel/apply/undo,
long timeline footer and Help, inbox arrival, real Reverb reconnect/read receipts,
text/photo/card delivery at desktop and mobile widths, shared-card tabs/Escape,
community edit/cancel/reactions/replies/reporting, keyboard selects, the original
landing design, the integrated personal journey, three-photo attachment limits,
writing-style persistence, and animated responsive panels/navigation.

Reproduce from the existing prepared QA environment:

```powershell
# backend: in-memory SQLite from phpunit.xml; raw output is ignored.
php -d extension=gd vendor/phpunit/phpunit/phpunit --log-junit storage/logs/amaru-test-results.xml
# frontend: build first, then verify the QA server and run real-browser checks.
npm.cmd run build
npx.cmd playwright test
```

Current outcome: all executed automated cases pass. This is evidence for the
covered cases in this local environment, not a guarantee of every device,
production dataset, live provider response or possible user interaction.
## Follow-up: sidebar filter clipping and link label

Fixed the sidebar filter panel being clipped by the narrow scroll container.
It now renders through a portal using the existing native popover pattern,
positions inside the viewport, scrolls internally on short screens, focuses its
first control, dismisses on outside interaction/Escape, and returns focus on
Escape. Nested select Escape closes the options before closing the filters.
Renamed the sidebar history link to exactly `Chat History`.

Validation: TypeScript/Vite build passed. Existing Settings/history apply/reset
browser case passed. New targeted regression passed at widths 1440, 1920, 900
and 390 with a 650px-tall viewport. It verifies actual unobstructed panel contents,
viewport bounds, all four controls, nested select interaction, Escape/focus return
and navigation through `Chat History`. Reviewed desktop/mobile QA screenshots.