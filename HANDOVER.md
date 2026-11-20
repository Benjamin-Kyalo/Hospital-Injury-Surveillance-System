# Technical Handover Guide
## Hospital Injury Surveillance and Reporting Management System (HISS)

This guide is written for someone who has never seen the project before. It explains what the system does, where everything lives, how the pieces talk to each other, how to run it, how to change it, and what to do when something breaks. All explanations refer to the actual files in this folder.

If your immediate task is to put the project on a different computer, start with **SETUP-NEW-PC.md** — it is a shorter, step-by-step installation and verification checklist. This document is the deeper technical reference.

---

## 0. Quick facts

| Item | Value |
| --- | --- |
| Project folder | `C:\Users\Admin IT\Downloads\CS50\Hospital Injury Surveillance System` |
| Web address (local) | `http://localhost:8000/` |
| Web server | PHP built-in development server (no Apache, no XAMPP) |
| Database | PostgreSQL 18, database name `HISS`, port `5432` |
| Database password | `1234` (also stored in `config.php`) |
| Main admin login | `admin` / `Admin@123` |
| Source control | Local Git repository, branch `main`; commits are local (not pushed) |
| Data | 16 synthetic demo cases and 4 demo staff accounts. No real patient data. |
| PHP version used here | Portable PHP 8.6 in `runtime\php` |

Start it with:

```powershell
cd "C:\Users\Admin IT\Downloads\CS50\Hospital Injury Surveillance System"
.\start-injury-surveillance.bat
```

Stop it with:

```powershell
.\stop-injury-surveillance.bat
```

---

## 1. What the system does and how it works

### 1.1 Purpose

The system is a small web application for recording, searching and reporting injury cases at a hospital. It replaces paper registers with a searchable database. It uses **patient codes and optional patient names**, not real personally identifying details. The hospital name, phone number, email address and physical address are configurable by an administrator under **Users & roles → Hospital settings**; the former affiliation line and demo-warning footer text were removed.

### 1.2 The main jobs

1. **Sign in and sign out** with staff accounts and four roles.
2. **Record an injury case**: patient code, patient name, age, sex, incident date/time, cause, injury type, body part, severity, treatment, outcome, notes.
3. **Search the register** by case reference, patient code or patient name, plus filters for mechanism, outcome and date range.
4. **View, edit and delete** existing cases (deletion needs the separate `delete` permission).
5. **See a dashboard** with case counts and simple breakdowns.
6. **Run reports** with the same filters and download a CSV file.
7. **Manage staff accounts** (admin only): create, edit, change role, disable, delete.
8. **Manage roles** (admin only): create, edit and delete roles and choose which permissions they carry.
9. **Configure the hospital profile** (admin only): set the name, phone number, email address and physical address displayed in the header and footer.

### 1.3 Roles and permissions

Permissions are stored as a comma-separated string in the `roles.permissions` column in `database.sql` and loaded into the PHP session at login (`index.php`, line 38). The PHP function `can()` in `includes/common.php` checks that list.

| Role | Permissions from `database.sql` | What the user can do |
| --- | --- | --- |
| `admin` | `view,create,export,manage_users,delete` | Everything: cases, reports, CSV, accounts, roles and deletion |
| `officer` | `view,create` | Add and edit cases |
| `records` | `view,export` | Search, view reports, download CSV |
| `viewer` | `view` | View cases and reports only |

The five valid permission names are defined in `$permission_options` at the bottom of `includes/common.php` and can be assigned on the **Roles & permissions** tab of `users.php` — there is no separate Roles page. The navigation bar in `includes/header.php` hides "New case" unless `can('create')`, and hides "Users & roles" unless `can('manage_users')`. Pages themselves are protected by `require_login()` or `require_permission('...')`, so hiding a link is not the only protection.

### 1.3.1 CRUD matrix (what can be changed by whom)

| Data | Create | Read | Update | Delete |
| --- | --- | --- | --- | --- |
| Cases (`injury_cases`) | `create` — `case_form.php` | `view` — `cases.php`, `case_view.php`, `reports.php`, `export.php` | `create` — `case_form.php` | `delete` — `case_delete.php` |
| Accounts (`users`) | `manage_users` — `users.php?tab=accounts&action=new` | `manage_users` — `users.php?tab=accounts` | `manage_users` — `users.php?tab=accounts&action=edit` (includes disable and password reset) | `manage_users` **and** `delete` — `users.php` |
| Roles (`roles`) | `manage_users` — `users.php?tab=roles&action=new` | `manage_users` — `users.php?tab=roles` | `manage_users` — `users.php?tab=roles&action=edit` | `manage_users` **and** `delete` — `users.php` |
| Hospital profile (`app_settings`) | `manage_users` — `users.php?tab=settings` | shown in header/footer on every page | `manage_users` — `users.php?tab=settings` | not applicable |

Every write operation is:

- **POST only** — a `GET` cannot change data;
- **CSRF protected** — `check_csrf()` compares the token in the form with the one in the session;
- **server-side checked** — `require_permission()` runs before any SQL;
- **parameterised** — all values are bound with PDO placeholders.

Extra safety rules: you cannot delete or deactivate your own account, move your own account to a role without `manage_users`, delete the last administrator, delete a role that still has accounts, or remove account/role management from your own role. See section 8.9.

### 1.4 A normal user journey

1. User opens `index.php` and signs in.
2. `index.php` looks up the username in `users`, joins `roles`, checks `active` and verifies the bcrypt password with `password_verify()`.
3. On success it stores the user details and permissions in the PHP session and redirects to `dashboard.php`.
4. Every later page includes `includes/common.php`, which starts the session, and `includes/db.php`, which connects to PostgreSQL.
5. The user works in `cases.php`, `case_form.php`, `case_view.php`, `reports.php` and, if admin, `users.php`.
6. `logout.php` clears the session and sends the user back to `index.php`.

### 1.5 Automatic numbers

Two PostgreSQL sequences prevent typing mistakes:

- `case_ref_seq` → case references such as `INJ-2026-0017`
- `patient_code_seq` → patient codes such as `PT-1017`

`includes/common.php` has `peek_sequence()`, `preview_case_reference()` and `preview_patient_code()`. These read the sequence **without consuming a value**, so the New Case form can show the next numbers as read-only fields. When the form is actually saved, `case_form.php` calls `nextval()` on both sequences and writes the real values. Editing a case shows its existing reference and code, which never change. Deleting a case does **not** return its numbers to the sequence — this prevents an old CSV or printed record from suddenly referring to a different case.

---

## 2. Technologies, frameworks and tools

### 2.1 What is used

| Layer | Technology | Where it lives |
| --- | --- | --- |
| Frontend | Plain HTML, CSS, JavaScript | PHP files plus `assets/style.css`, `assets/app.js` |
| Backend | PHP 8.6 (portable Windows build) | `runtime\php\php.exe` |
| Web server | PHP built-in development server | Started by `start-injury-surveillance.bat` on port 8000 |
| Database | PostgreSQL 18 (native Windows install) | Windows service `postgresql-x64-18`, port 5432 |
| Database access | PDO with `pdo_pgsql` | `includes/db.php` |
| Scripts | Windows batch files | `*.bat` in the project root |
| Version control | Git | Local `.git` folder |

### 2.2 What is deliberately NOT used

- No XAMPP, Apache, MySQL or MariaDB.
- No framework such as Laravel, Django or Express.
- No Node.js, npm, Composer, bundler or build step.
- No chart library, CDN or external CSS framework.
- No REST/JSON API layer. The PHP pages are the backend. The only non-HTML output is CSV from `export.php`.

This is intentional: the project is a final-year prototype that should be easy to read and run on one Windows laptop.

### 2.3 PHP extensions that must be enabled

The portable PHP in `runtime\php` already has a `php.ini` with these enabled:

```
extension=pdo_pgsql
extension=pgsql
extension=mbstring
date.timezone = Africa/Nairobi
```

`pdo_pgsql` is required by `includes/db.php`. `mbstring` is used by `mb_strlen()` for validating field lengths. If you use a different PHP installation, enable the same extensions.

---

## 3. Folder and file map

```
Hospital Injury Surveillance System\
│
├── index.php                     Sign-in page and login logic
├── dashboard.php                 Summary tiles, breakdowns, last six months
├── cases.php                     Case list and search form
├── case_view.php                 Full details of one case
├── case_form.php                 Add / edit a case (all validation and saving)
├── case_delete.php               Delete a case (POST, CSRF, delete permission)
├── reports.php                   Filtered report totals
├── export.php                    CSV download (permission: export)
├── users.php                     Admin: Accounts, Roles and Hospital settings tabs
├── roles.php                     Compatibility redirect to users.php?tab=roles (no UI)
├── logout.php                    Ends the session
├── config.php                    Database connection settings
│
├── includes\
│   ├── common.php                Session, auth helpers, validation, filters, sequences
│   ├── db.php                    PDO connection to PostgreSQL
│   ├── header.php                Shared header, navigation, flash, hospital name
│   └── footer.php                Shared footer (hospital name and contact details), app.js include
│
├── assets\
│   ├── style.css                 All styling (plain institutional look)
│   └── app.js                    Small client-side helpers
│
├── database.sql                  Full schema, roles, users, 16 demo cases
├── create-database.sql           Creates the HISS database with capital letters
├── migration-add-patient-name.sql  Adds patient_name to an older database
├── migration-add-patient-code.sql  Adds patient_code_seq to an older database
├── migration-add-delete-permission.sql  Adds the delete permission to admin
├── migration-fix-recorded-by-delete.sql Keeps cases when their recorder is deleted
├── migration-add-settings.sql    Adds the editable hospital profile settings
│
├── start-injury-surveillance.bat Starts/checks PostgreSQL and starts PHP
├── stop-injury-surveillance.bat  Stops only the PHP server
├── reset-database.bat            Replaces HISS tables with original demo data
│
├── runtime\                      Not for editing. Portable PHP and its settings.
│   ├── php\                      PHP 8.6 executables and php.ini
│   ├── pgdata\                   Old unused test cluster from earlier setup
│   └── logs\                     Old PostgreSQL log
│
├── README.md                     Short project overview, accounts and CRUD matrix
├── README-START-HERE.md          Quick start and manual commands
├── SETUP-NEW-PC.md               Copy the project to another PC and test it
├── HANDOVER.md                   This guide
└── .gitignore                    Ignores runtime\ so binaries are not committed
```

### 3.1 Important file responsibilities

| File | What to know |
| --- | --- |
| `config.php` | Returns an array with `host`, `port`, `dbname`, `user`, `password`. Change this first if the database moves. |
| `includes/db.php` | Builds the DSN `pgsql:host=...;port=...;dbname=...`, creates `$pdo`, stops with an error page if connection fails. |
| `includes/common.php` | The shared toolbox: `e()` (HTML escaping), `csrf_field()`, `check_csrf()`, `flash()`, `current_user()`, `can()`, `require_login()`, `require_permission()`, `get_setting()` (reads one setting), `hospital_defaults()` and `get_settings()` (read the whole hospital profile with safe fallbacks), `options()`, `value_of()`, `valid_date()`, `read_filters()`, `case_filter_sql()`, `preview_case_reference()`, `preview_patient_code()`, and all the dropdown option arrays. |
| `case_form.php` | The largest and most important file. Handles both add and edit. It reads POST data, validates it, generates the reference and code with `nextval()`, and runs prepared `INSERT` or `UPDATE` statements. |
| `case_delete.php` | Deletes one case. POST only, `require_permission('delete')`, CSRF check, prepared `DELETE`, then a flash message and redirect. Sequence numbers are not reused. |
| `cases.php` | Builds a `SELECT` using `case_filter_sql()` and lists up to 100 newest matching cases, ordered by incident date. Shows Edit (if `create`) and Delete (if `delete`) actions. |
| `reports.php` | Uses the same filters and runs `COUNT(*)`, `GROUP BY mechanism`, `GROUP BY outcome`, `GROUP BY classification`, and a monthly grouping with `to_char(incident_date, 'YYYY-MM')`. |
| `export.php` | Same filters as reports, but outputs CSV with `fputcsv()`. Requires the `export` permission. |
| `users.php` | The single administration page. Requires `manage_users`; `?tab=accounts` handles full account CRUD, `?tab=roles` handles full role CRUD and `?tab=settings` saves the hospital profile (name, phone, email, address). Delete additionally requires `delete`. All write actions are POST-only with CSRF and use distinct `action` values (`account_create/update/delete`, `role_create/update/delete`, `settings_save`). Blocks self-delete, self-deactivation, moving yourself off `manage_users`, deleting the last admin and deleting a role in use. Passwords use `password_hash($password, PASSWORD_BCRYPT)`. |
| `database.sql` | Drops and recreates `injury_cases`, `users`, `roles`, `app_settings`, `case_ref_seq`, `patient_code_seq`, then inserts demo data. **Destructive.** `recorded_by` uses `ON DELETE SET NULL`; `app_settings` holds the default hospital profile. |
| `migration-*.sql` | Non-destructive additions for databases created by older versions. Safe to run more than once. The start script applies any that are missing. |

---

## 4. How the frontend, backend and database communicate

### 4.1 There is no separate API

Every `.php` file in the root is both the page and the server-side controller. The browser sends a normal HTTP request to a PHP file; PHP reads `$_GET` or `$_POST`, talks to PostgreSQL through PDO, and returns an HTML page (or CSV from `export.php`). There are no JSON endpoints and no JavaScript `fetch()` calls in the app itself.

### 4.2 Request lifecycle, step by step

Example: user searches for `Amina` in `cases.php`.

1. Browser requests `GET cases.php?q=Amina`.
2. PHP runs `require_once 'includes/common.php'` → session starts, helper functions become available.
3. PHP runs `require_once 'includes/db.php'` → `config.php` is loaded and a PDO connection is created.
4. `require_login()` checks `$_SESSION['user']`. If missing, it sets a flash message and redirects to `index.php`.
5. `read_filters()` reads `q`, `from`, `to`, `mechanism`, `outcome` from `$_GET` and discards invalid dates.
6. `case_filter_sql($filters, $params)` builds:
   ```sql
   WHERE (case_reference ILIKE :q OR patient_code ILIKE :q OR patient_name ILIKE :q)
   ```
   and sets `:q` to `%Amina%`.
7. `cases.php` prepares and executes:
   ```sql
   SELECT case_id, case_reference, incident_date, patient_code, patient_name, mechanism,
          injury_type, body_part, classification, outcome
   FROM injury_cases
   WHERE (...)
   ORDER BY incident_date DESC, case_id DESC
   LIMIT 100
   ```
8. PDO returns rows as associative arrays (`PDO::FETCH_ASSOC`).
9. `includes/header.php` renders the top bar, navigation and any flash message.
10. `cases.php` loops over the rows and prints an HTML table. `e()` escapes every value before it reaches the browser.
11. `includes/footer.php` closes the page and loads `assets/app.js`.

### 4.3 How a save works

`case_form.php` is typical:

1. `check_csrf()` compares the submitted `csrf_token` with `$_SESSION['csrf_token']`. A mismatch returns HTTP 403.
2. POST values are read into a `$data` array with `trim()` and `(string)` casts.
3. Required fields, date format, age range, time format and field lengths are checked in PHP. `$errors` collects messages.
4. If there are no errors:
   - New case: `nextval('case_ref_seq')` and `nextval('patient_code_seq')` create `INJ-...` and `PT-....`
   - Edit: the existing reference and patient code are kept from `$case`.
5. A prepared statement runs `INSERT` or `UPDATE`. Values are always bound as parameters, never concatenated into SQL.
6. `flash('success', ...)` stores a message in the session, then `redirect('case_view.php?id=...')` sends the browser to the saved record. The header displays the flash once and clears it.

### 4.4 How a delete works

Deletes never happen through a link — they are POST forms with a CSRF token. There are three places to know:

1. **Case:** the Delete button on `cases.php` or `case_view.php` posts `case_id` to `case_delete.php`. That file requires `delete`, checks CSRF, reads the reference for the success message, runs `DELETE FROM injury_cases WHERE case_id = :id`, flashes a message and redirects to `cases.php`.
2. **Account:** the Delete button on the Accounts tab of `users.php` posts `action=account_delete` and `user_id` to the same page. Before deleting, the code blocks self-deletion and checks that at least one admin remains. Because `injury_cases.recorded_by` is `ON DELETE SET NULL`, the cases stay and show "unknown user" as the recorder.
3. **Role:** the Delete button on the Roles tab of `users.php` posts `action=role_delete` and `role_id`. The server counts accounts using that role and refuses if the count is above zero (otherwise those accounts would lose their role link).

All three catch `PDOException`, write details to the PHP error log and show a neutral error message to the user. The hidden `tab` field in every form makes the page return to the same tab after a failed validation or a redirect.

### 4.5 Security measures that are actually implemented

| Measure | Where |
| --- | --- |
| Prepared statements for all user input | `index.php`, `case_form.php`, `users.php`, `case_view.php`, `cases.php`, `reports.php`, `export.php` |
| HTML escaping with `e()` | All output in every page |
| CSRF tokens on every POST form | `csrf_field()` / `check_csrf()` in `includes/common.php` |
| Bcrypt password hashes | `password_hash()` in `users.php`, `password_verify()` in `index.php` |
| Session ID regeneration at login | `index.php` line 31 |
| HTTP-only, SameSite=Lax session cookie | `includes/common.php` lines 3–8 |
| Role checks on pages and menu items | `require_permission()` and `can()` |
| Destructive actions are POST-only with CSRF | `case_delete.php` and all delete forms in `users.php` |
| Own-account deactivation/deletion blocked | `users.php` (account branch) |
| Cannot move your own account off `manage_users` | `users.php` (account branch) |
| Last administrator cannot be deleted | `users.php` (admin role count) |
| Role in use cannot be deleted | `users.php` (role branch, user count per role) |
| Cannot remove your own role's `manage_users` | `users.php` (role branch) |
| Deleting an account keeps its cases | `injury_cases.recorded_by ... ON DELETE SET NULL` |

Known limitation for a handover: PHP's built-in server is intended for development. See section 5.5 for deployment guidance.

---

## 5. Install, configure, run and deploy

### 5.1 What must already be on the computer

- Windows with PostgreSQL 18 installed natively and the service `postgresql-x64-18` running.
- The `postgres` user password `1234` (or you change `config.php` to match your password).
- This project folder, including the `runtime\php` folder. `runtime` is in `.gitignore`, so if you clone from Git instead of copying the folder, `runtime\php` will be missing and you must supply your own PHP with `pdo_pgsql`.

### 5.2 First-time setup, step by step

For a brand-new computer, read **SETUP-NEW-PC.md** first — it covers installing PostgreSQL and copying the folder. The steps below assume PostgreSQL and the project folder are already present.

1. Open PowerShell in the project folder:
   ```powershell
   cd "C:\Users\Admin IT\Downloads\CS50\Hospital Injury Surveillance System"
   ```
2. Check PostgreSQL is running:
   ```powershell
   Get-Service postgresql-x64-18
   ```
   Status should be `Running`. If not:
   ```powershell
   Start-Service postgresql-x64-18
   ```
3. Start the app:
   ```powershell
   .\start-injury-surveillance.bat
   ```
   The script does the following, in order:
   - finds the PostgreSQL binaries from the Windows registry;
   - checks it can connect on port 5432 with password `1234`;
   - creates the `HISS` database from `create-database.sql` if it does not exist;
   - loads `database.sql` if the public schema has no tables;
   - applies `migration-add-patient-name.sql` if the `patient_name` column is missing;
   - applies `migration-add-patient-code.sql` if `patient_code_seq` is missing;
   - applies `migration-add-delete-permission.sql` if admin lacks the `delete` permission;
   - applies `migration-fix-recorded-by-delete.sql` if case authorship is not `ON DELETE SET NULL`;
   - always applies the idempotent `migration-add-settings.sql`, so existing databases receive newly added setting keys;
   - checks whether something is already listening on port 8000; if so it leaves that server alone and prints "already listening";
   - otherwise starts PHP on `http://localhost:8000/` in a small window titled "HISS PHP server".
4. Open `http://localhost:8000/` in a browser.
5. Sign in with `admin` / `Admin@123`. If permissions were just changed in the database while you were signed in, sign out and back in so the session picks them up.

### 5.3 Configuration

`config.php` currently contains:

```php
return [
    'host'     => '127.0.0.1',
    'port'     => '5432',
    'dbname'   => 'HISS',
    'user'     => 'postgres',
    'password' => '1234',
];
```

If the database moves to another machine, change `host` and/or `port`. If the password changes, change `password` here **and** in the two batch files (`start-injury-surveillance.bat` and `reset-database.bat` set `PGPASSWORD=1234`). PHP reads `config.php` on every request, so no restart is needed for config changes — just refresh the page.

Note: `create-database.sql` uses `CREATE DATABASE "HISS"` with quotes. Without the quotes PostgreSQL would create `hiss` in lowercase, and `config.php` would not find it.

### 5.4 Stopping, resetting and backing up

Stop only this app:

```powershell
.\stop-injury-surveillance.bat
```

This kills the window titled "HISS PHP server". It deliberately does **not** stop the PostgreSQL Windows service, because other projects use it. If you started PHP manually from a terminal, the batch file will not find it; press `Ctrl+C` in that terminal instead.

Restore the original demo data (destroys added cases and users):

```powershell
.\reset-database.bat
```

Back up the database:

```powershell
$env:PGPASSWORD = "1234"
$pg = "C:\Program Files\PostgreSQL\18\bin"
& "$pg\pg_dump.exe" -h 127.0.0.1 -p 5432 -U postgres -d HISS -F p -f "C:\backup\hiss_backup.sql"
```

Restore from that backup into an empty database:

```powershell
& "$pg\psql.exe" -h 127.0.0.1 -p 5432 -U postgres -d HISS -f "C:\backup\hiss_backup.sql"
```

### 5.5 Deploying beyond a laptop

The PHP built-in server is a development tool. For a real deployment:

1. Use a proper web server with PHP (IIS on Windows, or Apache/nginx with PHP-FPM on Linux). Point the document root at this folder.
2. Keep `includes\`, `assets\`, `database.sql` and the PHP files together; only the root folder needs to be web-accessible. Ideally move `config.php` and SQL files outside the document root.
3. Replace the password in `config.php` with a strong secret. Do not commit real credentials.
4. Require HTTPS. The session cookie is `httponly` and `SameSite=Lax`, but it is not marked `secure`; add `'secure' => true` in `session_set_cookie_params()` in `includes/common.php` when serving over HTTPS.
5. Give the application its own PostgreSQL role with only the privileges it needs, instead of the `postgres` superuser.
6. Schedule regular `pg_dump` backups.
7. Remove or protect `reset-database.bat`, `database.sql` and the migration scripts on a production server.
8. Replace the demo accounts and remove all synthetic cases before real use. The system currently has no audit trail and no password reset flow. Deletions in particular are not logged anywhere except the PHP error log when they fail — add an audit table before using this with real records.
9. Decide who gets the `delete` permission. It is granted only to `admin` in the demo data; grant it to another role on the Users & roles → Roles & permissions tab only if that role really needs it.
10. `runtime\pgdata` and `runtime\logs` are leftovers from an unused test cluster. They are not needed by the running app and can be removed from a deployment.

---

## 6. Common tasks and how to make updates

### 6.1 Add a case

1. Sign in as `admin` or `nurse1`.
2. Click **New case**.
3. Confirm the pre-filled `INJ-...` reference and `PT-....` patient code.
4. Fill in the required fields (marked `*` in the form): incident date, mechanism, injury type, body part, severity, outcome. Sex defaults to "Not recorded"; age, time, name, treatment and notes are optional.
5. Click **Save case**. The browser lands on `case_view.php?id=...` with a success message.

### 6.2 Find a case

Type any of these into the search box on **Cases** or **Reports**:

- full or partial reference: `INJ-2026`
- patient code: `PT-1004`
- patient name: `Amina` or `Amina Otieno`

Matching is case-insensitive because `case_filter_sql()` uses PostgreSQL `ILIKE`. Combine with mechanism, outcome and date filters if needed.

### 6.3 Edit a case

Open the case and click **Edit this case**, or use the Edit link in the list. Reference and patient code are read-only; all other fields can be changed. `updated_at` is set automatically by the `UPDATE` statement.

### 6.4 Add or change a staff account

1. Sign in as `admin`.
2. Go to **Users** → **Add a user** (or Edit on an existing row).
3. Enter username, full name, role, active flag and password.
4. Username may contain only letters, numbers, dots, dashes and underscores. Password must be at least 8 characters.
5. Leave the password blank when editing to keep the current one.
6. You cannot deactivate your own account (`users.php` lines 69–71).

### 6.5 Change what a role can do

Edit the `permissions` string in `database.sql`, then reload the database. For example, to let the records role add cases:

```sql
UPDATE roles SET permissions = 'view,create,export' WHERE role_name = 'records';
```

Existing users only pick up the change after they sign out and back in, because permissions are stored in the session at login. Avoid doing this ad hoc in the live database: update `database.sql` as well so a rebuild keeps the change.

### 6.6 Change a dropdown list

All dropdown values live in arrays at the bottom of `includes/common.php`: `$mechanism_options`, `$injury_options`, `$body_options`, `$class_options`, `$treatment_options`, `$outcome_options`, `$sex_options`. Add a value there. If it also needs to appear in demo data, update `database.sql`.

### 6.7 Change the number format

- Case reference: `preview_case_reference()` in `includes/common.php` and the `$reference = sprintf(...)` line in `case_form.php`. Both use `INJ-%s-%04d` with `date('Y')`.
- Patient code: `preview_patient_code()` in `includes/common.php` and the `sprintf('PT-%04d', $next)` line in `case_form.php`.

Change both places together, otherwise the preview will disagree with what is saved.

### 6.8 Add a new field to cases (worked example)

Suppose you want to add `phone` as an optional text field.

1. **Database** — add the column to `database.sql`:
   ```sql
   phone VARCHAR(30),
   ```
   inside the `injury_cases` CREATE TABLE statement.
2. **Existing database** — create a migration file, for example `migration-add-phone.sql`:
   ```sql
   ALTER TABLE injury_cases ADD COLUMN IF NOT EXISTS phone VARCHAR(30);
   ```
   Run it with `psql -h 127.0.0.1 -p 5432 -U postgres -d HISS -f migration-add-phone.sql`, and add a matching check to `start-injury-surveillance.bat` so new installations are handled.
3. **Form** — in `case_form.php`:
   - add `'phone' => trim((string) ($_POST['phone'] ?? ''))` to `$data`;
   - add any validation;
   - add `':phone' => $data['phone'] === '' ? null : $data['phone']` to `$params`;
   - add `phone = :phone,` to the `UPDATE` and `phone` to the `INSERT` column/value lists;
   - add an `<input type="text" id="phone" name="phone" ...>` in the form.
4. **Display** — add a `<dt>Phone</dt><dd>...</dd>` pair in `case_view.php` and a `<th>`/`<td>` in `cases.php` (and remember to add `phone` to that page's `SELECT`).
5. **Export** — add the column to the `SELECT`, header array and row array in `export.php`.
6. **Test** — add a case, edit it, search, view details and download the CSV.

### 6.9 Add a new page

1. Create `mypage.php`.
2. Start with:
   ```php
   <?php
   require_once __DIR__ . '/includes/common.php';
   require_once __DIR__ . '/includes/db.php';
   require_login();               // or require_permission('export')
   $page_title = 'My page';
   ```
3. Write the HTML/PHP body.
4. End with `include __DIR__ . '/includes/footer.php';`.
5. Add a link in `includes/header.php` if it should appear in the navigation.

### 6.10 Change the appearance

Edit `assets/style.css`. There is no preprocessor. The main classes are `.topbar`, `.mainnav`, `.wrap`, `.page`, `.panel`, `.pagehead`, `.formgrid`, `.field`, `.filters`, `table`, `.details`, `.tiles`, `.tile`, `.bars`, `.notice`, `.btn`, `.status` and `.tablewrap`. Keep changes simple; the current design deliberately avoids gradients and decorative effects.

### 6.11 Work with Git

```powershell
git status                       # see what changed
git add .                        # stage changes
git commit -m "Describe change"  # save a local snapshot
git log --oneline -5             # see recent commits
```

The repository has a remote (`origin`) but commits were intentionally not pushed. Do not push unless you intend to share the code. `runtime\` is ignored, so the PHP binary and old test database are not versioned.

### 6.12 Delete a case

1. Sign in with an account that has the `delete` permission (admin by default).
2. Open the case from **Cases**, or use the Delete link in the results table.
3. Read the confirmation dialog carefully — deletion is permanent.
4. Click OK. `case_delete.php` removes the row and redirects to Cases with a success message.

Numbers are not recycled: after deleting case 17, the next case still gets reference `INJ-2026-0018` and code `PT-1018` if those were the last values. This is deliberate.

### 6.13 Delete a staff account

1. Sign in as admin and open **Users & roles**. The **Accounts** tab is shown first.
2. Use the Delete link on the account. The row for your own account shows "You" instead — nobody can delete themselves.
3. Confirm. The server refuses if this is the only administrator.
4. Cases recorded by that account remain in the register. On the case details page the recorder is shown as "unknown user" because `recorded_by` is set to NULL.

To keep a safer history, disable the account (untick **Account active**) instead of deleting it. Disabling preserves the name on case records. The Edit link also lets you change another user's username, full name, role and password (leave the password blank to keep it).

### 6.14 Add, edit or delete a role

1. Sign in as admin and open **Users & roles**, then choose the **Roles & permissions** tab. There is no separate Roles page or menu item.
2. **Add a role:** click *Add a role*, enter a lowercase name such as `data_clerk`, a description, and tick the permissions it needs.
3. **Edit a role:** click Edit on its row. Change the description or permissions. Users must sign out and back in before they notice a permission change, because permissions are copied into the session at login.
4. **Delete a role:** use the Delete link. The server refuses while any account still uses that role — move those accounts to a different role on the Accounts tab first.
5. You cannot remove `manage_users` from your own role, because that would lock you out of account and role management. You also cannot move your own account to a role without `manage_users`.

Valid permissions are `view`, `create`, `export`, `manage_users` and `delete`. They are defined in `$permission_options` in `includes/common.php` and checked with `can()` / `require_permission()` throughout the app.

### 6.15 Test a copy on another computer

See **SETUP-NEW-PC.md**. It walks through installing PostgreSQL, copying the folder (remember `runtime\php`), configuring `config.php` and the batch files, starting the app, and a 13-step test covering all CRUD operations and role restrictions.

### 6.16 Configure the hospital profile

1. Sign in as admin and open **Users & roles → Hospital settings**.
2. Fill in the hospital name (required, 2–100 characters) and, optionally, the phone number (up to 30 characters), email address (up to 100 characters, must be a valid address) and physical address (up to 200 characters). Click **Save hospital details**.
3. All four values are stored in `app_settings` under the keys `hospital_name`, `hospital_phone`, `hospital_email` and `hospital_address` using an UPSERT, so saving twice updates the same rows.
4. `includes/header.php` calls `get_settings($pdo, hospital_defaults())` on every request and shows the name under "Injury Surveillance" in the top bar. `includes/footer.php` shows the name and, on a second line, any contact details that are filled in. Changes appear on the next page load, including on the sign-in page — no need to sign out.
5. The former affiliation line and demo-warning footer line are gone. If you still see them, the browser is showing a cached page — refresh.
6. `hospital_defaults()` supplies `General Hospital` and three blank contact fields if the settings table is missing on an older database, so pages do not break. `migration-add-settings.sql` is idempotent and the start script runs it every time, which also fills in newly added keys on older databases without overwriting saved values.

---

## 7. What to check and how to troubleshoot common errors

### 7.1 First checks for any problem

Work through this list before digging deeper:

1. Is PostgreSQL running? `Get-Service postgresql-x64-18`
2. Is PHP running? Look for the small "HISS PHP server" window, or `Get-Process php`.
3. Can `psql` connect?
   ```powershell
   $env:PGPASSWORD = "1234"
   & "C:\Program Files\PostgreSQL\18\bin\psql.exe" -h 127.0.0.1 -p 5432 -U postgres -d HISS -c "select 1;"
   ```
4. Are the tables there?
   ```powershell
   & "C:\Program Files\PostgreSQL\18\bin\psql.exe" -h 127.0.0.1 -p 5432 -U postgres -d HISS -c "\dt"
   ```
   Expected: `roles`, `users`, `injury_cases`.
5. Are the sequences in step?
   ```sql
   SELECT last_value, is_called FROM case_ref_seq;
   SELECT last_value, is_called FROM patient_code_seq;
   ```
6. Check the PHP error log. The portable PHP writes errors to the console window; connection and save failures are also sent to PHP's `error_log` by `includes/db.php`, `case_form.php` and `users.php`.
7. Check syntax after editing PHP:
   ```powershell
   & ".\runtime\php\php.exe" -l case_form.php
   ```
   `No syntax errors detected` means the file is valid.

### 7.2 Symptom table

| Symptom | Likely cause | Fix |
| --- | --- | --- |
| Page shows "Could not connect to the database." | Wrong `config.php`, PostgreSQL stopped, wrong password | Check service, then `config.php`; test with `psql` as above |
| `password authentication failed for user "postgres"` | Password in `config.php` or batch file is wrong | Set the real password in `config.php`, `start-...bat`, `reset-database.bat` |
| `database "HISS" does not exist` | Database created as lowercase `hiss`, or not created | Run `create-database.sql` (it quotes the name); check with `\l` in psql |
| Site will not open at port 8000 | PHP not running, or port already used | Run start script; check `Get-NetTCPConnection -LocalPort 8000`; stop the other process or change `WEBPORT` in the start script |
| "Incorrect username or password" with correct details | Account disabled, or wrong database loaded | Check `SELECT username, active FROM users;` and that HISS has demo data |
| Blank white page | PHP fatal error, or missing extension | Check the PHP server window / error log; run `php -m` and confirm `pdo_pgsql` and `mbstring` |
| HTTP 403 "You do not have permission" | Role lacks permission | Check `roles.permissions` and sign out/in; see section 1.3 |
| "The form has expired. Go back and try again." | CSRF token missing or session expired | Reload the page and submit again; make sure cookies are enabled and you are on the same tab |
| Search returns nothing | Filter values left over; name not stored | Click Clear; check `SELECT patient_name FROM injury_cases LIMIT 5;` |
| Case reference or patient code duplicated / preview wrong | Sequences out of step with data | Realign them from the data: `SELECT setval('case_ref_seq', COALESCE((SELECT MAX(regexp_replace(case_reference, '[^0-9]', '', 'g')::int) FROM injury_cases), 0), TRUE);` and `SELECT setval('patient_code_seq', COALESCE((SELECT MAX(regexp_replace(patient_code, '[^0-9]', '', 'g')::int) FROM injury_cases), 1000), TRUE);` |
| CSV has PHP warnings at the top | Old `fputcsv()` call without escape argument | `export.php` now passes `',', '"', '\\'` to both `fputcsv()` calls; keep those arguments |
| Edit form rejects time `09:15:00` | PostgreSQL TIME includes seconds | `case_form.php` trims to `HH:MM` when loading and validates `HH:MM` or `HH:MM:SS` |
| Styling looks broken | Browser cached old CSS, or wrong path | Hard refresh; confirm `assets/style.css` is reachable at `http://localhost:8000/assets/style.css` |
| Delete button not visible | Account lacks `delete` (or `manage_users` for accounts/roles) | Sign in as admin, check the **Roles & permissions** tab, then sign out and back in |
| Delete attempt returns HTTP 403 | Missing/invalid CSRF token, or permission missing | Reload the page for a fresh token, sign in again after role changes |
| "This is the last administrator account" | Only one admin exists | Create another admin (Users & roles → Accounts → Add a user, role `admin`) first |
| "You cannot delete your own account" | Normal safety rule | Sign in with a different admin account |
| "You cannot move your own account to a role without account/role management" | Normal lockout protection | Give your account a role with `manage_users`; edit another account instead |
| "Role ... is still assigned to N account(s)" | Accounts still reference the role | Edit those accounts on the Accounts tab and choose another role, then delete the role |
| "You cannot remove account/role management from your own role" | Normal lockout protection | Keep `manage_users` on your own role; remove it from a different role |
| Deleting an account fails with a foreign key error | `recorded_by` FK not updated by migration | Run `migration-fix-recorded-by-delete.sql` |
| Case shows "unknown user" as recorder | The recording account was deleted | Expected: `recorded_by` was set to NULL to keep the case |

### 7.3 Useful read-only SQL checks

```sql
-- how many cases and users
SELECT (SELECT count(*) FROM injury_cases) AS cases,
       (SELECT count(*) FROM users) AS users;

-- recent cases
SELECT case_reference, patient_code, patient_name, incident_date
FROM injury_cases ORDER BY incident_date DESC LIMIT 10;

-- role permissions
SELECT role_name, permissions FROM roles ORDER BY role_id;

-- column exists?
SELECT column_name FROM information_schema.columns
WHERE table_name = 'injury_cases' ORDER BY ordinal_position;

-- permissions and account counts per role
SELECT r.role_name, r.permissions, COUNT(u.user_id) AS users
FROM roles r LEFT JOIN users u ON u.role_id = r.role_id
GROUP BY r.role_name, r.permissions ORDER BY r.role_id;

-- does deleting an account keep cases? (confdeltype 'n' = SET NULL)
SELECT conname, confdeltype FROM pg_constraint
WHERE conname = 'injury_cases_recorded_by_fkey';

-- next automatic numbers
SELECT last_value, is_called FROM case_ref_seq;
SELECT last_value, is_called FROM patient_code_seq;
```

Run these with:

```powershell
$env:PGPASSWORD = "1234"
& "C:\Program Files\PostgreSQL\18\bin\psql.exe" -h 127.0.0.1 -p 5432 -U postgres -d HISS -f "check.sql"
```

---

## 8. What to do when a specific feature stops working

### 8.1 Login fails for everyone

1. Confirm PostgreSQL is up and `HISS` has data: `SELECT count(*) FROM users;`
2. Confirm `config.php` points to port 5432 with the right password.
3. Confirm the account is active: `SELECT username, active FROM users WHERE username = 'admin';`
4. Check that `password_hash` still looks like `$2y$10$...`. If a hash was corrupted, reset it in `users.php` by signing in with another admin, or generate one:
   ```powershell
   & ".\runtime\php\php.exe" -r "echo password_hash('Admin@123', PASSWORD_BCRYPT);"
   ```
   then:
   ```sql
   UPDATE users SET password_hash = '<the printed hash>' WHERE username = 'admin';
   ```
5. If no one can log in after a database reset, reload `database.sql` with `reset-database.bat`.

### 8.2 Dashboard shows wrong numbers

`dashboard.php` uses four counts: total cases, this month (`incident_date >= date_trunc('month', CURRENT_DATE)`), severe (`classification = 'Severe'`) and distinct patient codes. If a number looks wrong:

1. Check the underlying rows, e.g.
   ```sql
   SELECT classification, count(*) FROM injury_cases GROUP BY classification;
   ```
2. Check the incident dates — the "this month" figure depends on the computer's current date and on dates stored in the future/past in the demo data.
3. If the dashboard is completely empty, check whether `cases.php` is also empty; that points to the database connection or an empty HISS database rather than dashboard code.

### 8.3 Search does not find a case

1. Click **Clear** and try again — filters combine with AND.
2. Try a code from the list, e.g. `PT-1004`, to separate a search problem from a data problem.
3. Check `case_filter_sql()` in `includes/common.php`; the `q` condition must contain all three columns:
   ```php
   (case_reference ILIKE :q OR patient_code ILIKE :q OR patient_name ILIKE :q)
   ```
4. Check the stored value:
   ```sql
   SELECT case_reference, patient_code, patient_name FROM injury_cases WHERE patient_code = 'PT-1004';
   ```
5. Remember the list is capped at `LIMIT 100` and sorted by incident date; a matching older case may be outside the first 100 rows.

### 8.4 Adding or editing a case fails

1. Read the validation list at the top of the page — it names the exact field at fault.
2. If you see "The case could not be saved", check `error_log` and the PHP console. Common SQL causes are a missing column (migration not applied) or a sequence that no longer exists.
3. Confirm both sequences exist:
   ```sql
   SELECT relname FROM pg_class WHERE relname IN ('case_ref_seq','patient_code_seq');
   ```
   If `patient_code_seq` is missing, run `migration-add-patient-code.sql`.
4. If the preview shows the wrong next number, compare it with the sequence state and fix with `setval()` (see 7.2).
5. If editing shows the wrong case or redirects to the list, the `id` in the URL does not exist. Find the correct `case_id` in the database.

### 8.5 Reports or CSV export fail

1. Reports and export use the **same filters**. Clear the filters on `reports.php` and try the download again.
2. If view works but CSV fails, inspect `export.php`:
   - it requires the `export` permission (`admin` or `records`);
   - both `fputcsv()` calls must pass `',', '"', '\\'` as extra arguments under PHP 8.6;
   - the header row and row array must have the same number of items.
3. Check the response in the browser's Network tab: status 200 and `Content-Type: text/csv` means it worked.
4. If the file opens with warnings, the warnings are being printed from PHP — check the PHP console/error log.

### 8.6 User management fails

1. Only `admin` can open `users.php` (`require_permission('manage_users')`). If an admin lost access, fix `roles.permissions` directly in SQL and sign out/in.
2. "That username is already taken" → change the username; `users.username` is unique.
3. "Password must be at least 8 characters" → the password is checked only when creating or when a new password is typed on edit.
4. A disabled account cannot sign in. Re-enable with:
   ```sql
   UPDATE users SET active = TRUE WHERE username = 'nurse1';
   ```
5. Changing a role takes effect for that user only after they sign out and back in, because permissions live in the session.

### 8.7 Layout or navigation broken

1. Hard-refresh the browser and confirm `assets/style.css` and `assets/app.js` return 200.
2. Check `includes/header.php`: menu items are wrapped in `can('create')` and `can('manage_users')`. If a menu item disappeared, the role permissions changed.
3. Check `includes/footer.php`: it closes the `.wrap.page` div opened in the header. If a new page forgets the footer, the layout will look wrong.
4. `assets/app.js` only does three small things: password-match checking, `data-confirm` dialogs and disabling submit buttons. It cannot cause data loss.

### 8.8 Whole site is down after a machine restart

The PostgreSQL service usually starts with Windows, but PHP does not.

1. `Get-Service postgresql-x64-18` → start with `Start-Service postgresql-x64-18` if stopped.
2. Run `.\start-injury-surveillance.bat` again. It is safe to run repeatedly: it checks what already exists and skips completed steps.
3. If port 8000 is occupied, either stop the other process or change `set "WEBPORT=8000"` in the batch file and use the new port in the browser.

### 8.9 A delete operation fails

Work through these in order:

1. **Check the permission.** The Delete links only appear when `can('delete')` is true. For accounts and roles, `manage_users` is also required. Permissions come from the session, so sign out and back in after any role change.
2. **Check the HTTP response.** In the browser's Network tab, a 403 means permission or CSRF. Reload the page to get a fresh CSRF token and try again. The token is single-page, not single-use, but an old page after a session restart will fail.
3. **Check the message.** The app explains its own refusals: self-delete, last administrator, role still in use, own role losing `manage_users`. Each has a specific fix described in the message and in section 6.12–6.14.
4. **Check the foreign key.** If deleting an account fails with `violates foreign key constraint injury_cases_recorded_by_fkey`, the older FK is still in place. Run `migration-fix-recorded-by-delete.sql`; the start script does this automatically too.
5. **Check the console.** A failed SQL statement is written to the PHP error log with `Case delete failed`, `User delete failed` or `Role delete failed`.
6. **If the row really is gone but the list still shows it**, the browser is showing a cached page. Refresh.
7. **If a case was deleted by mistake**, there is no undo. Restore from a `pg_dump` backup, or recreate the case — but note that its old reference and code will not be reused.

---

## Appendix A. Demo accounts

| Username | Password | Role | Menu access |
| --- | --- | --- | --- |
| admin | Admin@123 | admin | Dashboard, Cases, New case, Reports, Users & roles (both tabs); can delete cases, accounts and roles |
| nurse1 | Nurse@123 | officer | Dashboard, Cases, New case, Reports; add/edit cases, no deletion |
| records1 | Records@123 | records | Dashboard, Cases, Reports (CSV allowed), no deletion |
| viewer1 | View@123 | viewer | Dashboard, Cases, Reports (read only) |

## Appendix B. Database structure

```
roles
  role_id, role_name, description, permissions
  permissions = comma-separated: view, create, export, manage_users, delete

users
  user_id, username, full_name, password_hash, role_id, active, created_at
  role_id  -> roles.role_id (RESTRICT: delete the role's accounts first)

app_settings
  setting_key, setting_value
  keys: hospital_name, hospital_phone, hospital_email, hospital_address
  all editable on Users & roles -> Hospital settings

injury_cases
  case_id, case_reference, incident_date, incident_time,
  patient_code, patient_name, age, sex, mechanism, injury_type,
  body_part, classification, treatment, outcome, notes,
  recorded_by, created_at, updated_at
  recorded_by -> users.user_id ON DELETE SET NULL (keeps cases when a user is deleted)

sequences
  case_ref_seq       -> INJ-YYYY-NNNN
  patient_code_seq   -> PT-NNNN
  injury_cases_case_id_seq -> primary key values
```

Indexes exist on incident date, mechanism, patient code and patient name.

## Appendix C. Key assumptions and limitations to be aware of

- All data is synthetic. Do not enter real patient details.
- There is no audit trail of who changed or deleted a case, only `recorded_by`, `created_at` and `updated_at`. Failed deletes are written to the PHP error log, but successful ones are not recorded anywhere.
- Deletion is permanent: there is no recycle bin or undo. Restore from a backup if needed.
- Deleting an account keeps its cases but erases the recorder's name (`recorded_by` becomes NULL). Disabling the account preserves that name.
- A patient code is generated per case, so repeat visits by the same person would get different codes unless you change the design.
- The built-in PHP server handles one request at a time; it is fine for a demo but not for concurrent production use.
- `reset-database.bat` and `database.sql` destroy data. Always back up first.
- `runtime\pgdata` is an old, unused test cluster from earlier setup. The live database is the main PostgreSQL service on port 5432. Do not confuse the two.

---

**End of handover guide.** If something is still unclear, the fastest way to understand a page is to read its PHP file from top to bottom, then read `includes/common.php` for any helper function it calls.
