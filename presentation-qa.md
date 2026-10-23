# PlantifierAI: security Q&A for the presentation

Likely professor questions about **security, privacy and safety**, with short answers you can say out loud. Each answer is based on how the code actually works. **Show it** points to the file to open if they ask to see the code.

Tip: answer in 2–3 sentences, then stop. If a question hits a real weakness, say so and give the fix (see [section 9](#9-honest-limitations-and-what-we-would-do-for-production)). Professors respect "here is the limit and here is the fix" more than a bluff.

---

## The 30-second security overview (say this first)

> PlantifierAI protects users in layers. **Accounts**: hashed passwords, login rate limits and secure sessions. **Access control**: every plant, note, chat and photo is checked against its owner on the server. **One protected Super Admin**: MFA, password re-entry for sensitive actions, and an audit trail of every decision. **Uploads**: photos are verified and re-encoded, which strips hidden data like GPS. **AI**: the chatbot never sees photos, treats user text as untrusted, and its answers are validated before saving. **Location privacy**: places are private by default, and sensitive locations are blurred to about 11 km.

---

## 1. Accounts, passwords and sessions

**Q: How do you store passwords?**
A: We never store them as plain text. Laravel hashes them with **bcrypt** (12 rounds), a one-way slow hash, so even we can't read them. New passwords need at least **10 characters with letters and numbers**.
*Show it:* `backend/app/Models/User.php` (`'password' => 'hashed'`), `backend/app/Http/Controllers/AuthController.php`.

**Q: What stops someone from guessing passwords (brute force)?**
A: Rate limits. Login allows **5 attempts per minute** for each IP address and email together. Sign-up allows **3 per hour** per IP. Every failed login is written to the audit log as `auth.failed`, and blocked requests are logged as `security.throttled`.
*Show it:* `backend/app/Providers/AppServiceProvider.php` (`RateLimiter::for`).

**Q: How do sessions work? What about session hijacking?**
A: Sessions are stored server-side in the database. The browser only holds a session cookie that JavaScript can't read (**HttpOnly**) and that isn't sent on cross-site requests (**SameSite=Lax**). The session ID is **regenerated at login**, which blocks session fixation, and sessions expire after **120 minutes**. Logging out destroys the session and issues a new CSRF token.
*Show it:* `backend/config/session.php`, `AuthController.php` (`login`, `logout`).

**Q: How is "Sign in with Google" kept safe?**
A: Google handles the password. We only accept accounts whose email **Google has verified**. We also never silently merge accounts: if an email already has a password account, that user must sign in normally and link Google from Settings. Suspended accounts can't sign in through Google either.
*Show it:* `AuthController.php` (`googleCallback`).

**Q: Can someone reset another person's password?**
A: No. Reset links are sent only to the account's email. The token is single-use and **expires after 60 minutes**, the request is rate-limited, and the new password must follow the same rules.

---

## 2. Access control: who can see what

**Q: If I change the ID in the URL, can I see another user's plant, chat or export? (IDOR)**
A: No. Every request is checked **on the server** against the logged-in owner, using Laravel **policies**. For example, a plant record can only be viewed, edited, deleted or exported by its owner. The export endpoint checks **every** requested ID before it builds the file. Notes and chats are only ever queried by the current user's ID.
*Show it:* `backend/app/Policies/PlantRecordPolicy.php`, `ConversationPolicy.php`, `backend/app/Http/Controllers/ExportController.php` (`Gate::authorize('export', …)`).

**Q: Are uploaded photos public?**
A: No. They are stored **outside the public web folder**, on Laravel's private disk, under random UUID names. Every image request goes through a controller that checks permission: your own photo, a published post, a friend's story, a public pin, or a message thread you belong to. Responses are sent with `X-Content-Type-Options: nosniff` and `Cache-Control: private, no-store`.
*Show it:* `backend/app/Http/Controllers/MediaController.php`.

**Q: Can someone listen in on another person's live messages?**
A: No. Live updates use **private channels**, and the server checks every subscription. You can only join your own inbox channel and threads you are part of. Suspended users are refused.
*Show it:* `backend/routes/channels.php`.

**Q: What happens to a suspended user?**
A: They are signed out immediately: their sessions are deleted, their "remember me" token is replaced, and every later request is blocked by the `AccountActive` middleware. They also lose live-chat access.

---

## 3. Admin security

**Q: How many admins are there, and how is one created?**
A: **Exactly one Super Admin**, enforced by the database itself: a `super_admin` table that allows one row only, with a unique user and deletion blocked. The admin can't be created or promoted from the website. Only the server command `php artisan plantifier:admin` can do it, and it refuses to promote an existing user. The admin account also can't be suspended, even by the admin.
*Show it:* `backend/app/Console/Commands/CreateSuperAdmin.php`, `backend/app/Http/Middleware/SuperAdmin.php`.

**Q: Does the admin use multi-factor authentication?**
A: Yes, in production. The admin uses an authenticator app (time-based one-time codes, **TOTP**), with backup recovery codes that are stored hashed. A code can't be reused (replay protection). The MFA check expires after **30 minutes**, and sensitive actions also ask for the **password again** (step-up authentication). The authenticator secret is **encrypted** in the database.
*Show it:* `AdminController.php` (`verifyMfa`), `backend/app/Services/AdminSecurity.php`.

**Q: Then why didn't it ask for MFA during your demo?**
A: Because we run in **local development mode** (`APP_ENV=local`) to keep the demo smooth. The check is a single switch: it turns on **automatically in any non-local environment**, or with `ADMIN_MFA=true` locally. We can turn it on live if you'd like to see it.
*Show it:* `backend/config/botanical.php` (`admin_mfa`).

**Q: Can the admin read users' private chats, notes or collections?**
A: No. The admin sees **counts only** for private data. For moderation, the admin sees only the **specific item that was reported**, and opening that evidence is itself logged.
*Show it:* `AdminController.php` (`evidence`), `backend/app/Services/AdminOverview.php`.

**Q: How do you know what the admin did?**
A: There's an **audit trail**. Every admin action requires a written reason and is recorded with who did it, when, and to what: warnings, suspensions, moderation decisions, location changes, settings, AI key changes and backups. Security events are logged too. The log uses an **allowlist of fields**, so passwords, API keys and chat text never end up in it.
*Show it:* Admin > Audit & security, `backend/app/Services/SecurityAudit.php`.

**Q: What if the admin account is compromised?**
A: In production, MFA makes a stolen password alone useless. If it still happens, `php artisan plantifier:admin <email> --recover` resets the password, wipes the MFA setup and signs out all of the admin's sessions. The recovery itself is audited.

---

## 4. Common web attacks

**Q: Is the system vulnerable to SQL injection?**
A: No. All database access goes through Laravel's query builder and Eloquent, which use **parameter binding**, so user input is never pasted into SQL. The few raw SQL fragments are fixed strings or use bound parameters. The search box even escapes `%` and `_` wildcards.
*Show it:* `backend/app/Services/ConversationHistory.php` (search uses `?` placeholders).

**Q: What about XSS (cross-site scripting)?**
A: React **escapes all text** before showing it, and the project has **no** `dangerouslySetInnerHTML` anywhere. Announcements, posts, comments and AI replies are stored and shown as plain text. A browser test posts `<img src=x onerror=…>` and confirms nothing runs.
*Show it:* `frontend/tests/browser/plantifier.spec.ts` ("escaped content").

**Q: And CSRF?**
A: Laravel's CSRF protection covers **every** form and request that changes data, with no exceptions configured. The frontend sends the `X-XSRF-TOKEN` header on every request, and the cookie is SameSite=Lax.

**Q: Couldn't someone upload a malicious file disguised as an image?**
A: We check the **real file content**, not the file name. Only JPEG, PNG and WebP pass, up to **4 MB** and **16 megapixels**. Then we **re-draw the image into a brand-new JPEG** with the GD library. Anything hidden inside the original, such as scripts, odd file structures or **EXIF data including GPS location**, is discarded. Files get random names, and rejected uploads are logged as `security.upload_rejected`.
*Show it:* `backend/app/Services/ImageStore.php`.

**Q: How do you stop spam or someone abusing the AI?**
A: Per-user rate limits:

| Action | Limit |
|---|---|
| Login, password reset, MFA codes | 5 per minute |
| Sign-up | 3 per hour per IP |
| AI chat | 30 per hour (text), 10 per hour (photos) |
| Community posts | 10 per hour |
| Photo uploads | 20 per hour |
| Other changes (comments, reactions, edits) | 30 per minute |
| Reports, verification emails | 6 per minute |

The admin can also **pause all AI** with one switch.

**Q: Your server calls outside services. Could that be abused (SSRF)?**
A: The app only calls **fixed provider addresses** (Pl@ntNet, Groq, Anthropic and OpenStreetMap). Users can't supply a URL. Redirects are **disabled** and every call has a timeout.

---

## 5. AI security and safety

**Q: What about prompt injection, a user telling the AI to ignore its rules?**
A: Several layers:
1. The instructions tell the model that user messages and quoted text are **untrusted data, never instructions**.
2. The model must reply in a **strict JSON format**, which we validate. Anything else is rejected.
3. Only **Pl@ntNet** identifies plants. If the AI's species name doesn't match Pl@ntNet's, we **reject** the reply.
4. The AI can't take actions. It only returns text, which is shown as plain text.

*Show it:* `backend/app/Services/GroqProvider.php` (system prompt + `Validator::make`).

**Q: Does the AI see users' photos or personal details?**
A: No. **Photos go only to Pl@ntNet** for identification. The text AI (Groq, or Claude as backup) receives the conversation text, the Pl@ntNet result, and the user's chosen writing style and experience level. It never receives names, emails or images.

**Q: Could the AI give dangerous advice, like saying a plant is safe to eat?**
A: It's told never to advise eating a plant identified from a photo, and every identification gets a fixed safety line: "Do not consume any plant based on an image identification." Scores are labelled as ranking scores, not guarantees. Users can **propose corrections**, which the admin reviews without changing the original record.

**Q: Where are the AI API keys? Can users see them?**
A: Keys exist **only on the server**, either in `.env` or added through the admin page. Admin-added keys are **encrypted** in the database with AES-256 using the app's secret key, and the page only ever shows the **last 4 characters**. Error messages are cleaned so a key is never shown or logged, which matters because Pl@ntNet's key travels in the URL.
*Show it:* `backend/app/Services/AiKeyPool.php`.

**Q: What if a key runs out or gets leaked?**
A: Requests rotate through several keys. A key that hits its limit **rests** until the limit resets, and the next one takes over. If every key is out, the backup provider answers. If a key leaks, the admin removes it with one click and revokes it at the provider. A key that the provider rejects is marked **invalid** automatically.

---

## 6. Privacy and location data

**Q: Couldn't someone use the map to find and steal rare plants (poaching)?**
A: Locations are **private by default**. Sharing publicly is opt-in, with two levels:
- **Approximate**: coordinates are rounded to about **11 km**.
- **Exact**: the user must tick a confirmation box first.

Marking a place **"sensitive" forces approximate**. Even the admin only sees blurred coordinates for those, and can blur or hide any public pin.
*Show it:* `backend/app/Http/Controllers/MapController.php` (`round(…, 1)`).

**Q: The app looks up your city from GPS. Does that leak your location?**
A: Coordinates are rounded to about **110 m** before being sent to OpenStreetMap. Lookups are cached and limited to 1 per second, and the city can also be typed by hand.

**Q: What personal data do you keep, and can users control it?**
A: We keep the account (name, email, optional bio and city), plant records, notes, chats and photos. Users choose who sees their **profile, connections and activity** (everyone, friends or private) and who can send friend requests. They can **block** people. They can also edit and delete their records and **export** their own collection as CSV or PDF.

---

## 7. Secrets, stored data and backups

**Q: Where are passwords and secrets like the database password or API keys kept?**
A: In `backend/.env`, which is **excluded from Git**. The repository only has `.env.example`, a template with blank values. The app's secret `APP_KEY` encrypts sessions, cookies and the encrypted database fields.

**Q: What is encrypted in the database?**
A: The admin's **MFA secret**, the **recovery codes** (also hashed) and **admin-added AI keys** are encrypted. **Passwords** are hashed, which is one-way and different from encryption.

**Q: Your GitHub repo is public and includes a database file. Isn't that dangerous?**
A: We cleaned that file before publishing. It contains **only the Super Admin and 300 fake demo accounts** (`@plantifier.test`). We removed every real account, private chat, photo, session, backup record and API key, then checked that no real email addresses remain. The demo admin password is for the demo only. In production it would be changed with the recovery command.

**Q: How do you back up the system, and how do you know a backup works?**
A: The admin can back up the **source code, the database and private photos**. Each backup is a ZIP file with a **SHA-256 checksum**. The source backup uses an allowlist that excludes `.env`, private photos and database files. A **restore check** rebuilds the latest backups in a separate temporary database. It then confirms row counts, account ownership and every photo's checksum, and **never touches the live data**. The backups folder is excluded from Git.

---

## 8. Testing and verification

**Q: How did you test the security?**
A: There are **134 automated backend tests**, including:
- Every admin page and action is refused for normal users.
- MFA, step-up and replay checks.
- Rate limits.
- Rejected uploads.
- Private evidence stays scoped to the reported item.
- API keys never appear in the page, logs or error messages.

There are also **19 browser tests** that click through real flows in Microsoft Edge, including two-account messaging and the admin. A dependency scan on **Oct 24, 2026** (`composer audit` and `npm audit`) found **0 known vulnerabilities**.

---

## 9. Honest limitations and what we would do for production

| Limitation today | Why | Production fix |
|---|---|---|
| Runs on `http://127.0.0.1` (no HTTPS) | Local demo | HTTPS with a TLS certificate, `SESSION_SECURE_COOKIE=true`, HSTS |
| Admin MFA is off in local mode | Smooth demo | Turns on automatically outside local mode (or `ADMIN_MFA=true`) |
| Demo admin password is `admin123` | Shared demo data | Change it with `plantifier:admin --recover`; enforce a strong password |
| No site-wide Content-Security-Policy header | Not yet added (React escaping is the main XSS defence) | Add a security-headers middleware (CSP, X-Frame-Options, Referrer-Policy) |
| Backups are not encrypted | Stored locally, excluded from Git | Encrypt the archives and keep a copy offsite |
| Session data is not encrypted in the database | Default Laravel setting | `SESSION_ENCRYPT=true` |
| Emails are written to a log, not sent | No mail server locally | Real SMTP with SPF/DKIM |
| Google sign-in is in "Testing" mode | Only listed testers can sign in | Publish the OAuth app |
| No automated security scanning on every push | School project | Run `composer audit`, `npm audit` and a static analyzer in CI |
| AI answers can still be wrong | Inherent to AI | Pl@ntNet is the authority, safety warnings, user corrections reviewed by the admin |

---

## 10. Numbers to remember

| Item | Value |
|---|---|
| Password rule | 10+ characters, letters and numbers, bcrypt hashed |
| Login attempts | 5 per minute per IP + email |
| Session lifetime | 120 minutes, HttpOnly, SameSite=Lax |
| Password reset link | Expires after 60 minutes |
| Admin MFA window | 30 minutes, then verify again |
| Super Admins | Exactly 1 (enforced by the database) |
| Photo uploads | JPEG/PNG/WebP, 4 MB, 16 MP, re-encoded (GPS removed) |
| Approximate location | Rounded to about 11 km |
| City lookup | Rounded to about 110 m before leaving the server |
| AI limits per user | 30 text / 10 photo requests per hour |
| Automated tests | 134 backend + 19 browser |
| Known dependency vulnerabilities | 0 (checked Oct 24, 2026) |
