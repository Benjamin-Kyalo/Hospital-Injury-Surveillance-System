@echo off
setlocal
set "ROOT=%~dp0"
set "APP=%ROOT%"
set "PHP=%ROOT%runtime\php\php.exe"
set "PGPORT=5432"
set "WEBPORT=8000"
set "PGPASSWORD=1234"

echo.
echo == Injury Surveillance - local start (existing PostgreSQL) ==
echo.

if not exist "%PHP%" (
    echo Portable PHP not found at %PHP%
    exit /b 1
)

set "PGBIN="
for /f "tokens=3,*" %%A in ('reg query "HKLM\SOFTWARE\PostgreSQL\Installations" /s /v "Base Directory" ^| findstr /i "REG_SZ"') do set "PGBIN=%%B\bin"
if "%PGBIN%"=="" if exist "C:\Program Files\PostgreSQL\18\bin\psql.exe" set "PGBIN=C:\Program Files\PostgreSQL\18\bin"
if "%PGBIN%"=="" (
    echo Could not find the PostgreSQL binaries.
    exit /b 1
)

echo Checking PostgreSQL on port %PGPORT% ...
"%PGBIN%\psql.exe" -h 127.0.0.1 -p %PGPORT% -U postgres -d postgres -w -tAc "select 1" >nul 2>&1
if errorlevel 1 (
    echo Cannot connect to PostgreSQL on port %PGPORT%.
    echo Make sure the "postgresql-x64-18" Windows service is running
    echo and that the postgres password in config.php is correct.
    exit /b 1
)

"%PGBIN%\psql.exe" -h 127.0.0.1 -p %PGPORT% -U postgres -d postgres -w -tAc "select 1 from pg_database where datname = 'HISS'" | findstr "1" >nul
if errorlevel 1 (
    echo Creating the HISS database ...
    "%PGBIN%\psql.exe" -h 127.0.0.1 -p %PGPORT% -U postgres -d postgres -w -f "%APP%create-database.sql"
)

"%PGBIN%\psql.exe" -h 127.0.0.1 -p %PGPORT% -U postgres -d HISS -w -tAc "select count(*) from pg_tables where schemaname='public'" | findstr /r /c:"^0$" >nul
if not errorlevel 1 (
    echo Loading the HISS tables and 16 demo cases ...
    "%PGBIN%\psql.exe" -h 127.0.0.1 -p %PGPORT% -U postgres -d HISS -w -f "%APP%database.sql"
) else (
    echo HISS tables already present.
)

"%PGBIN%\psql.exe" -h 127.0.0.1 -p %PGPORT% -U postgres -d HISS -w -tAc "select count(*) from information_schema.columns where table_name='injury_cases' and column_name='patient_name'" | findstr /r /c:"^0$" >nul
if not errorlevel 1 (
    echo Adding the patient name field to the existing cases ...
    "%PGBIN%\psql.exe" -h 127.0.0.1 -p %PGPORT% -U postgres -d HISS -w -f "%APP%migration-add-patient-name.sql"
)

"%PGBIN%\psql.exe" -h 127.0.0.1 -p %PGPORT% -U postgres -d HISS -w -tAc "select count(*) from pg_class where relname='patient_code_seq'" | findstr /r /c:"^0$" >nul
if not errorlevel 1 (
    echo Adding the patient code sequence ...
    "%PGBIN%\psql.exe" -h 127.0.0.1 -p %PGPORT% -U postgres -d HISS -w -f "%APP%migration-add-patient-code.sql"
)

"%PGBIN%\psql.exe" -h 127.0.0.1 -p %PGPORT% -U postgres -d HISS -w -tAc "select count(*) from roles where role_name='admin' and permissions !~ '(^|,)delete(,|$)'" | findstr /r /c:"^1$" >nul
if not errorlevel 1 (
    echo Adding the delete permission to the admin role ...
    "%PGBIN%\psql.exe" -h 127.0.0.1 -p %PGPORT% -U postgres -d HISS -w -f "%APP%migration-add-delete-permission.sql"
)

"%PGBIN%\psql.exe" -h 127.0.0.1 -p %PGPORT% -U postgres -d HISS -w -tAc "select count(*) from pg_constraint where conname='injury_cases_recorded_by_fkey' and confdeltype <> 'n'" | findstr /r /c:"^1$" >nul
if not errorlevel 1 (
    echo Updating case authorship links ...
    "%PGBIN%\psql.exe" -h 127.0.0.1 -p %PGPORT% -U postgres -d HISS -w -f "%APP%migration-fix-recorded-by-delete.sql"
)

"%PGBIN%\psql.exe" -h 127.0.0.1 -p %PGPORT% -U postgres -d HISS -w -tAc "select count(*) from pg_tables where schemaname='public' and tablename='app_settings'" | findstr /r /c:"^0$" >nul
if not errorlevel 1 (
    echo Adding the editable settings table ...
    "%PGBIN%\psql.exe" -h 127.0.0.1 -p %PGPORT% -U postgres -d HISS -w -f "%APP%migration-add-settings.sql"
)

netstat -ano | findstr /c:"127.0.0.1:%WEBPORT%" | findstr /c:"LISTENING" >nul
if not errorlevel 1 (
    echo PHP is already listening on http://localhost:%WEBPORT% - nothing to start.
) else (
    echo Starting PHP on http://localhost:%WEBPORT% ...
    start "HISS PHP server" /MIN "%PHP%" -S 127.0.0.1:%WEBPORT% -t "%APP%"
)

echo.
echo Ready. Open http://localhost:%WEBPORT%/ in your browser.
echo Run stop-injury-surveillance.bat when you are done.
echo.
