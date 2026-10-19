# PlantifierAI implementation tracker

Local demonstration build for October 24, 2026. Team coding is now active in the existing repository. The master blueprint defines the scope; `amaru.md` is the current non-admin handoff. Historical progress notes below may describe earlier states and must be checked against the current code.

## Integration order

1. Session authentication, onboarding, ownership policies, private media.
2. Persistent conversations, Pl@ntNet photo identification, Groq explanations with optional Claude quota fallback, saved Plant Records.
3. Collection, notes, growth observations, private/public maps, authorized export.
4. Public community, reports, friends/following and private messaging.
5. Singleton Super Admin, MFA and narrowly scoped moderation.

## External prerequisites

- MySQL is installed and running but requires credentials. Never put passwords or API keys in this document or chat. Configure the project's local `.env`.
- Pl@ntNet and Groq require separate backend API keys. Optional Claude quota fallback requires an Anthropic key and API credits. Missing configuration produces an honest unavailable state, never a fabricated identification.
- Google OAuth requires a client ID/secret and an exact localhost callback URI.
- SMTP is needed for actual password-reset and verification delivery; local log mail is only for development.
- PHP GD is available as a DLL but is not globally enabled. Local start/test scripts enable it explicitly for image re-encoding.

## Model verification

Pl@ntNet is now the sole photo-identification authority, following https://my.plantnet.org/doc/api/identify . Groq receives only structured text context and provides descriptions, care guidance and conversation. The configured Qwen model uses instruct mode and a 768-token output cap to fit the verified account limit. A Claude fallback is called only on Groq quota/rate-limit errors, with the same identity validation. Models remain configurable; verify actual account access with `php artisan plantifier:check-ai --plantnet --claude`. Documentation availability does not establish account credits or quota.

## Completion policy

Only checked and integrated behavior is marked complete. Module interfaces, screens, and migrations alone are not a completed feature. The final status below is updated after verification.


## User-requested folder layout

Laravel now lives in `backend/`; React/TypeScript, Vite and npm dependencies live in `frontend/`. Frontend builds target `backend/public/build`. Inertia pages resolve from `frontend/src/pages`. Root startup and source snapshot scripts are updated accordingly. Keep backend secrets exclusively in `backend/.env`.

## Verified locally

- TypeScript and Vite production build pass with the separate folder layout.
- PHP integration/security suite: 61 tests covering hybrid identification, cached retries, Claude quota fallback and insufficient-credit handling, alongside existing security and application behavior.
- Browser journey passes on desktop and 390px mobile: registration/onboarding, saved chat with an honest unavailable-provider state, manual plant record, growth observation, note, private map pin, public-map exclusion, public post, friend request/acceptance, and two-account live Reverb messaging.
- Browser regression checks cover escaped chat text, escaped Leaflet tooltip content, mobile composer visibility, and collection overflow.
- Backend coverage includes mocked AI identification through saved record, chat deletion independence, provider errors/schema validation, bounded context, image MIME checks and metadata removal, ownership/IDOR, map generalization, CSV/PDF permissions, CSV formulas, shares/revocation, story expiration, blocking, suggestions, private channel authorization, singleton admin triggers, TOTP replay protection, one-use recovery codes, and narrow audited report evidence.
- Frontend development entry is served through Vite while Laravel continues to serve the app. The compiled build also loads without Vite.
- Current Composer and npm audits report no known dependency vulnerabilities.

## Configuration and acceptance still required

- Rehearse the full presentation journey against MySQL with real user accounts. MySQL credentials, all migrations, and the 34-test security/integration suite now pass, including database triggers and the generated friendship-pair column.
- Live Pl@ntNet + Groq has passed photo upload, uncertainty/score display, conversational follow-up, collection save with hybrid provenance, and mobile layout using an image from Pl@ntNet's official documentation. Rehearse the intended presentation plants as well. Claude fallback has passed mocked integration tests, and the configured key passes live model access checks. Anthropic generation currently rejects the account for insufficient API credit balance; add API credits before relying on live fallback.
- Configure and exercise Google OAuth and SMTP verification/reset delivery with actual accounts.
- Create the single real Super Admin through the protected command and enroll the intended account's authenticator.
- Rehearse protected MySQL/media backup and restore. Source snapshots exclude private runtime data.
- Physically check camera/geolocation permission flows in the intended presentation browser/device.

## Remaining blueprint details

- Profile avatar upload and configurable profile/follower/activity visibility.
- Controlled observation/location cards in community and Messenger; current structured shares cover Plant Records.
- Gallery pin photos, note links to pins/chats, optional story plant links, and reopening closed identification requests.
- Editable onboarding purpose/experience in Settings; these currently persist from onboarding.
- Admin upload-rejection/throttling security event logging, visible account-warning delivery, and backup-status tracking.
- Exported images and selectable export fields beyond the current documented record/observation scope.

Existing optional choices such as custom collections and note autosave can be added after the required connected flows and remaining items above are accepted. No synthetic community content or accuracy percentages have been introduced into the working database; browser fixtures live in a separate test database.


## MySQL configuration correction

The application default and local `backend/.env` now select MySQL (`plantifierai` on `127.0.0.1:3306`). No fallback to SQLite is used for the application. A connection check reached the running MySQL server but was rejected because the local password is not configured. Set the credentials from the intended Workbench connection in `backend/.env`; create the database using `backend/database/setup-mysql.sql` and run migrations afterward. The earlier SQLite files remain intact, and automated tests explicitly use isolated SQLite. No MySQL migration or live connection success is claimed yet.


## Live MySQL and Groq verification completed

Removed duplicate DB settings in the local `.env`: an empty trailing password had overridden the populated entry. Preserved the user-provided password and API key without displaying either. The application uses `plantifierai`; all five migrations ran and created 30 tables. All 34 tests (162 assertions) also passed in a separate temporary MySQL database, which was removed afterward. The working database was preserved. Groq authenticated successfully, listed the configured model, and returned HTTP 200 with a valid no-plant result for a generated neutral calibration image. Next acceptance step: a real plant photo through the signed-in workspace and saved collection.
