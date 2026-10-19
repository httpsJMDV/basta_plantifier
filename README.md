# PlantifierAI

Local botanical workspace for the October 24, 2026 presentation. The master blueprint defines the full intended scope. The shared Git repository is configured; deployment has not been requested. See `amaru.md` for the remaining non-admin work.

## Folder layout

```text
basta_plantifier/
  backend/                 Laravel / PHP
    app/                   Controllers, models, requests, policies, services
    routes/                Web routes and private broadcast authorization
    database/              Migrations and local databases
    resources/views/       Inertia entry template and PDF templates
    public/                Laravel entry point and generated frontend build
    tests/                 PHP integration and security tests
    composer.json          PHP dependencies
    .env                   Local configuration and secrets
  frontend/                React / TypeScript
    src/
      pages/               Inertia screens
      components/          Shared components
      styles/              Botanical design system
      app.tsx              React entry point
    tests/browser/         Desktop/mobile browser journey
    package.json           JavaScript dependencies
    vite.config.js         Builds into backend/public/build
  scripts/                 Startup and source snapshots
  IMPLEMENTATION.md        Verification and remaining work
```

The folders are separate while the app keeps the blueprint's Laravel + Inertia architecture: Laravel handles routing, authentication, storage and server validation; React handles the interface. You open one localhost address.

## Install and configure

PHP 8.3+, Composer, Node/npm and MySQL are required. This workstation already has them.

1. From `backend/`, run `composer install`. If no local `.env` exists, copy `.env.example` to `.env`, then run `php artisan key:generate`. Never overwrite an existing application key.
2. Create a dedicated MySQL database and local database user. Set `DB_CONNECTION=mysql`, `DB_DATABASE=plantifierai`, `DB_USERNAME` and `DB_PASSWORD` in `backend/.env`.
3. From `backend/`, run `php artisan migrate`. Do not run `migrate:fresh` on a database containing work.
4. From `frontend/`, run `npm.cmd install --ignore-scripts`, then `npm.cmd run build`.

The application is configured for MySQL. Use the local server connection you manage in MySQL Workbench and set its username/password in `backend/.env`. For a new installation, run `backend/database/setup-mysql.sql` in Workbench to create the dedicated database, then run Laravel migrations. The local connection is now verified and all migrations have run, creating 30 tables. The earlier SQLite database is preserved; automated tests continue to use isolated SQLite databases. Keep passwords and API keys in `backend/.env`, never in chat or shared source.

## Run locally

Open two PowerShell terminals at the project root:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\Start-Local.ps1
```

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\Start-Local.ps1 -Reverb
```

Open **http://127.0.0.1:8024**. Reverb listens on port 8084. The script enables PHP GD in its process and child processes without modifying the global PHP configuration.

For development with live asset updates, also run `npm.cmd run dev` inside `frontend/`. Continue opening Laravel at port 8024, not the Vite asset-server URL. Stop Vite before checking a standalone build.

## AI, Google and email

Photo identification uses Pl@ntNet first, followed by a text explanation from Groq. Configure these backend-only values in `backend/.env`:

```dotenv
PLANTNET_API_KEY=
PLANTNET_PROJECT=all
GROQ_API_KEY=
GROQ_MODEL=qwen/qwen3.8-27b
GROQ_MAX_COMPLETION_TOKENS=768
ANTHROPIC_API_KEY=
CLAUDE_MODEL=claude-sonnet-5-5
CLAUDE_FALLBACK_ENABLED=true
```

From `backend/`, run `php artisan migrate`, `php artisan config:clear`, and `php artisan plantifier:check-ai --plantnet --claude`. Omit `--claude` if the optional fallback key is not configured. The check verifies provider/model access, not available Claude billing credits. If an Anthropic credential spans workspaces, also set `ANTHROPIC_WORKSPACE_ID` locally. Model availability depends on the actual API account.

Following [Pl@ntNet's identification documentation](https://my.plantnet.org/doc/api/identify), Laravel sends up to three processed photos of the same plant as repeated multipart `images` to `/v2/identify/all`. Omitting `organs` lets Pl@ntNet detect each organ automatically. Uploads support JPEG, PNG and WebP, up to 4 MB each, and are converted to JPEG before identification. Group photos should be cropped to one plant; alternative candidates are competing matches, not an inventory of every visible plant. Pl@ntNet alone supplies scientific/common names, family, scores and candidates. Groq receives text context, never the photos, and writes in the selected Friendly, Formal, Simple or Scientific style. Identification metadata stays in the result card without a duplicate score paragraph in the chat reply. Identification results are stored on the original message so retries and follow-ups reuse them without another identification request. A newer photo batch always gets its own result. Existing history and saved records are retained by additive migrations.

The workspace displays the provider's score on its native 0–1 scale, not as a guaranteed probability. Application caution rules mark a result ambiguous when its leading score is below 0.7 or its margin over the next candidate is below 0.2. No-match responses retain no species name; provider failures remain saved and retryable. Legacy Groq-only suggestions stay in history but are not authoritative Pl@ntNet results.

When Groq returns a quota/rate-limit error, a configured Claude fallback receives the same bounded text context and the same species validation. It runs at most once per request, using [Anthropic's Messages API](https://platform.claude.com/docs/en/api/messages/create), and saves its actual model/provider in identification provenance. Authentication errors, malformed explanations and unsupported species changes do not trigger a paid fallback. Claude requires its own API key and available API credits. Disable it with `CLAUDE_FALLBACK_ENABLED=false`. The Groq output budget is configurable because the current account's output-token limit is lower than the old hardcoded 2,200-token request budget.

Create a Google OAuth client of type **Web application**, then set `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET` in `backend/.env`. Register this exact authorized redirect URI: `http://127.0.0.1:8024/auth/google/callback` (also set as `GOOGLE_REDIRECT_URI`). These credentials are separate from the Groq API key. The login and registration pages show Google sign-in as unavailable until both credentials are set. Run `php artisan config:clear` from `backend/` after changing them. Existing password accounts sign in first and explicitly link their matching Google account in Settings.

Mail defaults to the local log driver. Configure SMTP for actual delivery. Verification and reset links in `backend/storage/logs/laravel.log` are private.

Camera and geolocation require browser permissions and a secure context. A phone using a laptop's LAN IP does not share the laptop's localhost secure origin. Upload and manual coordinate entry remain available. Map tiles, Pl@ntNet, Groq, Claude and Google need internet.

## Super Admin

No administrator credentials are seeded or hardcoded. From `backend/`:

```powershell
php artisan plantifier:admin your-admin-email@example.com --name="Your name"
```

Sign in and open `/admin` to enroll TOTP. Store the one-use recovery codes offline. MFA expires after 30 minutes; destructive actions also require the account password.

Protected recovery resets the same admin's password and MFA, invalidates its sessions and records an audit event:

`php artisan plantifier:admin current-admin-email@example.com --recover`

Database constraints and triggers reject a second administrator, accidental membership replacement and deletion of the singleton row.

## Checks

From `backend/`: `composer test` and `composer audit`.

From `frontend/`: `npm.cmd run build` and `npm.cmd audit`.

Browser checks use Microsoft Edge and a **separate** SQLite database at `backend/database/browser.sqlite`, an app server on port 8025, and Reverb on 8084. Run `npm.cmd run test:browser` from `frontend/` only with that isolated server. The browser journey creates accounts, records and public content; never aim it at your working database.

## Backups

From the root, `powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\Backup-Source.ps1` creates a source-only ZIP under `backups/`. Its explicit allowlist excludes `.env`, credentials, databases, private media, dependencies, caches and Git metadata.

Source snapshots are not data backups. Before presenting, make a protected MySQL backup and separately preserve `backend/storage/app/private` and the existing application key. Use a password prompt or protected MySQL option file, never a password in shell arguments. Restore into a separate rehearsal database and verify login, row counts, saved records and private media before marking backup/restore complete. MySQL backup/restore rehearsal remains pending until credentials are configured.
