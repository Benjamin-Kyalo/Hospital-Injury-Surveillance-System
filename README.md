# Injury Surveillance System (HISS)

A small web prototype for the Hospital Injury Surveillance and Reporting Management System final year project. It uses plain HTML, CSS, JavaScript, PHP and PostgreSQL. There is no XAMPP, Apache or MySQL involved.

## Running it

From this folder, double-click **start-injury-surveillance.bat**. That script:

1. checks your existing PostgreSQL service on port 5432,
2. creates the `HISS` database and loads `database.sql` if the tables are missing,
3. starts PHP's built-in web server on port 8000.

Then open **http://localhost:8000/** in your browser. Use **stop-injury-surveillance.bat** to stop the PHP server. Your PostgreSQL Windows service is left running.

To put the original demo records back, run **reset-database.bat**. It replaces the HISS tables, so only use it when you want a clean start. See `README-START-HERE.md` for the equivalent commands.

Do not close the small "HISS PHP server" window while using the site — closing it stops PHP.

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
config.php          database settings (HISS on port 5432)
create-database.sql creates the HISS database with the correct capital letters
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
runtime/php/        portable PHP 8.6 with pdo_pgsql enabled
```

## Notes

All records are synthetic demo data. Passwords are bcrypt hashes, forms use CSRF tokens, queries use prepared statements, and the permission list for each role lives in the `roles` table.
