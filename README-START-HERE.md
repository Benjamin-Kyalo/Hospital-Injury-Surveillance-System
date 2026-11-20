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
  runtime\pgdata\                            this project's PostgreSQL data
  runtime\logs\                              server logs
  start-injury-surveillance.bat
  stop-injury-surveillance.bat
  reset-database.bat
```

The app uses the `HISS` database on port 55432. Your main PostgreSQL service on port 5432 is untouched.

## Easiest way

Double-click **start-injury-surveillance.bat**, then open **http://localhost:8000/**.

Sign in with `admin` / `Admin@123`. Other accounts are listed in `README.md`.

Double-click **stop-injury-surveillance.bat** when finished.

## Commands instead of double-clicking

Open PowerShell, go to this folder, and run:

```powershell
cd "C:\Users\Admin IT\Downloads\CS50\Hospital Injury Surveillance System"
.\start-injury-surveillance.bat
```

Then open http://localhost:8000/ in your browser. To shut down:

```powershell
.\stop-injury-surveillance.bat
```

## Manual commands (what the start script does)

From the same folder:

```powershell
# 1. Start PostgreSQL for this project
& "C:\Program Files\PostgreSQL\18\bin\pg_ctl.exe" `
  -D "$PWD\runtime\pgdata" `
  -o "-p 55432 -h 127.0.0.1" `
  -l "$PWD\runtime\logs\postgres.log" start

# 2. First time only: create the HISS database and load the tables
& "C:\Program Files\PostgreSQL\18\bin\psql.exe" -h 127.0.0.1 -p 55432 -U postgres -d postgres -f "$PWD\create-database.sql"
& "C:\Program Files\PostgreSQL\18\bin\psql.exe" -h 127.0.0.1 -p 55432 -U postgres -d HISS -f "$PWD\database.sql"

# 3. Start PHP's built-in server
& "$PWD\runtime\php\php.exe" -S 127.0.0.1:8000 -t "$PWD"
```

Leave that PHP command running while you use the site. To stop things:

```powershell
Stop-Process -Name php -Force
& "C:\Program Files\PostgreSQL\18\bin\pg_ctl.exe" -D "$PWD\runtime\pgdata" -m fast stop
```

`reset-database.bat` puts the original 16 demo cases back if you change data.
