@echo off
setlocal
set "ROOT=%~dp0"
set "PGDATA=%ROOT%runtime\pgdata"
set "PGLOG=%ROOT%runtime\logs\postgres.log"
set "PHP=%ROOT%runtime\php\php.exe"
set "APP=%ROOT%"
set "PGPORT=55432"
set "WEBPORT=8000"

echo.
echo == Injury Surveillance - local start ==
echo.

if not exist "%PHP%" (
    echo Portable PHP not found at %PHP%
    exit /b 1
)

set "PGBIN="
for /f "tokens=3,*" %%A in ('reg query "HKLM\SOFTWARE\PostgreSQL\Installations" /s /v "Base Directory" ^| findstr /i "REG_SZ"') do set "PGBIN=%%B\bin"
if "%PGBIN%"=="" if exist "C:\Program Files\PostgreSQL\18\bin\pg_ctl.exe" set "PGBIN=C:\Program Files\PostgreSQL\18\bin"
if "%PGBIN%"=="" (
    echo Could not find the PostgreSQL binaries.
    exit /b 1
)

if not exist "%PGDATA%\PG_VERSION" (
    echo Creating local PostgreSQL cluster in runtime\pgdata ...
    "%PGBIN%\initdb.exe" -D "%PGDATA%" -U postgres --auth=trust --encoding=UTF8
    if errorlevel 1 exit /b 1
)

"%PGBIN%\pg_ctl.exe" -D "%PGDATA%" status >nul 2>&1
if errorlevel 1 (
    echo Starting PostgreSQL on 127.0.0.1:%PGPORT% ...
    "%PGBIN%\pg_ctl.exe" -D "%PGDATA%" -o "-p %PGPORT% -h 127.0.0.1" -l "%PGLOG%" start
    if errorlevel 1 exit /b 1
) else (
    echo PostgreSQL already running on port %PGPORT%.
)

"%PGBIN%\psql.exe" -h 127.0.0.1 -p %PGPORT% -U postgres -d postgres -tAc "SELECT 1 FROM pg_database WHERE datname = 'HISS'" | findstr "1" >nul
if errorlevel 1 (
    echo Creating database HISS and loading the tables and demo records ...
    "%PGBIN%\psql.exe" -h 127.0.0.1 -p %PGPORT% -U postgres -d postgres -f "%APP%\create-database.sql"
    "%PGBIN%\psql.exe" -h 127.0.0.1 -p %PGPORT% -U postgres -d HISS -f "%APP%\database.sql"
)

echo Starting PHP on http://localhost:%WEBPORT% ...
start "HISS PHP server" /MIN "%PHP%" -S 127.0.0.1:%WEBPORT% -t "%APP%"

echo.
echo Ready. Open http://localhost:%WEBPORT%/ in your browser.
echo Run stop-injury-surveillance.bat when you are done.
echo.
