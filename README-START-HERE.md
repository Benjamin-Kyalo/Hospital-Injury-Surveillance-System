# Starting the Injury Surveillance project (no XAMPP)

Everything is inside this folder:

```
Hospital Injury Surveillance System\
  index.php, dashboard.php, cases.php, ...   the PHP app
  assets\                                    CSS and JavaScript
  includes\                                  shared PHP helpers and layout
  database.sql, create-database.sql          database setup
  config.php                                 database connection settings
  runtime\php\                               portable PHP 8.6
  start-injury-surveillance.bat
  stop-injury-surveillance.bat
  reset-database.bat
```

The app uses the **HISS** database in your **existing PostgreSQL 18** installation (Windows service `postgresql-x64-18`, port 5432). The main service stays running when you stop this app.

## Easiest way

Double-click **start-injury-surveillance.bat**, then open **http://localhost:8000/**.

Sign in with `admin` / `Admin@123`. Other accounts are listed in `README.md`.

Double-click **stop-injury-surveillance.bat** when finished.

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

Expected result: `cases = 16` and `users = 4`.

Warning: `database.sql` replaces the HISS tables. Do not run it if you have added real cases you want to keep. `reset-database.bat` does the same thing after asking for confirmation.

To add the patient name field to an existing HISS database **without losing any data**, run:

```powershell
& "$pg\psql.exe" -h 127.0.0.1 -p 5432 -U postgres -d HISS -f ".\migration-add-patient-name.sql"
```

The start script also runs this automatically the first time it finds an older database without the `patient_name` column.

Patient codes are numbered from a second sequence. If an older database does not have it yet, the start script runs `migration-add-patient-code.sql`, which continues from the highest code already stored. Or run it yourself with the same `psql` command, changing the `-f` filename.

## Manual start commands (what the start script does)

```powershell
# Check the main PostgreSQL service is running
Get-Service postgresql-x64-18

# Start only PHP's built-in server (leave this window open)
& "$PWD\runtime\php\php.exe" -S 127.0.0.1:8000 -t "$PWD"
```

Stop with:

```powershell
Stop-Process -Name php -Force
```

## Changing the database connection

Edit `config.php`. Current values are host `127.0.0.1`, port `5432`, database `HISS`, user `postgres`, password `1234`. The app reads these values on every request, so no restart of PostgreSQL is needed — just refresh the page.
