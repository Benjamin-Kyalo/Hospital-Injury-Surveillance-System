# Starting the Injury Surveillance project (no XAMPP)

Everything is inside this folder:

```
Hospital Injury Surveillance System\
  index.php, dashboard.php, cases.php, ...   the PHP app
  case_delete.php, users.php                 deletion; account and role management
  assets\                                    CSS and JavaScript
  includes\                                  shared PHP helpers and layout
  database.sql, create-database.sql          database setup
  migration-*.sql                            safe updates for older databases
  config.php                                 database connection settings
  runtime\php\                               portable PHP 8.6
  start-injury-surveillance.bat
  stop-injury-surveillance.bat
  reset-database.bat
  README.md, README-START-HERE.md
  SETUP-NEW-PC.md                            full guide for a new computer
  HANDOVER.md                                full technical handover
```

The app uses the **HISS** database in your **existing PostgreSQL 18** installation (Windows service `postgresql-x64-18`, port 5432). The main service stays running when you stop this app.

## Easiest way

Double-click **start-injury-surveillance.bat**, then open **http://localhost:8000/**.

Sign in with `admin` / `Admin@123`. Other accounts are listed in `README.md`.

Double-click **stop-injury-surveillance.bat** when finished.

The start script is safe to run repeatedly. It checks what already exists, creates `HISS` if needed, loads `database.sql` only when the tables are empty, and applies the `migration-*.sql` files only when the corresponding change is missing.

## Copying this to another PC

Follow **[SETUP-NEW-PC.md](SETUP-NEW-PC.md)**. In short:

1. Install PostgreSQL (port 5432, `postgres` password `1234` unless you change the config).
2. Copy the **entire** folder, including `runtime\php` — that folder is not tracked by Git.
3. If your password or ports differ, edit `config.php` and the `PGPASSWORD` / `WEBPORT` lines in the batch files.
4. Run `start-injury-surveillance.bat` and open `http://localhost:8000/`.
5. Work through the 13-step test table in SETUP-NEW-PC.md to confirm create, read, update, delete and permissions work.

## Commands in PowerShell

```powershell
cd "C:\Users\Admin IT\Downloads\CS50\Hospital Injury Surveillance System"
.\start-injury-surveillance.bat
```

Then open http://localhost:8000/. To stop only this app:

```powershell
.\stop-injury-surveillance.bat
```

## Syncing / loading the data into the existing database

If the HISS database is empty, or you want to put back the original demo data, run these from the same folder (password is `1234`):

```powershell
$env:PGPASSWORD = "1234"
$pg = "C:\Program Files\PostgreSQL\18\bin"

# Create HISS only if it does not exist yet
& "$pg\psql.exe" -h 127.0.0.1 -p 5432 -U postgres -d postgres -f ".\create-database.sql"

# Load tables, roles, demo users and 16 sample cases into HISS
& "$pg\psql.exe" -h 127.0.0.1 -p 5432 -U postgres -d HISS -f ".\database.sql"

# Check it worked
& "$pg\psql.exe" -h 127.0.0.1 -p 5432 -U postgres -d HISS -c "SELECT count(*) AS cases FROM injury_cases; SELECT count(*) AS users FROM users;"
```

Expected result after a fresh load: `cases = 16` and `users = 4`.

Warning: `database.sql` replaces the HISS tables. Do not run it if you have added cases you want to keep. `reset-database.bat` does the same thing after asking for confirmation.

## Migrations for databases created by an older version

All five migrations are non-destructive and safe to run more than once. The start script applies them automatically when needed, or you can run them yourself with the same `psql` command and a different `-f` filename:

```powershell
$env:PGPASSWORD = "1234"
$pg = "C:\Program Files\PostgreSQL\18\bin"

& "$pg\psql.exe" -h 127.0.0.1 -p 5432 -U postgres -d HISS -f ".\migration-add-patient-name.sql"
& "$pg\psql.exe" -h 127.0.0.1 -p 5432 -U postgres -d HISS -f ".\migration-add-patient-code.sql"
& "$pg\psql.exe" -h 127.0.0.1 -p 5432 -U postgres -d HISS -f ".\migration-add-delete-permission.sql"
& "$pg\psql.exe" -h 127.0.0.1 -p 5432 -U postgres -d HISS -f ".\migration-fix-recorded-by-delete.sql"
& "$pg\psql.exe" -h 127.0.0.1 -p 5432 -U postgres -d HISS -f ".\migration-add-settings.sql"
```

| File | What it adds |
| --- | --- |
| `migration-add-patient-name.sql` | `injury_cases.patient_name` plus fictional names for the original 16 demo cases |
| `migration-add-patient-code.sql` | `patient_code_seq`, starting after the highest existing code |
| `migration-add-delete-permission.sql` | `delete` permission for the built-in admin role |
| `migration-fix-recorded-by-delete.sql` | `ON DELETE SET NULL` on `injury_cases.recorded_by`, so deleting an account keeps its cases |
| `migration-add-settings.sql` | `app_settings` table with the editable `hospital_name` shown in the header/footer |

After a permissions change, affected users must sign out and back in.

## Manual start commands (what the start script does)

```powershell
# Check the main PostgreSQL service is running
Get-Service postgresql-x64-18

# Start only PHP's built-in server (leave this window open)
& "$PWD\runtime\php\php.exe" -S 127.0.0.1:8000 -t "$PWD"
```

Stop with `Ctrl+C` in that window, or with `.\stop-injury-surveillance.bat` if you used the script.

## Changing the database connection

Edit `config.php`. Current values are host `127.0.0.1`, port `5432`, database `HISS`, user `postgres`, password `1234`. The app reads these values on every request, so no restart of PostgreSQL is needed — just refresh the page. If the password changes, also update `start-injury-surveillance.bat` and `reset-database.bat`.

## Where to read more

- `README.md` — features, accounts, CRUD/permission matrix, file list
- `SETUP-NEW-PC.md` — full installation and verification guide for another computer
- `HANDOVER.md` — architecture, how the pages talk to the database, maintenance and troubleshooting
