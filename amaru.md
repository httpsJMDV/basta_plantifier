# Amaru — PlantifierAI non-admin handoff

Updated October 19, 2026. Presentation: October 24, 2026.

## Assignment and boundaries

Continue the existing application. This file is an implementation handoff, not a statement that the tasks below are complete. The project owner's October 19 requests take precedence over older planning notes.

- Work on the user-facing application and its necessary backend services, validation, policies, migrations, and tests.
- **Exclude the Admin System.** King builds the admin foundation first using [king.md](king.md); Liam extends it using [liam.md](liam.md). Do not implement or redesign admin dashboards, admin MFA, Super Admin setup, moderation decisions, or audit screens. Coordinate shared report fields with them.
- Keep Laravel, React/TypeScript, Inertia, MySQL, Reverb/Echo, and the existing frontend/backend folders. Reuse `Avatar`, `CustomSelect`, `Modal`, `Button`, and the existing design tokens.
- Preserve accounts, conversations, uploads, plant records, shares, and identification provenance. Add migrations; never reset or reseed the working database to make a feature work.
- The first public landing-page design was restored at the owner's request. Keep it. The later reduced-spacing/plain-copy revision was rejected.
- Team coding is now allowed. Coordinate file ownership with the integration lead and work in the existing repository. Do not overwrite others' changes or commit secrets. This handoff does not itself request a commit, push, merge, or deployment.

## Current foundations: extend these instead of rebuilding

| Area | Existing behavior and starting files |
| --- | --- |
| Authentication | Password login, reset broker, verification, Google OAuth and explicit matching-account linking exist. `backend/app/Http/Controllers/AuthController.php`, `frontend/src/pages/Auth.tsx`. |
| Onboarding/settings | Purpose, experience, interests, municipality, and discovery preference persist. Name currently allows 80 characters. Purpose/experience are not editable in Settings or used to tailor AI explanations. `Onboarding.tsx`, `Settings.tsx`, `AuthController.php`. |
| Workspace | Persistent chats, rename/delete, three photos per message, camera, writing styles, identification cards, follow-ups, save-to-collection and save-as-note exist. `Workspace.tsx`, `WorkspaceController.php`. |
| Identification | `PlantNetIdentifier.php` identifies; `GroqProvider.php` writes; `HybridBotanicalProvider.php` coordinates and caches results. Claude is an optional quota fallback. Keep these responsibilities separate. |
| Sidebar | Recent conversations are capped at 30 in `HandleInertiaRequests.php`; search in `Shell.tsx` only filters those loaded titles. There is no complete history/filter/archive UI. |
| Messenger | Persistence, private-channel authorization, an Echo subscriber and `ThreadChanged` broadcasts exist. The owner reports needing manual refresh; treat realtime delivery as a reproduced acceptance issue, not a missing library. |
| Community | Post creation, body editing, deletion, comments, suggestion votes, identification requests and stories exist. Editing currently uses a browser prompt. Replies and the requested social reactions are missing. |
| Reporting | Shared `ReportButton` uses `window.prompt`; `POST /reports` persists pending reports for the existing admin workflow. Replace the user experience, not the reporting system. |
| Notes | Private title/body notes, search, plant linking, save/delete and chat-to-note exist. Note images and AI editing are missing. `Notes.tsx`, note methods in `PlantController.php`. |
| Collection/maps/shares | Collection CRUD, observations, private/public maps, controlled Plant Record sharing and basic exports exist. `PlantController.php`, `MapController.php`, `Sharing.php`, `ShareCard.tsx`, `ExportController.php`. |
| Profile photos | PNG/JPEG profile upload up to 5 MB and the shared `Avatar` component already exist. Fix inconsistent layouts; do not create a second avatar-upload system. |

Paths above without a directory prefix are frontend pages under `frontend/src/pages/`, shared components under `frontend/src/components/`, or backend classes under `backend/app/`. Check the actual file before changing it. `IMPLEMENTATION.md` contains historical entries, including items that have since been completed; it is not an authoritative list of missing features.

## 1. Sidebar filters and complete conversation history

The first reference image shows the desired filter interaction: Status, Last activity, Group by, Sort by, and Reset to defaults. Match its usefulness, using PlantifierAI's existing colors and typography rather than copying Claude's branding.

- [ ] Add a keyboard-accessible filter popover beside Recent conversations, with clear selected values and reset behavior.
- [ ] Proposed options: Status = Active / Archived / All; Last activity = All time / Today / Last 7 days / Last 30 days; Group by = Date / None; Sort by = Last activity / Created date / Title. Display only options that actually work.
- [ ] Status requires real archive/unarchive behavior and an additive `archived_at` field or equivalent. Do not invent inactive states or make deletion behave like archiving.
- [ ] Add a complete **Chat history** page reachable from AI Workspace/sidebar. Support owner-only, server-side title search, pagination, these filters, reopening, rename, archive/unarchive and confirmed deletion. Preserve saved Plant Records after chat deletion.
- [ ] Use the same query/filter logic for the sidebar and history page. Search must not silently be limited to the 30 loaded recent chats. Clearly distinguish sidebar previews from the complete results page.
- [ ] Preserve filter state through navigation, with documented defaults. Use the user's local date boundaries for date grouping and activity filters.
- [ ] If using `/workspace/history`, register it before `/workspace/{conversation?}` or constrain the dynamic route to numeric conversation IDs. Do not let `history` resolve as a conversation ID.

**Acceptance:** create more than 30 conversations; an old matching title is found in history, filtering is consistent, archived chats can be restored, and another user's chats are never returned. Empty results, reset, loading, keyboard dismissal, and mobile layout work.

## 2. Optional current-location detection and municipality autofill

Reference: onboarding's Municipality or city field.

- [ ] Offer **Use my current location** in onboarding and Settings. Ask for browser permission after that explicit action. Once permission is granted, resolve and automatically fill the municipality/city field.
- [ ] Use browser location plus a reverse-geocoding service. Read the chosen provider's current official documentation, permitted use, attribution and limits before implementing it. Keep any private service key in Laravel configuration.
- [ ] Use the returned city/municipality, with an appropriate town/village fallback; include province when needed to distinguish identical names. Do not fabricate a municipality from failed or incomplete geocoding.
- [ ] Let the user review/edit the result. Preserve a manually entered place unless the user deliberately replaces it.
- [ ] Handle denied permission, unavailable position, timeout, unsupported browser, insecure origin and service failures. Manual entry and Skip remain usable.
- [ ] Do not persist exact GPS coordinates on the profile merely to fill a city. Do not log raw coordinates or include them in people suggestions. Explain location-provider processing before the action.
- [ ] Location permission and profile discovery are separate choices. Geolocation must not enable public sharing or discovery automatically. Discovery stays opt-in.
- [ ] Replace the existing “No GPS permission is needed” helper when adding the optional permission-based action; distinguish manual entry from automatic lookup.

**Acceptance:** allowing location fills the correct broad area; denying it leaves a usable form. Nothing becomes public and no exact coordinates appear in profile payloads or suggestion explanations.

## 3. Display-name validation

Reference: “What should we call you?”

- [ ] Limit display names to **20 characters** after trimming; allow letters and spaces only. Reject numbers, punctuation, emoji and other symbols. Require at least one letter; normalize repeated/outer spaces consistently.
- [ ] Support actual letters such as `ñ` and accented names rather than accidentally making names ASCII-only. Normalize Unicode consistently before counting and validation.
- [ ] Add an accessible `current / 20` counter and useful validation feedback. Validate on the backend too; `maxLength` alone is insufficient.
- [ ] Apply the same rule to registration, onboarding and Settings. Coordinate Google-created names: ask users to choose a valid display name when needed; do not corrupt their Google identity or silently truncate existing names/history.
- [ ] Keep optional usernames separate; their existing handle rules are not display-name rules.

**Acceptance:** names with spaces and supported letters work; 21 characters, digits, symbols and whitespace-only values fail through both the UI and direct requests. Existing accounts still sign in and can correct their names.

## 4. Give purpose, experience and interests a real use

References: Discover/Grow/Research/Connect, experience level and plant-interest selections. The following are recommended connections, not existing completed behavior.

| Preference | Recommended use in the application |
| --- | --- |
| Discover | Offer an optional upload/identify shortcut in the Workspace welcome area. |
| Grow | Offer collection and growth-journal shortcuts; suggest care-oriented follow-up actions. |
| Research | Offer notes, observation records and export shortcuts. Never imply that this grants expert status. |
| Connect | Offer Community, identification requests and people suggestions, respecting discovery opt-in. |
| Beginner experience | Explain botanical terms and use shorter explanations by default. |
| Enthusiast experience | Use ordinary botanical detail with explanations where useful. |
| Experienced | Allow more technical terminology and detail, without changing identity, confidence or safety rules. |
| Plant interests | Keep the existing shared-interest people-suggestion signal; add optional, explicit interest filters where posts/records have real relevant tags. Do not invent content categories or imply a feed is filtered when it is not. |

- [ ] Make purpose, experience and interests editable in Settings. Preserve existing saved values on both onboarding and profile updates.
- [ ] Keep AI **writing style** (Friendly/Formal/Simple/Scientific) separate from **experience/detail level**. Explicit user requests and style selections take precedence over preference defaults. Preferences can never override Pl@ntNet or weaken uncertainty warnings.
- [ ] Keep AI Workspace as the default signed-in home. Purpose suggests useful actions; it must not redirect users away from it, gate features, repeatedly show onboarding, or generate a fake personalized dashboard.
- [ ] `PeopleController.php` already uses interests, municipality, mutual friends and shared activity for suggestions. Extend/test that behavior instead of building a duplicate recommendation system.

**Acceptance:** changing a preference has an observable, explained effect, survives reload, and does not expose private locations or take away any feature. Compare otherwise identical beginner and experienced explanation requests in tests.

## 5. Fix Messenger's realtime delivery

Reference: messages currently need refresh to appear.

- [ ] Reproduce with two independently signed-in browser contexts and a running Reverb server. Trace persistence, broadcast connection, private-channel auth, event delivery and UI updates before choosing a fix.
- [ ] Inspect `Messages.tsx`, `MessageController.php`, `Events/ThreadChanged.php`, `routes/channels.php`, broadcasting/Reverb configuration and `scripts/Start-Local.ps1`.
- [ ] The event currently implements `ShouldBroadcastNow`; do not assume a queue worker is the missing step. Check the actual transport configuration and server availability. The controller currently catches broadcast failures silently; retain saved messages but add safe diagnostics without secrets.
- [ ] Active conversations must update for both sender and recipient without a manual page refresh. Maintain ordering, avoid duplicate messages, and preserve typed drafts and reading position.
- [ ] Refresh unread counts, conversation ordering and newly created threads even when the recipient has another thread open or is viewing the Messages list. The current subscription only to the active thread is not enough for inbox-wide updates; use authorized user/inbox notifications or an equivalent safe design.
- [ ] Handle reconnect and re-sync after interruption, and show truthful connection states. Do not describe polling or a refresh button as successful Reverb delivery.
- [ ] Preserve membership, block and suspended-account checks. Broadcast minimal event data; do not publish private text or locations on public channels. Coordinate read receipts and unread updates without event/reload loops.

**Acceptance:** A sends text, a photo and a Plant Record; B sees each without refresh. Repeat with B on another thread and with reconnect. Verify unread/read changes and rejection of a third user's channel subscription. Existing `plantifier.spec.ts` is a starting point, not proof that the owner's current setup works.

## 6. Settings password flow and Google account status

Reference: Account → Reset password / Link Google account.

- [ ] Password-account users get a Settings modal/page with current password, new password and confirmation. Verify the old password server-side; keep the existing password-strength policy, CSRF protection and throttling.
- [ ] Google-only account users get an email-based set/reset-password action. Send a real Laravel password-broker link to their account email; the signed, expiring-token workflow must open a Settings-styled reset modal/page as requested. Never set a password merely because someone knows an email address.
- [ ] Preserve the guest Forgot password flow. A reset link opened in a different browser/session must have a safe supported path; avoid login/reset redirect loops. Explain expired or used tokens and permit resending within rate limits.
- [ ] Explicitly model whether local password credentials have been established. **Do not infer it from a non-null password hash:** the current Google signup creates a random password hash. A Google link also does not mean an existing password account has lost its password. Agree on a backward-compatible migration for legacy accounts.
- [ ] Expose only safe account capability flags, such as `google_linked` and `has_local_password`, to Settings. Keep `google_id`, hashes and reset tokens private.
- [ ] If Google is already linked, clicking the link action opens an informational modal: **Your Google account is already linked.** Do not launch another OAuth round trip. Judge this using the stored link, not an assumption about the latest login button used.
- [ ] If not linked, preserve the current explicit matching-email OAuth flow and state checks. Do not merge unrelated accounts.
- [ ] Handle successful change/set/reset, loading and errors; invalidate tokens and rotate remembered-login credentials as appropriate. Coordinate session security without locking out the owner.

**Acceptance:** test password-only, Google-only, and linked accounts with local passwords; wrong old password, mismatched confirmation, invalid/expired/reused tokens, cancellation and unrelated Google accounts. Verify actual email delivery with configured SMTP; a log-mail entry is not delivered mail.

## 7. Avatar alignment and community layout

References: sidebar footer, post author/photo circle, Community feed spacing.

- [ ] Use shared `Avatar` rendering and consistent fixed sizes, aspect ratio, `object-fit`, flex alignment and shrink behavior in sidebar, posts, comments, profiles and Messenger.
- [ ] Align the author name and timestamp with the avatar. Preserve this with long names, missing photos, uploaded photos and wrapped metadata.
- [ ] Give the Community page a consistent readable feed width, aligned composer/post/comment edges and predictable vertical gaps. Use familiar social-feed hierarchy, keeping PlantifierAI's existing theme and fonts.
- [ ] Do not stretch the feed across a wide monitor, clip content, or force mobile-sized modals on desktop. Check 1440/1920 desktop, tablet and 390px mobile layouts.

## 8. Post editing, reactions and replies

References: browser-prompt edit dialog and the existing comment area.

- [ ] Replace `window.prompt` post editing with a proper modal or post editor in the page. Existing body editing and ownership checks should be reused. Keep the original post visible, retain unsaved changes after errors, and handle cancel without modifying it.
- [ ] Support editing permitted post fields, including image/area changes when offered. If adding image replacement, use validated uploads and additive backend support; do not claim editable controls that only change local state.
- [ ] Add post and comment reactions. Required labels: **Fascinating**, **Crazy**, **Loved It**. Suggested additions: **Helpful**, **Curious**. Final labels can be confirmed during review.
- [ ] Use a small emoji/reaction picker with restrained selection animation. Provide readable labels, keyboard and touch controls, visible selected state and reduced-motion support. Avoid downloaded Facebook artwork or animation dependencies without a need/license.
- [ ] Persist one selected reaction per user per target. Clicking again removes it; choosing another replaces it. Enforce uniqueness and allowlists on the backend; display real counts only.
- [ ] Keep **social reactions separate from evidence-backed identification votes**. “Loved It” must not accept or scientifically verify a species. Preserve existing suggestion-vote and requester acceptance behavior.
- [ ] Add replies with explicit parent/post relationships and authorization. A reply cannot target a comment on another post. Bound reply depth and paginate rather than permitting arbitrary nesting or loading every comment into every feed post.
- [ ] Collapsed feed posts show the **latest top-level comment**, plus a visible reply preview if that comment has replies. Show counts and expandable controls to see all permitted comments and replies; provide collapse again. Use the latest reply for the preview, then load the thread in a consistent order.
- [ ] Fix the current oldest-first, first-50-comment loading behavior; slicing the already truncated frontend array cannot reliably produce the latest comment. Fetch the latest preview separately and paginate expansion.
- [ ] Keep an author's new comment/reply visible after posting. Respect deleted/hidden/blocked comments and closed identification requests. A missing parent must not reveal restricted text through a reply preview.

**Acceptance:** two users react/change/remove reactions, reply, edit their own posts and expand/collapse long discussions. Verify counts after reload, unique constraints, newest preview beyond 50 comments, keyboard controls, reduced motion, and rejection of another user's edits or cross-post reply IDs.

## 9. Messenger send button

- [ ] Replace the current crowded send control with a compact, aligned forest-green icon button. Keep a clear disabled state, loading feedback and the accessible name “Send message.”
- [ ] Do not render a spinner and arrow on top of each other while sending. Keep attachment, input and send controls aligned at different textarea heights.
- [ ] Preserve Enter to send, Shift+Enter for a newline and IME composition handling. Empty and duplicate submissions must be prevented in the interface and validated by Laravel.
- [ ] Verify with text, photo-only and plant-card-only messages on desktop and mobile; this is separate from proving realtime delivery.

## 10. Dedicated report modal and admin handoff contract

- [ ] Replace the browser prompt in shared `ReportButton` with an accessible modal or report page for posts and messages; reuse it for other supported targets where appropriate.
- [ ] Show the selected target, a reason selector and details field with validation, submit/loading/success/error states and cancel. Do not expose private content that the reporter cannot access.
- [ ] Submit to the existing `POST /reports` workflow so the report is stored as pending and is available to the Admin System. Agree any new reason codes/fields with the admin developers before changing the shared contract.
- [ ] The admin team owns queue screens, notifications on their side, investigation permissions and resolution actions. Amaru verifies that the submitted report can be consumed; do not add a second queue, send personal emails, or build admin pages here.
- [ ] Preserve narrow evidence access: reporting one direct message does not grant access to an entire private thread or unrelated media. Validate reporter access server-side; consider duplicate reports and rate limits.

**Acceptance:** authorized reports persist with the correct target, reason and pending status; invalid/inaccessible targets fail; cancel creates no report. Coordinate an admin-side receipt check with the other developers.

## 11. Notes & Journal: AI writing assistance and images

- [ ] Add explicit actions such as **Polish writing**, **Make formal**, and **Summarize**, using Groq through Laravel. Use the selected note text or user-selected passage; do not send unrelated private notes or their images.
- [ ] Preview the proposed result with Apply/Cancel and an undo path. Preserve the original until the user applies it. Do not auto-save AI edits, invent observations, or change scientific names, measurements and dates without review.
- [ ] Keep note assistance separate from photo identification. Do not invoke Pl@ntNet or force notes through the identification-only JSON schema. Reuse safe provider transport/fallback handling where suitable, with a dedicated note-writing prompt and response validation.
- [ ] Require ownership, bounded input/output, request throttling and safe plain-text rendering. Model instructions embedded in notes remain untrusted content. Quota or network failures must leave the draft intact.
- [ ] Add up to **5 images per note, 5 MB each**, with selection/camera if supported by the existing pattern, preview, removal, replacement and persisted authorized access. Validate content, size, dimensions and count on Laravel, re-encode/strip metadata, and preserve attachments during ordinary text saves.
- [ ] Reuse private media storage with a note/attachment relationship. Changing the limit in the file picker alone does not implement uploads. Never allow arbitrary media IDs from other users.
- [ ] Raise the request-body allowance to support five 5 MB files **plus multipart overhead** (for example, 32 MB), in the documented local PHP setup. The current startup allowance is 16 MB. Keep per-file validation at 5 MB; do not globally relax every module's limits.
- [ ] Preserve AI Workspace's separate 3-photo/4-MB rule and profile-photo 5-MB rule.

## 12. Notes & Journal: layout and actions

- [ ] Remove the bulky moss-colored outer treatment around “Standalone note · no linked plant.” Use the existing custom select with a clear label and consistent field sizing.
- [ ] Redesign New note, Save and Delete consistently with the theme. Use an appropriate destructive state and accessible confirmation for Delete; retain unsaved-change protection.
- [ ] Fix note-list previews: wrap or deliberately clamp titles and excerpts with an ellipsis; do not hide half a line of text with fixed-height clipping. Show useful date/linked-record metadata without crowding it.
- [ ] Balance list/editor widths and internal spacing on desktop. On mobile provide usable note selection and editing, without a sidebar swallowing the editor. Images and AI controls must not obscure Save.
- [ ] Keep saving/dirty indicators accurate and prevent silent draft loss during search, pagination, note selection or navigation. Add autosave only if it can reliably handle validation failures and concurrent edits; it remains optional.

## 13. Shared Plant Record modal

Reference: the very tall Arabian jasmine card opened in Messenger.

- [ ] Update shared `ShareCard.tsx` so Community and Messenger use the same improved details view.
- [ ] On desktop use a wider dialog with a bounded photograph/identity column and a readable details column. Keep the existing font sizes and scientific-name styling.
- [ ] Make the overview useful without scrolling: names, photo, source/uncertainty and concise description. Use accessible tabs/expandable sections for longer description, care and safety, keeping the close control visible.
- [ ] On mobile stack the content and permit bounded internal scrolling. Do not promise zero scrolling for arbitrarily long content, shrink fonts, hide safety text, or truncate the only copy of information.
- [ ] Preserve fresh permission checks on open, revoked-share handling, focus trapping, Escape/close and focus return. Do not expose private notes, exact locations or full records merely to fill a larger modal.
- [ ] Fix visible replacement/question-mark separators in share-source text using proper UTF-8; check similar user-facing labels.

## 14. Workspace Help and fixed timeline footer

- [ ] On **AI Workspace**, replace the top-bar “Private by default” badge with a Help button opening a proper modal/page. Do not remove actual privacy defaults or misleadingly change other pages' indicators.
- [ ] Explain: photos go to Pl@ntNet for identification/scoring; Groq receives structured identification context and writes explanations; alternatives and uncertainty are not guarantees; three views should show the same plant; unrelated images may be rejected.
- [ ] Explain why the platform was built and list its actual creators/contributors using names approved by the owner. Do not invent contributor names or infer authorship from a Git account. The wording can be supplied later without blocking the Help structure.
- [ ] Mention the optional configured Claude quota fallback accurately if enabled; do not claim it works without usable credentials and credits. Keep technical detail out of the ordinary user flow unless it explains behavior.
- [ ] In Session timeline, only the timeline events should scroll. Keep the bottom divider and **Worth remembering / Save a suggestion… / Visit your collection** area fixed within the panel, below the scroll region.
- [ ] Use flex/grid sizing with `min-height: 0`, an independently scrollable event area and a non-shrinking footer. Do not position the footer over the events or pin it to the full browser instead of its panel.
- [ ] Preserve collapse transitions, fixed toggle positions, reduced-motion support, event-to-message navigation and mobile drawer behavior. Test a long chat and a short-height viewport. The footer must remain usable without hiding the last event.
- [ ] Connect the complete history page from task 1; Session timeline is still only the selected chat's events, never a replacement for cross-chat history.

## 15. Remaining non-admin blueprint work

This table reconciles the 20 selected areas with the code inspected for this handoff. “Existing” means code is present; it does not replace an acceptance test. Do not recreate a working area because an old tracker calls it unfinished.

| Blueprint area | Remaining work or required acceptance |
| --- | --- |
| 01 Onboarding | Tasks 2–4: optional location autofill, 20-character names, useful preferences and Settings editing. Preserve skip/return behavior. |
| 02 Authentication | Task 6 plus real SMTP verification/reset rehearsal, OAuth cancellation/linking and permission-safe capabilities. |
| 03 Workspace/history | Tasks 1 and 14. Existing chats, rename/delete and saved-record lifecycle must survive. |
| 04 Identification | Keep the working hybrid flow, 3-photo batch validation, no-match behavior and cache/retry logic. Physically check camera/permissions and actual plant examples. A group photo is not a complete species inventory. |
| 05 Collection | Existing CRUD/filter/favorite/archive/detail flows stay. Finish missing navigation between a plant, its observations, notes and authorized map locations. Optional custom collections are a later enhancement, not a release blocker. |
| 06 Gallery/location | Add pin-specific photos (current map payload relies on the linked plant, not a dedicated pin upload); preserve separate private/public maps, date, area and sensitive-location controls. |
| 07 Notes | Tasks 11–12. Add optional links to a private map pin and conversation as well as a plant. Existing chat-to-note creates note text but does not preserve a conversation relationship. Enforce ownership on every link. |
| 08 Community | Tasks 7–8 and 10. Preserve pagination, visibility, blocking and own-content controls. |
| 09 Messenger | Tasks 5 and 9. Recheck unread/read behavior, attachments and two-account delivery. |
| 10 Sharing | Existing controlled shares cover Plant Records. Add permitted observation/location cards for Community and Messenger, with current visibility/revocation checks and approximate-public-location rules. Coordinate schema changes; never serialize private objects wholesale. |
| 11 Stories | Add optional linked-plant cards. Check existing viewer navigation, author deletion, report/block controls, audience and 24-hour expiry; complete any missing user controls. Expired items must not remain visible awaiting cleanup. |
| 12 Profiles/following | Avatar upload already exists. Add explicit profile, follower/following and activity visibility controls with enforced backend reads. Check blocked/suspended users and private collection separation. |
| 13 Identification requests | Preserve suggestions and requester acceptance; add reopening a closed request and clear open/closed status. Only the owner can reopen it. |
| 14 Public map search | Existing search supports title, area and common plant name, with a date filter and pagination. Add scientific-name search and any missing filter UI; add categories only for real stored categories. Evaluate clustering for larger datasets. Keep private pins out of results/counts/bounds. |
| 15 AI corrections | Existing original-result preservation and correction submission remain. Verify the user sees accurate submission feedback; coordinate review/status contracts with admin developers without implementing their review screens. |
| 16 Community verification | Existing suggestion votes/accepted results remain distinct from task 8 reactions. Check duplicate/self-vote protection and avoid expert-verified claims. |
| 17 Growth journal | Existing dated observations, measurements and before/after views remain. Add optional owned private-pin linkage and check edit/delete/ordering and plant relationships. |
| 18 Export | Extend the current authorized PDF/CSV export with selectable fields and permitted images/selected observations where applicable. Keep images private, PDF remote/script execution disabled, and CSV formula-injection defenses. Do not export another user's notes or private coordinates. |
| 19 Friends | Existing request/cancel/accept/decline/unfriend/block flows remain. Check privacy choices, duplicates, mutual counts and changes propagating into profiles, suggestions and Messenger. |
| 20 Suggestions | Shared interests/municipality/mutual/activity scoring already exists in `PeopleController.php`. Connect autofilled broad areas; preserve opt-in exclusions. It currently sorts a name-limited pool of 100 candidates: evaluate ranking/pagination before claiming complete best matches for a larger user base. |

Also check user-facing loading, empty, validation, permission-denied and provider-unavailable states across the affected pages. Fix demonstrated non-admin regressions, not unrelated redesigns. The project still excludes production deployment, a new metrics dashboard, fabricated social content, care reminders and other unapproved feature expansions.

## 16. Coordination and implementation order

1. Reproduce Messenger failures and agree the account/report/schema contracts. Create a checklist against this file before editing shared code.
2. Fix realtime and account password/link status; these affect core functionality and security.
3. Implement names/location/preferences, then history/filter/help/timeline behavior.
4. Add Community editing/reactions/replies/reporting and common avatar/layout fixes.
5. Add note assistance/images/layout and the shared plant modal.
6. Complete the remaining required blueprint connections and run the integrated presentation journey. Keep optional enhancements separate.

Coordinate changes to `routes/web.php`, `HandleInertiaRequests.php`, shared types/UI, `AuthController.php`, `MediaController.php`, reporting and migrations with the other developers. New reaction/reply/media/share types need agreed validation and media-authorization rules. Private-channel changes must have a documented event/channel contract.

Admin ownership is defined in [king.md](king.md) and [liam.md](liam.md). Coordinate shared security/layout and user-warning changes with King; report review/queue and evidence contracts with Liam. Do not implement their admin modules or overwrite their files. User-facing reports use the same existing report storage. Two admin developers still means exactly one Super Admin account.

## 17. Verification and handback

Run appropriate focused tests as each behavior changes. Useful existing coverage is in `backend/tests/Feature/` and `frontend/tests/browser/`. PHPUnit uses an isolated test database; browser tests use their separately configured local server/database. Never point destructive test setup at the working MySQL database.

```powershell
# From backend/ — enable GD for image-related tests.
php -d extension=gd vendor/phpunit/phpunit/phpunit

# From frontend/.
npm.cmd run build
npx.cmd playwright test
```

The browser test configuration currently targets `http://127.0.0.1:8025`; prepare the isolated QA server/database before running it. Check any mail/register throttles without disabling production safeguards. Start Reverb for genuine two-browser realtime checks, not just a mocked event assertion.

For the ordinary local demonstration, from the project root:

```powershell
.\scripts\Start-Local.ps1
# In a separate terminal:
.\scripts\Start-Local.ps1 -Reverb
```

Confirm HTTP and Reverb host/port/credentials match each developer's local environment. The script currently uses HTTP port 8024 and Reverb port 8084; that is not proof that a correctly configured Reverb process is running. Keep `.env` local and document placeholders only.

Before marking a task complete:

- Demonstrate the requested behavior with real persisted data and refresh/reopen checks; screenshots alone do not prove backend behavior.
- Test owner/non-owner access, validation, blocked accounts, private media/location boundaries and meaningful failure paths.
- Check desktop 1440/1920, tablet and mobile around 390px; verify keyboard access, focus, visible labels, animation reduction and no clipped content.
- Rehearse register/login → onboarding → photo identification → follow-up → saved plant → note/observation → optional share → two-account Messenger → report submission.
- Mark only completed checkboxes. Leave remaining tasks and configuration dependencies explicit. Provide changed files, migrations, environment-variable names, test results and reproducible steps to the integration lead. Do not include secrets, private database exports or other users' screenshots in the handback.

**Owner confirmation still needed:** approved creator names for Help, final additional reaction labels, and any shared-report changes requested by the two admin developers. These should not prevent work on the already specified tasks.
