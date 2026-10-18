# PlantifierAI — Master Project Blueprint (Local-First)

**Version:** 4 · **Presentation:** Saturday morning, October 24, 2026  
**Product:** Responsive web platform, desktop and mobile browsers; **not** a native mobile application.  
**Development policy:** **NO GIT YET.** Build, integrate, and test locally first. No `git init`, commits, remotes, GitHub account switching, or branch setup required during development. GitHub handoff is a later decision.  
**Scope:** 20 selected user-facing feature areas, one dedicated Admin System, and shared security architecture. No rigid day-by-day schedule.

> This document defines intended behavior and implementation contracts. A feature described here is not automatically implemented. Favor a reliable connected flow over disconnected demo screens.

## 1. Product identity

PlantifierAI is a botanical discovery and community workspace: identify a plant from an image, discuss it with an AI assistant, preserve the discovery as a personal record, attach observations and optional location, and selectively share with a community or friends. The default signed-in landing screen is the **AI Workspace**, not a metrics dashboard.

### Core user journey

1. Register/login (email/password or Google OAuth); complete skippable first-time onboarding.
2. Open AI Workspace; upload an image, use a supported browser camera, or type a question.
3. Receive a suggested identification with evidence/limitations and optional alternatives; continue the same chat.
4. Save a reusable Plant Record; reopen it later independently of the AI provider.
5. Add personal notes, images, growth observations, and an optional **private-by-default** map pin.
6. Deliberately publish a selected post, observation, or generalized location; participate in community, friends, and Messenger.
7. Export permitted botanical records.

### Main navigation

- **Primary sidebar:** New Chat; AI Workspace; My Plant Collection; Gallery & Location; Notes & Journal; Community; Messages; Recent Chats; Profile/Settings.
- **AI Workspace right panel:** collapsible **Session Timeline** for events in the *selected conversation* (uploaded image, result, follow-up, saved record); this is distinct from cross-conversation chat history in the sidebar.
- **Mobile:** compact top bar, drawers/sheets for navigation and timeline; composer stays accessible without covering content.
- **Community subsections:** Feed, Friends, Requests, People You May Know, Following, Stories, Identification Requests.
- **Admin:** separate authorized `/admin` area, not an ordinary user navigation tab.

## 2. Technology and local environment

| Layer | Selected approach |
|---|---|
| Frontend | React + TypeScript, Inertia.js |
| UI | Tailwind CSS, shadcn/ui selectively, Lucide React |
| Backend | Laravel/PHP; controllers, Form Requests, Policies, service classes |
| Database | MySQL with migrations and relational constraints |
| Auth | Laravel session authentication, Google OAuth via Socialite |
| AI | Groq **currently available image-capable model**, called only by Laravel via replaceable provider interface |
| Realtime | Laravel Reverb + Echo, authorized private channels |
| Maps | Leaflet and OpenStreetMap-based tiles; obey tile provider policy |
| Images | Laravel Storage; private/public separation |
| Tooling | Composer, Node/npm, Vite, local PHP/MySQL/Reverb |

**Connectivity:** Groq, Google OAuth, and online tiles need internet even if the app runs locally. Camera/geolocation require permission and browser secure-context rules; `localhost` on a laptop is not the same origin as its LAN IP on a phone. The presentation is localhost-only; no production hosting, public domain, or deployment work is assumed. Check Groq's actual model availability/quotas before coding; a provider is not a specific vision model.

## 3. Detailed feature specifications (20)

Each module should have proper loading, empty, success, validation, permission-denied, and error states.

### 01 — First-time onboarding
- First successful login shows a brief, skippable flow: preferred display name (“What should we call you?”), purpose (discover/grow/research/connect), experience level, optional plant interests.
- Optional location discovery: manual city/municipality selection or opt-in approximate location; **not** mandatory GPS.
- Persist completion/version and preferences; editing later in Settings; never loop users through onboarding on each login.

### 02 — Authentication and Google sign-in
- Email/password registration/login, verification where appropriate, password reset, logout, profile editing, Google OAuth.
- Validate uniqueness, password rules, and OAuth state; account linking must not silently merge unrelated accounts.
- Laravel sessions, throttling, session regeneration, protected routes; only one Super Admin created separately.

### 03 — AI Workspace and chat history
- Conversational center with message bubbles/blocks, multiline composer, upload/camera affordances, sending/streaming or pending state.
- Sidebar: New Chat, searchable recent conversations, auto-title with manual rename, reopen and delete with confirmation.
- Right Session Timeline lists important events within current chat, not all chats. Clicking an event scrolls to the related message.
- Persist messages in MySQL. Context sent to AI is bounded/summarized; deletion and saved Plant Records have distinct lifecycles.

### 04 — Plant image identification
- Browser image upload and `getUserMedia` camera capture where supported; preview and remove before submit.
- Backend validates/re-encodes image, calls a vision-capable model, normalizes result into common/scientific name when supportable, possible family, traits, habitat, care, safety/ecological notes, and uncertainty.
- Permit unidentified/ambiguous results and alternative candidates. Do not fabricate confidence percentages, certainty, or unsupported scientific claims.
- User can ask follow-ups and save a result as a Plant Record; provider errors and quotas must not delete user content.

### 05 — My Plant Collection
- Save/edit/archive/delete owned records; grid/list, search, filters, favorite, optional custom collections.
- Plant detail: cover image, names, taxonomy if available, description, care/safety/ecology, identification provenance, date, original conversation, related notes/observations/map.
- Optional relationship label (growing, discovered, researching, wishlist); one species may have many distinct personal records.

### 06 — Gallery & Location
- Two explicit contexts: **My Private Map** and **Public Discovery Map**.
- Plant-linked pins with photos, editable description, observation date, optional coordinates/general area, privacy indicator.
- Default private. Public approximate shares generalized geography; public exact requires explicit informed confirmation and sensitive-species safeguards.
- Strip unnecessary GPS EXIF from shared photos; no private coordinates in public APIs, cards, shares, exports, or broadcasts.

### 07 — Notes & Journal
- Standalone notes with title/body, creation/edit dates, search and optional links to plant/pin/chat.
- Draft/autosave where practical; private by default. User can turn selected chat content into a note.
- Journal entries may be chronological; note ownership enforced in every read/write route.

### 08 — Community posts and comments
- Feed with text, images, optional linked public Plant Record, author, timestamp, comments, reactions if included.
- Create/edit/delete own posts, pagination, reporting, blocking, moderation status.
- Public posts never implicitly expose a private linked record or exact private location.

### 09 — Real-time Messenger
- One-to-one conversation list, unread state, message timestamps, text and approved image/plant-card attachments.
- Laravel Reverb/Echo with membership-authorized private channels and MySQL persistence.
- Block enforcement, rate limits, reporting specific messages; admins do not casually browse private conversations.
- Do **not** claim end-to-end encryption unless actually implemented.

### 10 — Plant and location sharing
- Share structured Plant Record previews and permitted observation/location cards into Messenger or Community.
- Server resolves permissions when creating **and opening** shares; revoking visibility invalidates access.
- Never copy exact private coordinates into a publicly readable payload.

### 11 — Image stories
- Photo/story upload, optional caption/plant link, author, audience setting, 24-hour expiration behavior.
- Story viewer with next/previous, expiry-aware queries, report/block controls, safe storage.
- Expired stories stop appearing even if cleanup jobs have not yet physically deleted files.

### 12 — User profiles and following
- Username/display name, avatar, short bio, optional general municipality, interests, public plant/posts summary.
- Follow/unfollow; distinguish one-way following from mutual friendship.
- Profile, follower/following and activity visibility settings; avoid exposing private collections.

### 13 — Community identification requests
- Post an image with an “Help identify this plant” status and optional generalized area/context.
- Community proposes species with explanation; requester can accept/close or request more input.
- Community suggestion is not an automatic scientific certification.

### 14 — Public map search and filters
- Search public discoveries by plant name, area, date/category where stored; filter and open pin previews.
- Paginate or cluster pins for performance; only query public/authorized locations.
- Never include private pins in counts, autocomplete, or returned map bounds.

### 15 — AI uncertainty and correction reporting
- AI response supports unknown, ambiguous, alternatives, and “Report incorrect identification.”
- Record original AI suggestion, user-proposed correction, reason, status, and timestamps.
- Correction workflow does not silently rewrite historical AI messages or imply a verified species.

### 16 — Community verification
- Community members can submit evidence-backed suggestions or votes; display provenance and status.
- Separate “community-supported” from “expert/scientifically verified”; do not confer expert authority without a real expert process.
- Prevent duplicate votes and self-approval where relevant; report/moderation controls.

### 17 — Plant growth journal
- Dated observations per personal Plant Record with photos, text, optional growth measurements.
- Chronological timeline, before/after comparison where images exist, edit/delete owned entries.
- Observations can optionally be linked to a private map pin without automatically publishing it.

### 18 — Botanical record export
- Export owned/authorized records to PDF and CSV, including selected fields and permitted images/observations where supported.
- Preview scope; omit secrets, hidden coordinates, other users' private notes, and unauthorized linked content.
- Sanitize CSV cells against spreadsheet formula injection; validate record ownership at export time.

### 19 — Friends system
- Search users, send/cancel/accept/decline friend requests, unfriend, mutual-friend display, block.
- Pending requests must not duplicate; blocking suppresses relevant interactions and recommendations.
- Friends and Following remain distinct relationships. Friend-request privacy: everyone/friends-of-friends/nobody.

### 20 — Friend suggestions
- Explicit opt-in location discovery. Start with user-selected municipality/general area; automatic approximate location is optional later.
- Proposed deterministic ranking: general-area match **40%**, mutual friends **25%**, shared plant interests **20%**, shared community activity **15%**; tune after actual testing.
- Exclude self, blocked accounts, existing friends, pending requests, and opt-outs. Do not expose coordinates, precise distance, address, or exact location.
- Explain suggestions in broad language (“Same general area”, “Mutual friends”); let users turn discovery off, which removes them from nearby suggestions.

### Shared domain design
Use a reusable **Plant Record** with related images, AI identification provenance, chat references, notes, dated observations, and optional map location. Posts and messages link through controlled share objects, not duplicated unrestricted database blobs. Every relationship still checks ownership, visibility, and revocation independently.

## 4. Administration: exactly ONE Super Admin

**Hard rule:** Exactly one Super Admin in the system; no public admin signup, no additional admin roles for this project. Create with a protected Laravel setup command and enforce the singleton at database and server authorization levels (not only frontend). Prevent accidental deletion and define a protected recovery/replacement procedure. `marubalmes` is assigned the Admin workstream for later collaboration, **not automatically the actual Super Admin account holder**.

**Admin modules:**
1. Dashboard: users, plant records, report queue, AI usage/error summaries, recent admin events.
2. User management: search, warning, suspension/ban, review reported account activity.
3. Moderation: posts, comments, stories, identification requests; pending/reviewed/resolved/dismissed states and reasons.
4. Public map review: reports, misleading/duplicate pins, sensitive species locations, removal/generalization.
5. AI management: model/provider configuration via server environment, quotas, request/error metrics, maintenance switch; never display raw keys.
6. Audit/Security Center: admin actions, failed logins, throttling events, rejected uploads, backup status.
7. System settings and platform announcements.
8. Admin MFA (TOTP/recovery codes), reauthentication for destructive actions, protected `/admin` routes.

**Privacy boundary:** No unrestricted admin access to private messages, private notes, or private pins. Authorized reported-content and recovery workflows should be narrow and logged.

## 5. System-wide security and validation

**Build these into shared foundations, even on localhost.**

- **SQL injection:** Eloquent or parameterized queries for every database input; allowlist column/sort names; never concatenate untrusted raw SQL.
- **Input validation:** Laravel Form Requests with types, length, enum, numeric ranges, uniqueness, ownership, and file constraints. React validation is only for UX.
- **XSS:** escaped React rendering, sanitize permitted rich text, never trust AI/user HTML; safe link handling.
- **CSRF and sessions:** Laravel CSRF middleware, secure cookie/session configuration appropriate to the local origin, session regeneration after login and privilege changes.
- **Authorization/IDOR:** Policies/Gates for read/write/delete/share/export, including nested records; public visibility never grants edit access.
- **Rate limits:** per-account and/or per-IP on login, registration, reset, AI requests, upload, posts, comments, messaging, friend requests. Respond with 429 and retry feedback. Proposed starting values only: login 5/min, registration 3/hour/IP, AI image 10/hour/user, AI text 30/hour/user, uploads 20/hour/user, posts 10/hour/user, comments 30/hour/user, Messenger 30/min/user. Calibrate to actual provider quotas.
- **Uploads:** verify actual MIME/content and size/dimensions, approved image types, randomized filenames, re-encode/strip metadata where practical, private storage authorization; never execute uploads.
- **AI security:** backend-only secrets, provider abstraction, bounded prompt/image size and context, response schema validation, timeouts and quota handling; no AI-issued SQL or privileged actions.
- **Realtime:** authorize every private channel and message broadcast; block enforcement and membership checks.
- **Map privacy:** default private, opt-in discovery, sensitive species handling, no coordinate leakage through API, exports, media metadata, or notifications.
- **Admin:** singleton enforcement, MFA, audit logging, no hardcoded credentials.
- **Configuration:** `.env` never shared/published, `.env.example` placeholders, `APP_DEBUG` not used to expose secrets to other viewers; dependency review (`composer audit`, `npm audit`).
- **Testing:** SQLi, XSS, CSRF, IDOR, private coordinate leakage, malicious upload rejection, rate limits, OAuth linking, WebSocket access, singleton admin, export authorization, backup/restore.

## 6. Design direction: editorial botanical, not generic “AI slop”

**Design goal:** A considered botanical field notebook combined with a calm contemporary research tool. The AI Workspace should feel conversational and precise, while plant records feel like carefully typeset specimen entries. Avoid copying Claude/ChatGPT screens literally.

### Recommended palette (light-first)

| Token | Hex | Usage |
|---|---|---|
| Canvas / warm paper | `#F8F7F2` | Main app background |
| Surface / porcelain | `#FFFFFF` | Cards, composer, panels |
| Forest / primary | `#244D3A` | Primary actions, selected states |
| Deep forest | `#19382A` | Hover and high-emphasis accents |
| Sage | `#A8BFA5` | Muted illustration/accent, not tiny text |
| Mist | `#DCE8D5` | Soft selection and tags |
| Ink / charcoal | `#242923` | Primary text |
| Slate olive | `#58635A` | Secondary text |
| Hairline | `#DFE3D9` | Borders/dividers |
| Clay / warm accent | `#B46D4E` | Sparing botanical annotation/alerts, not generic gradient |

Use accessible contrast, visible focus rings, and state colors that are not conveyed by color alone. Test contrast rather than assuming every palette pairing works. A future dark mode can use near-black green-tinted surfaces; do not spend early effort on a decorative theme toggle.

### Typography and layout
- Headings: **Fraunces** (or another restrained editorial serif) for specimen headings and occasional page titles, **not** every navigation label.
- Interface/body: **Inter** or **Manrope** for clear compact UI; scientific names italicized correctly.
- Spacious but disciplined 8px spacing system; readable content widths; deliberate hierarchy rather than huge hero text everywhere.
- Use thin borders and restrained shadows; corner radius mostly 8–12px, not giant floating pills on every component.
- Photos are the primary visual emphasis. Use natural image aspect ratios, restrained captions, and species taxonomy labels.
- Use shadcn/ui as accessible primitives, **not** as a stock dashboard template. Customize component composition and tokens.

### Specific page compositions
- **Login:** quiet two-column or centered layout; crisp form, small botanical detail, no giant abstract AI gradient/orb.
- **Onboarding:** one question per compact step, clear skip/back, understated transition; avoid gamified confetti.
- **AI Workspace:** conversation centered; practical left nav and optional right Session Timeline; stable composer with image preview; no fake suggested prompts dominating the page.
- **Plant Collection:** specimen cards with strong photos, name/taxonomy, observation metadata; detail view reads like a digital field sheet.
- **Gallery & Location:** map is genuinely primary, with a coordinated list/preview panel and clear private/public switch; avoid a generic dashboard with a tiny map widget.
- **Notes:** comfortable reading/writing column and unobtrusive linked-record metadata.
- **Community:** human-scale feed, identity and provenance, readable posts; avoid imitation engagement counters or decorative social noise.
- **Messenger:** compact conversation list + conversation panel; on mobile use a dedicated conversation screen.
- **Admin:** deliberately utilitarian and denser than the user app: clear queues, filters, evidence, action reasons, and audit trails.

### Anti-generic design rules
1. No purple/teal neon gradients, floating glassmorphism cards, AI brain imagery, random sparkles, or meaningless hero statistics.
2. No fabricated plant photos, scientific certainty, community counts, or placeholder “98.7% accuracy” claims.
3. No identical card grid on every screen; each module should follow its task.
4. Use one consistent icon family (Lucide), a small palette, and few typography roles.
5. Prefer real content states: empty collection, uncertain result, no nearby suggestions, permission denied, AI unavailable.
6. Motion is functional (panel open, upload progress, timeline navigation), subtle, and respects reduced-motion settings.
7. Mobile layouts are intentionally designed, not desktop screens merely shrunk.

## 7. NO GIT YET — local development and later one-repository handoff

**Immediate policy:** Develop on one computer, in one project directory. No Git initialization, commit, push, branch setup, account switching, or repository deletion required while coding. Keep safe local backups/versioned ZIP snapshots; never ZIP `.env`, keys, credentials, private database dumps, `node_modules`, or `vendor` for sharing.

**Later, once code is integrated:** create **one** repository owned by `httpJMDV`. The owner will decide the precise GitHub handoff with classmates. ZIP delivery of source code is allowed; a ZIP without `.git` has no Git history, but does not itself establish authorship. Do not create four unrelated repositories or delete a repository merely to alter contribution statistics. Preserve truthful records of actual coding, testing, reviewing, documentation, and presentations.

### Responsibility allocation (planning, not Git branches yet)

| Assigned account | Five feature areas |
|---|---|
| `httpJMDV` | 02 Authentication; 01 Onboarding; 03 AI Workspace; 04 Identification; 15 AI corrections |
| `KingOrdona` | 05 Collection; 06 Gallery & Location; 14 Map search; 17 Growth journal; 18 Export |
| `liamrubiales-dev` | 07 Notes; 08 Community feed; 11 Stories; 13 Identification requests; 16 Verification |
| `marubalmes` | 09 Messenger; 10 Sharing; 12 Profiles/Following; 19 Friends; 20 Friend suggestions |

**Additional dedicated workstream:** `marubalmes` — the complete Admin System. `httpJMDV` remains integration lead. These are intended areas of responsibility and future review/presentation, not claims about who has already authored code.

**Integration contracts before modules:** auth and profiles → Plant Record schema → AI/collection/map/notes → Community and friendship → Messenger/sharing → admin moderation. Agree on migrations, routes, policy naming, and shared UI components before dependent work. Track module state locally in a simple checklist (not a fixed schedule).

## 8. Local demonstration acceptance criteria

- Local PHP/Laravel, Vite, MySQL, and Reverb run reliably; required internet-connected integrations checked in advance.
- Login/onboarding and return visits work; exactly one Super Admin can exist.
- Browser upload/camera where supported → AI response (including uncertainty/errors) → saved Plant Record → collection/detail.
- Private pin stays private across API, public map, share, export, and image metadata.
- Notes/growth observations link correctly; public posts and community reports have proper ownership/moderation.
- Friend requests, suggestions (including opt-out), and two-account private Messenger work with authorization.
- Admin can moderate reported public content, inspect audit/security events, and control AI usage without unrestricted private-data browsing.
- Responsive layouts work on intended demonstration browsers; empty/error states are present.
- Security regression tests cover validation, injection, access control, uploads, rate limits, and singleton admin.

**Not in current scope:** production hosting, public domain/TLS deployment, offsite infrastructure, extensive synthetic community seeding, care reminders, plant comparison, or offline drafts. These can be reconsidered later without silently adding them to the confirmed 20.