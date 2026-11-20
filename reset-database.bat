@echo off
setlocal
set "ROOT=%~dp0"
set "APP=%ROOT%"
set "PGPORT=5432"
set "PGPASSWORD=1234"

set "PGBIN="
for /f "tokens=3,*" %%A in ('reg query "HKLM\SOFTWARE\PostgreSQL\Installations" /s /v "Base Directory" ^| findstr /i "REG_SZ"') do set "PGBIN=%%B\bin"
if "%PGBIN%"=="" if exist "C:\Program Files\PostgreSQL\18\bin\psql.exe" set "PGBIN=C:\Program Files\PostgreSQL\18\bin"
if "%PGBIN%"=="" (
    echo Could not find psql.exe.
    exit /b 1
)

echo This replaces the HISS tables on port %PGPORT% with the original demo data.
echo Any cases or users you added will be lost.
set /p answer=Continue? (y/n):
if /i not "%answer%"=="y" exit /b 0

"%PGBIN%\psql.exe" -h 127.0.0.1 -p %PGPORT% -U postgres -d HISS -w -f "%APP%database.sql"
pause
