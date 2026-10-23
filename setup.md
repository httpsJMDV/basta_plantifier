# PlantifierAI setup guide

How to get PlantifierAI running on your own computer: clone the branch, install everything, load the shared database, and start the app. Commands are for **Windows PowerShell**, run from the project folder unless a step says otherwise.

## 1. Install these first

| Tool | Version | Check with |
|---|---|---|
| Git | any recent | `git --version` |
| PHP | 8.3 or newer | `php -v` |
| Composer | 2.x | `composer --version` |
| Node.js | 20.19+ or 22 LTS | `node -v` |
| MySQL Server | 8.0 (MySQL Workbench is optional) | `mysql --version` |

### Turn on the PHP extensions

Open your `php.ini` (run `php --ini` to find it) and make sure these lines **do not** start with `;`:

```ini
extension=curl
extension=fileinfo
extension=gd
extension=intl
extension=mbstring
extension=openssl
extension=pdo_mysql
extension=zip
```

Check with `php -m`. Every name above should be listed. `gd` is needed for photo uploads, and `zip` for the admin backups.

## 2. Clone the branch

```powershell
git clone -b feat/plantifier-ai-workspace https://github.com/httpsJMDV/basta_plantifier.git
cd basta_plantifier
```

The project has two folders:

- `backend/`: Laravel (PHP). It runs the server, database, and API.
- `frontend/`: React + Vite. It holds the pages you see.

## 3. Install the dependencies

**Backend:**

```powershell
cd backend
composer install
Copy-Item .env.example .env
php artisan key:generate
cd ..
```

**Frontend:**

```powershell
cd frontend
npm install
npm run build
cd ..
```

`npm run build` creates the page files the server needs. Run it again after pulling new frontend changes.

## 4. Configure `backend/.env`

Open `backend/.env` and set your MySQL login:

```ini
DB_DATABASE=plantifierai
DB_USERNAME=root
DB_PASSWORD=your-mysql-password
```

Optional, for the AI features to answer:

```ini
GROQ_API_KEY=...        # chat replies and note help   (console.groq.com/keys)
PLANTNET_API_KEY=...    # photo identification         (my.plantnet.org)
ANTHROPIC_API_KEY=...   # optional backup for chat     (console.anthropic.com)
```

You can skip the keys and add them later from **Admin > AI providers**. Google sign-in also needs `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET` (see section 9).

After any change to `.env`, run `php artisan config:clear` inside `backend`.

## 5. Load the database

Pick **one** option.

### Option A: Import the shared database (recommended)

This gives you the same data as the team: the admin account, 300 demo members, community posts, and more. The file is `backend/database/plantifierai.sql`.

> **Create the empty database first.** The `.sql` file only holds the tables and data. It does not create the database itself, so importing without step 1, or without choosing `plantifierai` as the target, fails with `ERROR 1046 (3D000): No database selected`.

#### Step 1: Create the empty `plantifierai` database

**MySQL Workbench:**

1. Open your local connection.
2. Click the **Create a new schema** button in the toolbar (the cylinder icon with a +).
3. Set **Name** to `plantifierai`.
4. Set **Charset** to `utf8mb4` and **Collation** to `utf8mb4_unicode_ci`.
5. Click **Apply**, then **Apply** again, then **Finish**.
6. In the **Schemas** panel on the left, click the refresh icon. `plantifierai` should be listed.

**Or run this SQL** (in a Workbench query tab, then press the lightning bolt):

```sql
CREATE DATABASE IF NOT EXISTS plantifierai CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

**Or from PowerShell:**

```powershell
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS plantifierai CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
```

#### Step 2: Import the file into it

**MySQL Workbench:**

1. Go to **Server > Data Import**.
2. Choose **Import from Self-Contained File** and select `basta_plantifier\backend\database\plantifierai.sql`.
3. Set **Default Target Schema** to `plantifierai`. This is the step people miss.
4. Leave **Dump Structure and Data** selected and click **Start Import**.
5. It should end with "Import of ... has finished". Refresh the schema. It should contain 37 tables.

**Or from PowerShell**, in the project folder:

```powershell
mysql -u root -p plantifierai -e "source backend/database/plantifierai.sql"
```

You can import again at any time. The file replaces its tables, so a half-finished import is fixed by rerunning it.

#### Step 3: Apply any newer changes

This is safe to run on an imported database:

```powershell
cd backend
php artisan migrate
cd ..
```

### Option B: Start with an empty database

```powershell
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS plantifierai CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
cd backend
php artisan migrate
php artisan plantifier:admin your-email@example.com --name="Your Name"   # creates the admin; asks for a password
php artisan db:seed --class=DemoCommunitySeeder                          # optional: 300 demo members + 100 posts
cd ..
```

## 6. Run the system

Open **two terminals** in the `backend` folder:

| Terminal | Command | What it does |
|---|---|---|
| 1 | `php artisan serve` | Runs the app at **http://127.0.0.1:8024** |
| 2 | `php artisan reverb:start` | Live chat and notifications (port 8084) |

Then open **http://127.0.0.1:8024** in your browser.

**Editing the design?** Also run `npm run dev` in `frontend` (a third terminal), so changes show up without rebuilding. Keep opening port **8024**. Port 5173 only shows a "Laravel Vite" info page.

To stop a server, press `Ctrl + C` in its terminal.

## 7. Accounts

These accounts exist if you imported `plantifierai.sql`:

| Account | Email | Password |
|---|---|---|
| Super Admin | `admin@plantifier.com` | `admin123` |
| Demo members (300) | any `...@plantifier.test`, for example `jenny_vergara_742@plantifier.test` | `Plantdemo2026` |

- The admin lands on `/admin` after signing in. The **Admin System** link in the sidebar switches between the admin and the normal app.
- Find any demo member's email under **Admin > Accounts**.
- On your own computer (`APP_ENV=local`), the admin pages don't ask for an authenticator code or your password again. Add `ADMIN_MFA=true` to `.env` to turn those checks on.

## 8. Things that are not in Git or the `.sql` file

- **Uploaded photos** are stored in `backend/storage/app/private`. They are not in Git or the database file. Copy that folder from a teammate if you want the same photos. Otherwise older photos show as missing. The demo data has no photos, so it is unaffected.
- **API keys and `.env`** are never committed. Each person adds their own.
- **`APP_KEY`**: keys saved through Admin > AI providers are encrypted with the `APP_KEY` of the computer that saved them. On a different computer, add them again.

## 9. Google sign-in (optional)

### Easiest: use the team's shared Google client

The whole team can use one Google client. The redirect address `http://127.0.0.1:8024/auth/google/callback` always points to the computer you're on, so it works on every laptop.

1. Ask the project owner to send you these three lines **privately**, in a direct message. Never put them in Git or in this file, because the secret works like a password.

   ```ini
   GOOGLE_CLIENT_ID=...
   GOOGLE_CLIENT_SECRET=...
   GOOGLE_REDIRECT_URI=http://127.0.0.1:8024/auth/google/callback
   ```

2. Paste them into your `backend/.env`, then run `php artisan config:clear` in `backend`.
3. If the Google app is in **Testing** mode, the owner must add your Gmail address under **Google Cloud Console > APIs & Services > OAuth consent screen > Test users**. Until then, Google shows "Access blocked" for your account.

### Or create your own Google client

1. In Google Cloud Console, create an OAuth client of type **Web application**.
2. Add this authorized redirect URI exactly: `http://127.0.0.1:8024/auth/google/callback`.
3. Put the client ID and secret in `backend/.env` (`GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`), then run `php artisan config:clear`.

Either way, open the app at **http://127.0.0.1:8024**. Google treats `localhost:8024` or any other port as a different address and refuses the sign-in.

## 10. Updating `backend/database/plantifierai.sql` (for whoever shares the data)

**MySQL Workbench:**

1. Go to **Server > Data Export**.
2. Tick the `plantifierai` schema.
3. Choose **Dump Structure and Data** and **Export to Self-Contained File**.
4. Save it as `basta_plantifier\backend\database\plantifierai.sql`.
5. Leave **Include Create Schema** unticked, so the file keeps working with the "create the empty database first" steps in section 5.
6. Click **Start Export**.

**Or PowerShell**, from the project folder:

```powershell
mysqldump -u root -p --single-transaction --routines --result-file=backend\database\plantifierai.sql plantifierai
```

Use `--result-file` instead of `>`, because PowerShell's `>` saves the file in a format MySQL can't import.

The export includes every account's email and password hash. Keep the repository private if real accounts are in it.

## 11. Useful commands

| Task | Command (in `backend`) |
|---|---|
| Run the backend tests | `php artisan test` |
| Create a fresh admin (empty database only) | `php artisan plantifier:admin email@example.com` |
| Reset the admin's password and authenticator | `php artisan plantifier:admin admin@plantifier.com --recover` |
| Add the demo community to an empty database | `php artisan db:seed --class=DemoCommunitySeeder` |
| Remove all demo members and their posts | `php artisan tinker --execute="DB::table('users')->where('email','like','%@plantifier.test')->delete();"` |
| Clear cached settings after editing `.env` | `php artisan config:clear` |

## 12. Troubleshooting

| Problem | Fix |
|---|---|
| Import fails with `ERROR 1046 (3D000): No database selected` | The file doesn't create the database. Create `plantifierai` first (section 5, step 1), then set it as **Default Target Schema** and import again. |
| `ERROR 1049 (42000): Unknown database 'plantifierai'` | Same fix: create the empty `plantifierai` database first. |
| App shows `Table 'plantifierai.…' doesn't exist` | The import didn't finish. Run the import again, then `php artisan migrate` in `backend`. |
| `php artisan migrate` says a table already exists | You ran migrate before importing. Just import the `.sql` file (it replaces those tables), then run `php artisan migrate` again. |
| `Vite manifest not found` | Run `npm run build` in `frontend`. |
| Browser shows a "Laravel Vite" page | You opened port 5173. Open http://127.0.0.1:8024 instead. |
| `Port 8024 is already in use` | Another `php artisan serve` is still running. Close it or press `Ctrl + C` in its terminal. |
| `could not find driver` / `SQLSTATE[HY000] [2002]` | Turn on `pdo_mysql` in `php.ini` and make sure MySQL Server is running. |
| `Access denied for user 'root'` | Fix `DB_USERNAME` / `DB_PASSWORD` in `backend/.env`, then `php artisan config:clear`. |
| Photo upload fails | Turn on `extension=gd` in `php.ini` and restart `php artisan serve`. |
| Backup buttons fail in the admin | Turn on `extension=zip` in `php.ini`. |
| `419 Page Expired` when signing in | Refresh the page, or clear the site's cookies, then sign in again. |
| Chat says "AI is not configured yet" | Add a Groq key in `.env` or in Admin > AI providers. |
| Messages don't update live | Start `php artisan reverb:start` in a second terminal. |
| `Module "gd" is already loaded` warning | Harmless. GD is turned on twice, once in `php.ini` and once by the old `scripts/` launchers. |
