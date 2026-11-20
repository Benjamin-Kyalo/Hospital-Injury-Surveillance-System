# Injury Surveillance System (HISS)

A small web prototype for the Hospital Injury Surveillance and Reporting Management System final year project. It uses plain HTML, CSS, JavaScript, PHP and PostgreSQL. There is no XAMPP, Apache or MySQL involved.

## Running it

From this folder, double-click **start-injury-surveillance.bat**. That script:

1. checks your existing PostgreSQL service on port 5432,
2. creates the `HISS` database and loads `database.sql` if the tables are missing,
3. applies any missing migrations without deleting existing data,
4. starts PHP's built-in web server on port 8000.

Then open **http://localhost:8000/** in your browser. Use **stop-injury-surveillance.bat** to stop the PHP server. Your PostgreSQL Windows service is left running.

To put the original demo records back, run **reset-database.bat**. It replaces the HISS tables, so only use it when you want a clean start. See `README-START-HERE.md` for the equivalent commands.

Do not close the small "HISS PHP server" window while using the site — closing it stops PHP.

## Setting it up on your own PC

See **[SETUP-NEW-PC.md](SETUP-NEW-PC.md)** for a complete step-by-step guide: install PostgreSQL, copy the folder (including `runtime\php`), change the password/ports if needed, run the app, and test every feature. See **HANDOVER.md** for the full technical handover.

## Accounts

| Username | Password | Role | Can do |
| --- | --- | --- | --- |
| admin | Admin@123 | admin | everything: cases, reports, CSV, accounts, roles and deletion |
| nurse1 | Nurse@123 | officer | add and edit cases |
| records1 | Records@123 | records | search, view reports, download CSV |
| viewer1 | View@123 | viewer | view cases and reports only |

If you change the PostgreSQL `postgres` password, update both `config.php` and the `PGPASSWORD` line in the two batch files.

## What it does

- Sign in and sign out, with role-based menus and server-side permission checks.
- **Cases — full CRUD:** add, view, search, edit and delete injury cases. Each case has a patient code and an optional patient name. The search box on Cases and Reports finds cases by reference, code or name.
- Case references (`INJ-2026-0016`) and patient codes (`PT-1016`) are assigned automatically from PostgreSQL sequences. The New Case form shows both next values as read-only fields; editing a case shows its existing values, which never change. Deleting a case does not reuse its numbers.
- Dashboard with basic counts and summaries.
- Reports with filters and a CSV download for admin and records users.
- **Users & roles — one admin page with three tabs** at `users.php`: admins can manage staff accounts, manage roles and permissions, and edit the **hospital profile** (name, phone number, email address and physical address). No separate Roles page or menu item.
- The old affiliation and demo-warning notices were removed from the layout. The header shows the hospital name you set under **Users & roles → Hospital settings**; the footer shows the name plus any phone, email and address you provide (default name: `General Hospital`, contact fields blank).
- Account safety: you cannot delete yourself, deactivate yourself, move yourself to a role without `manage_users`, or delete the last administrator. Cases recorded by a deleted account are kept, with the recorder shown as "unknown user".
- Role safety: a role that still has accounts cannot be deleted, and you cannot remove `manage_users` from your own role. Users must sign out and back in after their role's permissions change.
- Every change is protected: POST-only actions, CSRF tokens, prepared statements, and permission checks on the server.

## CRUD and permissions at a glance

| Data | Create | Read | Update | Delete |
| --- | --- | --- | --- | --- |
| Cases | `create` (admin, officer) | `view` (all roles) | `create` (admin, officer) | `delete` (admin by default) |
| Accounts | `manage_users` (admin) | `manage_users` (admin) | `manage_users` (admin) | `manage_users` + `delete` (admin) |
| Roles | `manage_users` (admin) | `manage_users` (admin) | `manage_users` (admin) | `manage_users` + `delete` (admin) |
| Hospital profile settings | `manage_users` (admin) | shown in header/footer on every page | `manage_users` (admin) | not applicable |

Open `users.php` and use the **Accounts** tab for people and the **Roles & permissions** tab for their access. Permission names are stored in the `roles.permissions` column and can be changed there or in `database.sql`.

## Files

```
config.php          database settings (HISS on port 5432)
create-database.sql creates the HISS database with the correct capital letters
database.sql        tables, roles, accounts and sample cases
migration-add-patient-name.sql       adds the name field without removing existing data
migration-add-patient-code.sql       adds the patient code sequence to an existing database
migration-add-delete-permission.sql  adds the delete permission to the admin role
migration-fix-recorded-by-delete.sql keeps cases when their recorder is deleted
migration-add-settings.sql       adds the editable hospital profile settings
index.php           sign-in page
dashboard.php       summary counts
cases.php           search and list
case_view.php       full case details
case_form.php       add / edit case
case_delete.php     delete case (POST, CSRF, delete permission)
reports.php         filtered summaries
export.php          CSV download
users.php           admin: Accounts and Roles tabs; full CRUD for accounts, roles and permissions
roles.php           compatibility redirect to users.php?tab=roles (no duplicate interface)
logout.php          ends the session
includes/           database connection, helpers and page layout
assets/             stylesheet and a little JavaScript
runtime/php/        portable PHP 8.6 with pdo_pgsql enabled
README.md           this file
README-START-HERE.md quick start and manual database commands
SETUP-NEW-PC.md     copy the project to another PC and test it
HANDOVER.md         full technical handover and troubleshooting
```

## Notes

All records are synthetic demo data. Passwords are bcrypt hashes, forms use CSRF tokens, queries use prepared statements, and the permission list for each role lives in the `roles` table. The PHP built-in server is for local demonstration; see HANDOVER.md for production deployment advice.
