# Setting up HISS on another PC (copy, test, run)

This guide takes someone who has never seen the project from **zero to a working local copy**. Follow it in order. Every command is run in PowerShell unless stated otherwise.

---

## Step 1 — What you need

| Requirement | Notes |
| --- | --- |
| Windows 10 or 11 | The supplied start/stop scripts are Windows batch files. |
| This **whole project folder** | It must include `runtime\php\php.exe`, `config.php`, the `.sql` files and all `.php` files. |
| PostgreSQL 18 (Windows installer) | Download from `https://www.postgresql.org/download/windows/`. Use the default port **5432**. |
| A modern browser | Chrome, Edge or Firefox. |

Important: `runtime\` is listed in `.gitignore`. If someone only clones the Git repository, the portable PHP folder will be **missing**. In that case either copy the folder from the original PC or install PHP yourself and point the start script at it (Step 4).

---

## Step 2 — Install PostgreSQL

1. Run the PostgreSQL installer and keep the default port `5432`.
2. When asked for the `postgres` password, use `1234` if you want the project to work without editing anything. If you choose a different password, write it down — you will need it in Step 4.
3. Finish the installer. It normally creates a Windows service called `postgresql-x64-18` (the exact name can differ by version).
4. Confirm the service is running:

```powershell
Get-Service | Where-Object { $_.Name -like '*postgres*' }
```

If its status is `Stopped`:

```powershell
Start-Service postgresql-x64-18
```

5. Confirm the command-line tools were installed:

```powershell
Test-Path "C:\Program Files\PostgreSQL\18\bin\psql.exe"
```

`True` means the start script will find them automatically. If the path is different (for example PostgreSQL 17), the start script also reads the install location from the Windows registry; if that fails, edit the `PGBIN` fallback lines in `start-injury-surveillance.bat` and `reset-database.bat`.

---

## Step 3 — Copy the project

1. Copy the entire folder, for example from a USB drive or zip file, to somewhere simple such as:

```
C:\Users\<your name>\Documents\Hospital Injury Surveillance System
```

2. Open that folder and confirm these exist:

```
runtime\php\php.exe
config.php
database.sql
create-database.sql
start-injury-surveillance.bat
stop-injury-surveillance.bat
index.php
includes\common.php
```

3. Do not move files out of the folder. The PHP pages use `__DIR__` paths that assume this layout.

---

## Step 4 — Configure the connection

Open `config.php` in Notepad. It currently says:

```php
return [
    'host'     => '127.0.0.1',
    'port'     => '5432',
    'dbname'   => 'HISS',
    'user'     => 'postgres',
    'password' => '1234',
];
```

Change `password` if you chose a different PostgreSQL password. Change `port` only if your PostgreSQL uses a different port. The app reads this file on every request, so no restart is needed afterwards.

Also check the two batch files. Both contain:

```
set "PGPASSWORD=1234"
```

Change `1234` there too if your password is different, otherwise the automatic database setup will fail. These are in `start-injury-surveillance.bat` and `reset-database.bat`.

If port `8000` is already used by another program on the new PC, change this line in `start-injury-surveillance.bat`:

```
set "WEBPORT=8000"
```

Use the new port in the browser address afterwards.

Portable PHP check:

```powershell
cd "C:\Users\<your name>\Documents\Hospital Injury Surveillance System"
& ".\runtime\php\php.exe" -v
& ".\runtime\php\php.exe" -m
```

The module list must include `pdo_pgsql`, `pgsql` and `mbstring`. If `runtime\php` is missing, install a normal PHP 8.x for Windows with those extensions, then either copy `php.exe` and its `php.ini` into `runtime\php\` or change `set "PHP=..."` at the top of `start-injury-surveillance.bat` to point at your PHP executable.

---

## Step 5 — First run

1. In PowerShell, go to the project folder and start the app:

```powershell
cd "C:\Users\<your name>\Documents\Hospital Injury Surveillance System"
.\start-injury-surveillance.bat
```

2. Read what the script prints. On a brand-new PostgreSQL install you should see, in order:

```
Checking PostgreSQL on port 5432 ...
Creating the HISS database ...
Loading the HISS tables and 16 demo cases ...
Starting PHP on http://localhost:8000 ...
Ready. Open http://localhost:8000/ in your browser.
```

If the database already existed, it prints `HISS tables already present.` and only applies migrations that are actually missing. The script is safe to run repeatedly.

3. Open `http://localhost:8000/` in the browser.

4. Sign in with:

```
username: admin
password: Admin@123
```

The other demo accounts are in `README.md`.

If you see a database error page instead of the sign-in page, go to Step 8.

---

## Step 6 — Confirm everything was copied correctly

Work through this short test on the new PC. It also proves that Create, Read, Update and Delete all work and that permissions are enforced.

| # | Action | Expected result |
| --- | --- | --- |
| 1 | Sign in as `admin`. | Dashboard opens; menu shows Dashboard, Cases, New case, Reports, Users, Roles. |
| 2 | Click **New case**. | Two read-only fields show the next reference (`INJ-2026-0017`) and patient code (`PT-1017`) after a fresh load of `database.sql`. If you copied an existing database, the numbers continue from its last case. |
| 3 | Fill the required fields, enter patient name `Local Test`, click **Save case**. | Details page opens with the saved reference and name. |
| 4 | On **Cases**, search `Local Test`. | The new case appears. Search `PT-1017` and the reference shown in step 2 too — each finds it. |
| 5 | Click **Edit** on the test case, change the notes, save. | Details page shows the new notes and a "Last updated" time. |
| 6 | Click **Delete this case** on the test case, accept the confirmation. | Case disappears from the list; a success message appears. |
| 7 | **Users → Add a user** named `localtest`, role `officer`, password `Local@1234`. | User appears in the table. |
| 8 | **Users → Delete** `localtest`, accept the confirmation. | User disappears; a success message says their cases are kept. |
| 9 | **Roles → Add a role** named `localrole`, permission *View cases and reports*, then Edit it and add *Create and edit cases*. | Table shows `view, create` for that role. |
| 10 | **Roles → Delete** `localrole`. | Role is removed (it has no accounts). |
| 11 | **Reports →** apply a filter, then **Download CSV**. | A `.csv` file downloads and opens with a header row including "Patient name". |
| 12 | Sign out, sign in as `viewer1` / `View@123`. | No New case, Users or Roles links; no Delete links. |
| 13 | Run `.\stop-injury-surveillance.bat`, then refresh the browser. | The site no longer loads — only PHP was stopped. |

If all 13 pass, the copy is working. Delete any test records you left behind.

---

## Step 7 — Everyday use

Start:

```powershell
cd "C:\Users\<your name>\Documents\Hospital Injury Surveillance System"
.\start-injury-surveillance.bat
```

Stop:

```powershell
.\stop-injury-surveillance.bat
```

The stop script only closes the small "HISS PHP server" window. The PostgreSQL Windows service stays running because other applications may use it.

After a PC restart, PostgreSQL normally starts with Windows but PHP does not — just run the start script again.

---

## Step 8 — Problems on a new PC and what to do

| Problem | Cause | Fix |
| --- | --- | --- |
| `Portable PHP not found at ...` | `runtime\php` was not copied | Copy it from the original PC, or install PHP and edit the `PHP=` line in the start script. |
| `Cannot connect to PostgreSQL on port 5432` | Service stopped, wrong password or wrong port | Start the service; check `config.php` and the `PGPASSWORD` lines in the batch files. |
| `Could not find the PostgreSQL binaries` | Registry lookup failed and the hard-coded path is wrong | Edit `PGBIN` in the batch files to the real `bin` folder. |
| `database "HISS" does not exist` after manual setup | Database created as lowercase `hiss` | Run `create-database.sql` — it quotes the name as `"HISS"`. |
| Browser shows `localhost refused to connect` | PHP not running, or different port | Run the start script; check the `WEBPORT` value. |
| White page / PHP error text | Missing PHP extension or syntax error | Run `& ".\runtime\php\php.exe" -m`; run `& ".\runtime\php\php.exe" -l <file>`. |
| Password prompt from `psql` | Batch scripts use `-w` and rely on `PGPASSWORD` | Set `$env:PGPASSWORD = "1234"` in PowerShell or fix the batch files. |
| Delete links missing | Account lacks the `delete` permission | Sign in as admin, check **Roles**, then sign out and back in. |
| Search finds nothing | Filters still active | Click **Clear** on Cases or Reports. |

---

## Step 9 — Put back the original demo data (optional)

This destroys any cases and accounts you added, so back up first if needed:

```powershell
.\reset-database.bat
```

Answer `y` when asked. The script reloads `database.sql`: 4 roles, 4 accounts and 16 synthetic cases.

To make a backup before resetting:

```powershell
$env:PGPASSWORD = "1234"
$pg = "C:\Program Files\PostgreSQL\18\bin"
& "$pg\pg_dump.exe" -h 127.0.0.1 -p 5432 -U postgres -d HISS -F p -f ".\hiss_backup.sql"
```

Restore it into an empty HISS database with:

```powershell
& "$pg\psql.exe" -h 127.0.0.1 -p 5432 -U postgres -d HISS -f ".\hiss_backup.sql"
```

---

## Step 10 — Manual setup instead of the batch files

If you cannot use the batch files, do this by hand from the project folder:

```powershell
$env:PGPASSWORD = "1234"
$pg = "C:\Program Files\PostgreSQL\18\bin"

# 1. create the database (quoted name!)
& "$pg\psql.exe" -h 127.0.0.1 -p 5432 -U postgres -d postgres -f ".\create-database.sql"

# 2. load tables, roles, accounts and demo cases
& "$pg\psql.exe" -h 127.0.0.1 -p 5432 -U postgres -d HISS -f ".\database.sql"

# 3. check
& "$pg\psql.exe" -h 127.0.0.1 -p 5432 -U postgres -d HISS -c "SELECT count(*) FROM injury_cases; SELECT count(*) FROM users;"

# 4. start PHP in this window (leave it open)
& ".\runtime\php\php.exe" -S 127.0.0.1:8000 -t "$PWD"
```

Expected counts: `16` cases and `4` users after step 2. Press `Ctrl+C` in that window to stop PHP.

`database.sql` already contains the patient name column, both sequences, the `delete` permission and the `ON DELETE SET NULL` link. The `migration-*.sql` files are only needed for databases created by an older version of the project; the start script applies them automatically when necessary.
