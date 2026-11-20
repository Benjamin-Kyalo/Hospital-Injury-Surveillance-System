# Injury Surveillance System (HISS)

A small web prototype for the Hospital Injury Surveillance and Reporting Management System final year project. It uses plain HTML, CSS, JavaScript, PHP and PostgreSQL. There is no XAMPP, Apache or MySQL involved.

## Running it

From this folder, double-click **start-injury-surveillance.bat**. That script:

1. starts a local PostgreSQL cluster stored in `runtime\pgdata` on port 55432,
2. creates the `HISS` database and loads `database.sql` the first time,
3. starts PHP's built-in web server on port 8000.

Then open **http://localhost:8000/** in your browser. Use **stop-injury-surveillance.bat** to shut both down. If you want the original demo records back, run **reset-database.bat**.

Do not close the small “HISS PHP server” window while using the site — closing it stops PHP.

## Accounts

| Username | Password | Role | Can do |
| --- | --- | --- | --- |
| admin | Admin@123 | admin | everything, including user accounts and CSV |
| nurse1 | Nurse@123 | officer | add and edit cases |
| records1 | Records@123 | records | search, view reports, download CSV |
| viewer1 | View@123 | viewer | view cases and reports only |

## What it does

- Sign in and sign out, with role-based menus.
- Add, view, search and edit injury cases using patient codes rather than names.
- Dashboard with basic counts and summaries.
- Reports with filters and a CSV download for admin and records users.
- Admin screen for creating accounts, changing roles and disabling accounts.

## Files

```
config.php          database settings (HISS, port 55432)
database.sql        tables, roles and sample cases
index.php           sign-in page
dashboard.php       summary counts
cases.php           search and list
case_view.php       full case details
case_form.php       add / edit case
reports.php         filtered summaries
export.php          CSV download
users.php           admin: manage accounts
includes/           database connection, helpers and page layout
assets/             stylesheet and a little JavaScript
```

## Using your main PostgreSQL instead

If you already have PostgreSQL running on the usual port 5432 and want to use that instead of the bundled cluster, edit `config.php`: set `port` to `5432` and put your PostgreSQL password in `password`. Create the `HISS` database and load `database.sql` into it, then start only the PHP server:

```
runtime\php\php.exe -S 127.0.0.1:8000 -t .
```

## Notes

All records are synthetic demo data. Passwords are bcrypt hashes, forms use CSRF tokens, queries use prepared statements, and the permission list for each role lives in the `roles` table.
