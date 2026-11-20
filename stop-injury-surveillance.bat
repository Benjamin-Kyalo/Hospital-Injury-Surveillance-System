@echo off
setlocal
set "ROOT=%~dp0"
set "PGDATA=%ROOT%runtime\pgdata"

set "PGBIN="
for /f "tokens=3,*" %%A in ('reg query "HKLM\SOFTWARE\PostgreSQL\Installations" /s /v "Base Directory" ^| findstr /i "REG_SZ"') do set "PGBIN=%%B\bin"
if "%PGBIN%"=="" if exist "C:\Program Files\PostgreSQL\18\bin\pg_ctl.exe" set "PGBIN=C:\Program Files\PostgreSQL\18\bin"

echo Stopping the PHP server ...
taskkill /FI "WINDOWTITLE eq HISS PHP server*" /F >nul 2>&1

if not "%PGBIN%"=="" (
    "%PGBIN%\pg_ctl.exe" -D "%PGDATA%" status >nul 2>&1
    if not errorlevel 1 (
        echo Stopping PostgreSQL ...
        "%PGBIN%\pg_ctl.exe" -D "%PGDATA%" -m fast stop
    )
)

echo Done.
